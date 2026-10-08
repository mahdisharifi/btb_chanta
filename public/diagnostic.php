<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Not Found');
}
/**
 * فایل عیب‌یابی خطای 500
 * این فایل را در مرورگر باز کنید: https://yourdomain.com/diagnostic.php
 * بعد از رفع مشکل، این فایل را حذف کنید!
 */

echo "<h1>Laravel Diagnostic Tool</h1>";
echo "<hr>";

// 1. PHP Version
echo "<h2>1. PHP Version</h2>";
echo "PHP Version: " . PHP_VERSION . "<br>";
echo "Required: >= 8.2 <br>";
if (version_compare(PHP_VERSION, '8.2.0', '>=')) {
    echo "<span style='color:green'>✓ OK</span><br>";
} else {
    echo "<span style='color:red'>✗ ERROR: PHP version too old</span><br>";
}

// 2. Extensions
echo "<h2>2. Required Extensions</h2>";
$required = ['pdo', 'pdo_mysql', 'mbstring', 'xml', 'json', 'openssl', 'tokenizer', 'ctype', 'fileinfo', 'bcmath', 'curl', 'dom', 'filter', 'hash', 'session'];
foreach ($required as $ext) {
    if (extension_loaded($ext)) {
        echo "<span style='color:green'>✓ $ext</span><br>";
    } else {
        echo "<span style='color:red'>✗ $ext (MISSING)</span><br>";
    }
}

// 3. .env file
echo "<h2>3. Environment File</h2>";
$envPath = __DIR__ . '/../.env';
if (file_exists($envPath)) {
    echo "<span style='color:green'>✓ .env file exists</span><br>";
    echo "Size: " . filesize($envPath) . " bytes<br>";
} else {
    echo "<span style='color:red'>✗ .env file NOT FOUND</span><br>";
    echo "Expected path: $envPath<br>";
}

// 4. Storage Permissions
echo "<h2>4. Storage Permissions</h2>";
$paths = [
    __DIR__ . '/../storage',
    __DIR__ . '/../storage/logs',
    __DIR__ . '/../storage/framework',
    __DIR__ . '/../storage/framework/cache',
    __DIR__ . '/../storage/framework/sessions',
    __DIR__ . '/../storage/framework/views',
    __DIR__ . '/../bootstrap/cache',
];

foreach ($paths as $path) {
    if (is_dir($path)) {
        $perms = substr(sprintf('%o', fileperms($path)), -4);
        $writable = is_writable($path) ? 'writable' : 'NOT WRITABLE';
        $color = is_writable($path) ? 'green' : 'red';
        echo "<span style='color:$color'>• $path - $perms - $writable</span><br>";
    } else {
        echo "<span style='color:red'>✗ $path (NOT EXISTS)</span><br>";
    }
}

// 5. Database Connection
echo "<h2>5. Database Connection</h2>";
try {
    $envContent = file_get_contents(__DIR__ . '/../.env');
    
    // Parse DB_ settings
    preg_match('/DB_HOST=(.+)/', $envContent, $host);
    preg_match('/DB_DATABASE=(.+)/', $envContent, $db);
    preg_match('/DB_USERNAME=(.+)/', $envContent, $user);
    preg_match('/DB_PASSWORD=(.+)/', $envContent, $pass);
    
    echo "Host: " . trim($host[1] ?? 'not set') . "<br>";
    echo "Database: " . trim($db[1] ?? 'not set') . "<br>";
    echo "Username: " . trim($user[1] ?? 'not set') . "<br>";
    
    // Try connection
    $host = trim($host[1] ?? 'localhost');
    $db = trim($db[1] ?? '');
    $user = trim($user[1] ?? '');
    $pass = trim($pass[1] ?? '');
    
    if ($db && $user) {
        $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
        echo "<span style='color:green'>✓ Database connection successful</span><br>";
    } else {
        echo "<span style='color:red'>✗ Database credentials not set</span><br>";
    }
} catch (Exception $e) {
    echo "<span style='color:red'>✗ Database connection failed: " . $e->getMessage() . "</span><br>";
}

// 6. APP_KEY
echo "<h2>6. Application Key</h2>";
if (strpos($envContent, 'APP_KEY=base64:') !== false) {
    echo "<span style='color:green'>✓ APP_KEY is set</span><br>";
} else {
    echo "<span style='color:red'>✗ APP_KEY is NOT set</span><br>";
    echo "Run: php artisan key:generate<br>";
}

// 7. Vendor Directory
echo "<h2>7. Vendor Directory</h2>";
if (is_dir(__DIR__ . '/../vendor')) {
    echo "<span style='color:green'>✓ vendor/ exists</span><br>";
    echo "Autoload: " . (file_exists(__DIR__ . '/../vendor/autoload.php') ? 'exists' : 'missing') . "<br>";
} else {
    echo "<span style='color:red'>✗ vendor/ NOT FOUND</span><br>";
    echo "Run: composer install --no-dev --optimize-autoloader<br>";
}

// 8. Laravel Version
echo "<h2>8. Laravel</h2>";
try {
    require_once __DIR__ . '/../vendor/autoload.php';
    $app = require_once __DIR__ . '/../bootstrap/app.php';
    echo "Laravel Version: " . app()->version() . "<br>";
    echo "<span style='color:green'>✓ Laravel loaded successfully</span><br>";
} catch (Exception $e) {
    echo "<span style='color:red'>✗ Laravel failed to load: " . $e->getMessage() . "</span><br>";
}

// 9. Error Logs
echo "<h2>9. Recent Errors</h2>";
$logFile = __DIR__ . '/../storage/logs/laravel.log';
if (file_exists($logFile)) {
    $lines = file($logFile);
    $lastLines = array_slice($lines, -20);
    echo "<pre style='background:#f5f5f5;padding:10px;overflow:auto;max-height:300px;'>";
    echo htmlspecialchars(implode('', $lastLines));
    echo "</pre>";
} else {
    echo "No log file found<br>";
}

echo "<hr>";
echo "<h2>⚠️ SECURITY WARNING</h2>";
echo "<p style='color:red;font-weight:bold;'>DELETE THIS FILE AFTER DIAGNOSTIC!</p>";
echo "<p>This file exposes sensitive information about your server.</p>";
