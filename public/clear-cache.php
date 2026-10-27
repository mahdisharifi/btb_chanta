<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Not Found');
}
/**
 * پاکسازی کش برای رفع مشکل نمایش محتوای ادمین به کاربران مهمان
 * آدرس: https://new.chanta.ir/clear-cache.php
 * بعد از اجرا، این فایل را حذف کنید!
 */

echo "<h1>Cache Clearing Tool</h1><hr>";

// 1. Laravel Cache Clear
echo "<h2>1. Laravel Cache</h2>";
try {
    $artisan = __DIR__ . '/../artisan';
    $output = shell_exec("php $artisan optimize:clear 2>&1");
    echo "<pre>$output</pre>";
    echo "<span style='color:green'>✓ Laravel cache cleared</span><br>";
} catch (Exception $e) {
    echo "<span style='color:red'>✗ Error: " . $e->getMessage() . "</span><br>";
}

// 2. OPcache Clear
echo "<h2>2. OPcache</h2>";
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "<span style='color:green'>✓ OPcache cleared</span><br>";
} else {
    echo "<span style='color:orange'>OPcache not available</span><br>";
}

// 3. LiteSpeed Cache Clear
echo "<h2>3. LiteSpeed Cache</h2>";
if (function_exists('litespeed_request_headers')) {
    header('X-LiteSpeed-Purge: *');
    echo "<span style='color:green'>✓ LSCache purge requested</span><br>";
} else {
    // روش جایگزین: ایجاد فایل .lscache_purge
    $purgeFile = __DIR__ . '/.lscache_purge';
    if (file_put_contents($purgeFile, date('Y-m-d H:i:s'))) {
        echo "<span style='color:green'>✓ LSCache purge file created</span><br>";
    } else {
        echo "<span style='color:orange'>Could not create purge file</span><br>";
    }
}

// 4. File Cache Clear
echo "<h2>4. File Cache</h2>";
$cachePath = __DIR__ . '/../storage/framework/cache';
if (is_dir($cachePath)) {
    $files = glob($cachePath . '/*');
    $cleared = 0;
    foreach ($files as $file) {
        if (is_file($file) && basename($file) !== '.gitignore') {
            unlink($file);
            $cleared++;
        }
    }
    echo "<span style='color:green'>✓ Cleared $cleared cache files</span><br>";
}

// 5. View Cache Clear
echo "<h2>5. View Cache</h2>";
$viewPath = __DIR__ . '/../storage/framework/views';
if (is_dir($viewPath)) {
    $files = glob($viewPath . '/*');
    $cleared = 0;
    foreach ($files as $file) {
        if (is_file($file) && basename($file) !== '.gitignore') {
            unlink($file);
            $cleared++;
        }
    }
    echo "<span style='color:green'>✓ Cleared $cleared view files</span><br>";
}

echo "<hr>";
echo "<h2>✅ Cache Cleared Successfully!</h2>";
echo "<p><strong>Now test the pages that had the issue:</strong></p>";
echo "<ul>";
echo "<li><a href='/products/اسپیکر-BTS-2241' target='_blank'>Test Product 1</a></li>";
echo "<li><a href='/products/ABS-206-01' target='_blank'>Test Product 2</a></li>";
echo "</ul>";
echo "<hr>";
echo "<p style='color:red;font-weight:bold;'>⚠️ DELETE THIS FILE AFTER USE!</p>";
