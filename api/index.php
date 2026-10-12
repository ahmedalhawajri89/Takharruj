<?php

/*
| مدخل Vercel (vercel-php): كل طلب لا يطابق ملفاً ثابتاً في vercel.json يصل هنا.
|
| القرص للقراءة فقط عدا ‎/tmp‎: التخزين والكاش المترجم يُنقلان إليه، وملفات
| اللقطة التجريبية تُنسخ إليه في أول تشغيل لتعمل التنزيلات. ما يرفعه الزوار
| يبقى ما دامت النسخة دافئة ثم يزول — مقبول في نسخة العرض.
*/

$storage = '/tmp/storage';

foreach (['app/public', 'framework/cache/data', 'framework/sessions', 'framework/views', 'logs'] as $dir) {
    if (! is_dir("{$storage}/{$dir}")) {
        mkdir("{$storage}/{$dir}", 0777, true);
    }
}

$demoFiles = __DIR__ . '/../database/demo/files';
if (is_dir($demoFiles) && ! file_exists("{$storage}/app/.demo-files")) {
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($demoFiles, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($items as $item) {
        $target = "{$storage}/app/" . substr($item->getPathname(), strlen($demoFiles) + 1);
        $item->isDir() ? (is_dir($target) || mkdir($target, 0777, true)) : copy($item->getPathname(), $target);
    }
    touch("{$storage}/app/.demo-files");
}

$paths = [
    'LARAVEL_STORAGE_PATH' => $storage,
    'VIEW_COMPILED_PATH' => "{$storage}/framework/views",
    'APP_CONFIG_CACHE' => '/tmp/config.php',
    'APP_EVENTS_CACHE' => '/tmp/events.php',
    'APP_PACKAGES_CACHE' => '/tmp/packages.php',
    'APP_ROUTES_CACHE' => '/tmp/routes.php',
    'APP_SERVICES_CACHE' => '/tmp/services.php',
];
foreach ($paths as $key => $value) {
    putenv("{$key}={$value}");
    $_ENV[$key] = $_SERVER[$key] = $value;
}

// Vercel يخدم HTTPS دائماً ويصل الطلب إلى PHP عبر وكيله: بدون هذا تُبنى
// الروابط والأصول بـ http فيحجبها المتصفح.
$_SERVER['HTTPS'] = 'on';

require __DIR__ . '/../public/index.php';
