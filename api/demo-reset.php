<?php

/*
| الإعادة الليلية على Vercel: لا مجدوِل هناك، فيستدعي Vercel Cron هذا المسار
| مرة في اليوم (vercel.json › crons) ويشغّل ‎demo:reset‎ — البيانات والملفات تعود
| للّقطة، والتواريخ تُزاح لتبقى المناقشة المعروضة قادمة.
|
| Vercel يرسل ‎Authorization: Bearer <CRON_SECRET>‎؛ بدونه أو بغيره يُرفض الطلب،
| فلا يقدر زائر أن يمسح ما يجرّبه غيره.
*/

$secret = getenv('CRON_SECRET') ?: '';
if ($secret === '' || ! hash_equals("Bearer {$secret}", $_SERVER['HTTP_AUTHORIZATION'] ?? '')) {
    http_response_code(401);
    exit;
}

require __DIR__ . '/_runtime.php';
require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$status = $kernel->call('demo:reset');

http_response_code($status === 0 ? 200 : 500);
header('Content-Type: text/plain; charset=utf-8');
echo trim($kernel->output()), "\n";
