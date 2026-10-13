# Install, deploy and run

Everything an operator needs. The product itself is described in the
[README](../README.md) · [بالعربية](../README.ar.md).

---

## Requirements

**Requirements:** PHP 8.2+ with `gd`, `zip`, `xml`, `fileinfo`, `mbstring`, `pdo_mysql` · Composer · MySQL 8+ (or MariaDB 10.2+).
The front-end assets are static files in `public/` — there is no build step for the Laravel app.

## Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Set your database credentials in `.env`, then:

```bash
php artisan migrate --seed
php artisan serve             # http://localhost:8000
```

Outside production, `--seed` loads demo data: one admin (`admin@admin.com`), 300 supervisors
and 500 students, **all with the password `adminadmin`**. It creates users only — no projects.
Never run the demo seed on a server that real people use (see *Deployment*).

For local mail and queues, `MAIL_MAILER=log` and `QUEUE_CONNECTION=sync` in `.env` avoid needing
a mail server or a worker: messages land in `storage/logs/laravel.log`.

## Tests

The tests run on a **separate copy** of your development database named `<DB_DATABASE>_testing`
(set in `phpunit.xml`). They depend on realistic data, so the copy is taken from your dev database:

```bash
php artisan test:prepare            # creates graduation_clc_testing from graduation_clc
php artisan test:prepare --force    # re-copy after new migrations or data changes
vendor/bin/phpunit
```

`tests/TestCase.php` refuses to run against any database whose name does not end in `_testing`.

## Deployment

1. **Environment.** In `.env`: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://your-domain`
   (password-reset links are built from it), `LOG_LEVEL=warning`, `SESSION_SECURE_COOKIE=true`,
   real SMTP credentials and a real `MAIL_FROM_ADDRESS`.
2. **Schema and first admin.** `--seed` in production plants only semesters and specializations —
   no accounts:
   ```bash
   php artisan migrate --force --seed
   php artisan admin:create          # prompts for name, email and password
   ```
   Activate the current semester from *Admin › Semesters* before students log in.
3. **Scheduler (required).** Mail is queued, teams are reminded of stage deadlines every morning
   (`milestones:remind` at 09:00: two days before and on the day), teams and committees are reminded
   of their defense (`defenses:remind` at 08:00: the day before and on the day — the email carries
   an `.ics` calendar file), and a daily stats snapshot feeds the dashboard trend.
   Add one cron entry:
   ```
   * * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
   ```
   It runs a queue worker that exits when the queue is empty. On a server with Supervisor or
   systemd, run a permanent `php artisan queue:work` there instead and remove the `queue:work`
   line from `app/Console/Kernel.php`.
4. **Writable paths:** `storage/`, `bootstrap/cache/` and `public/uploads/` (avatars).
   Project files are stored privately in `storage/app` and served only through an
   authorized download route — `storage:link` is not needed.
5. **PHP upload limits.** Project files accept up to 10 MB, defense presentations up to 20 MB and
   Excel imports up to 5 MB: set `upload_max_filesize = 22M` and `post_max_size = 26M`.
6. **Caches.** `php artisan config:cache && php artisan route:cache && php artisan view:cache`.
7. **Defenses.** Add the defense rooms from *Admin › Defenses › Rooms*; the planner suggests a slot,
   room and examiner for each completed project within the working hours in `config/defenses.php`
   (Sunday–Thursday, 09:00–15:00 by default). Online and hybrid defenses take a meeting link (the
   dialog has a *Create Google Meet* shortcut) — no Google API credentials are needed. A committee is
   the supervisor plus up to three examiners, one of them its chair. The final grade is the members'
   average; set `DEFENSE_SUPERVISOR_WEIGHT=40` (for example) to give the supervisor 40% and split the
   rest evenly between the examiners.
8. **Readiness check.** `php artisan deploy:check` verifies what can be checked automatically
   (environment, security, mail, queue, migrations, first admin, active semester, writable paths,
   upload limits, caches) and exits non-zero on anything critical. Run it after each deploy.

## Live demo (demo mode)

A public copy for a portfolio, where visitors try the platform without an account. It is off
unless `DEMO_MODE=true` is set, and nothing below happens without it.

- **One-click sign-in.** The sign-in page shows *Try as a student / supervisor / admin*. The
  accounts are picked from the data (a team leader whose project reached its defense, that
  project's supervisor, the first admin), or set them with `DEMO_STUDENT`, `DEMO_SUPERVISOR`,
  `DEMO_ADMIN` (emails).
- **Role switch.** A thin bar on every dashboard page switches between the three roles without
  signing out, so a visitor can send a message as the student and read it as the supervisor.
- **Protected.** Profile, password and avatar changes, password resets, deleting users, semesters
  and specializations, imports and the demo accounts themselves are refused with a short notice.
  Uploads are capped at `DEMO_UPLOAD_MB` (2). Mail goes to the log.
- **Nightly reset.** `php artisan demo:snapshot` saves the current database and uploaded files to
  `database/demo/` (run it once on the curated data and commit it). On the demo server,
  `php artisan demo:reset` restores it — it refuses to run without `DEMO_MODE` — and shifts every
  date by whole weeks since the snapshot, so the showcased defense stays upcoming and on a working
  day. The scheduler runs it daily at `DEMO_RESET_AT` (03:00).

```bash
# on the demo server, after deploying
DEMO_MODE=true                 # in .env
php artisan migrate --force
php artisan demo:reset         # load the snapshot
```

### On Vercel

The live demo runs on Vercel with the community PHP runtime (`vercel.json`, `api/`) and
an external MySQL-compatible database (TiDB Cloud Starter). The disk is read-only except `/tmp`,
so `api/_runtime.php` moves storage and Laravel's caches there and copies the snapshot's files in on
a cold start. Sessions live in the cookie, the cache in memory, mail in the log, and the queue runs
inline.

- Set in the project's environment: `APP_KEY`, `APP_URL`, `DB_*`, `DEMO_MODE=true`,
  `MYSQL_ATTR_SSL_CA=/etc/pki/tls/certs/ca-bundle.crt` (TLS is required), `SESSION_DRIVER=cookie`,
  `CACHE_STORE=array`, `LOG_CHANNEL=stderr`, `QUEUE_CONNECTION=sync`, `MAIL_MAILER=log`.
- Fill the database once from your machine, pointing `.env` at it: `php artisan migrate --force`
  then `php artisan demo:reset`.
- The nightly reset is a Vercel Cron Job (`vercel.json` › `crons`, 01:00 UTC) calling
  `api/demo-reset.php`, which runs `demo:reset`. Set `CRON_SECRET` in the project: Vercel sends it
  and the endpoint refuses any request without it. Files a visitor uploads last only while the
  instance stays warm.

## Next.js front end

```bash
cd frontend
npm install
cp .env.local.example .env.local
npm run dev                   # http://localhost:3000
```
