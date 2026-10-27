<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Not Found');
}
/**
 * تست ساده برای بررسی مشکل
 * آدرس: https://new.chanta.ir/test-simple.php
 */

echo "<h1>Laravel Quick Test</h1>";
echo "<hr>";

// تست PHP
echo "<h2>1. PHP Status</h2>";
echo "PHP Version: " . PHP_VERSION . "<br>";
echo "Status: <span style='color:green'>OK</span><br>";

// تست Storage Writable
echo "<h2>2. Storage Writable?</h2>";
$storagePath = __DIR__ . '/../storage';
if (is_writable($storagePath)) {
    echo "Storage: <span style='color:green'>WRITABLE ✓</span><br>";
} else {
    echo "Storage: <span style='color:red'>NOT WRITABLE ✗</span><br>";
    echo "<strong>SOLUTION:</strong> chmod -R 775 storage<br>";
}

// تست Cache Writable
echo "<h2>3. Bootstrap Cache Writable?</h2>";
$cachePath = __DIR__ . '/../bootstrap/cache';
if (is_writable($cachePath)) {
    echo "Bootstrap Cache: <span style='color:green'>WRITABLE ✓</span><br>";
} else {
    echo "Bootstrap Cache: <span style='color:red'>NOT WRITABLE ✗</span><br>";
    echo "<strong>SOLUTION:</strong> chmod -R 775 bootstrap/cache<br>";
}

// تست .env
echo "<h2>4. .env File Exists?</h2>";
$envPath = __DIR__ . '/../.env';
if (file_exists($envPath)) {
    echo ".env: <span style='color:green'>EXISTS ✓</span><br>";
    
    // بررسی APP_KEY
    $envContent = file_get_contents($envPath);
    if (strpos($envContent, 'APP_KEY=base64:') !== false) {
        echo "APP_KEY: <span style='color:green'>SET ✓</span><br>";
    } else {
        echo "APP_KEY: <span style='color:red'>NOT SET ✗</span><br>";
        echo "<strong>SOLUTION:</strong> php artisan key:generate<br>";
    }
    
    // بررسی APP_DEBUG
    if (strpos($envContent, 'APP_DEBUG=true') !== false) {
        echo "APP_DEBUG: <span style='color:orange'>true (set to false if error persists)</span><br>";
    } else {
        echo "APP_DEBUG: false<br>";
    }
} else {
    echo ".env: <span style='color:red'>NOT FOUND ✗</span><br>";
}

// تست Vendor
echo "<h2>5. Vendor Directory?</h2>";
$vendorPath = __DIR__ . '/../vendor';
if (is_dir($vendorPath)) {
    echo "Vendor: <span style='color:green'>EXISTS ✓</span><br>";
    echo "Autoload: " . (file_exists($vendorPath . '/autoload.php') ? 'EXISTS ✓' : 'MISSING ✗') . "<br>";
} else {
    echo "Vendor: <span style='color:red'>NOT FOUND ✗</span><br>";
    echo "<strong>SOLUTION:</strong> composer install --no-dev<br>";
}

// تست Laravel Load
echo "<h2>6. Laravel Can Load?</h2>";
try {
    require_once __DIR__ . '/../vendor/autoload.php';
    $app = require_once __DIR__ . '/../bootstrap/app.php';
    echo "Laravel: <span style='color:green'>LOADED ✓</span><br>";
    echo "Version: " . app()->version() . "<br>";
} catch (Throwable $e) {
    echo "Laravel: <span style='color:red'>FAILED ✗</span><br>";
    echo "Error: " . $e->getMessage() . "<br>";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "<br>";
}

echo "<hr>";
echo "<p><strong>⚠️ DELETE THIS FILE AFTER TESTING!</strong></p>";
