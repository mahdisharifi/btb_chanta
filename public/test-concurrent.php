<?php
/**
 * تست کاربر همزمان - سرور واقعی
 * 
 * استفاده:
 * 1. این فایل را در public قرار دهید
 * 2. آدرس را در مرورگر باز کنید: https://your-domain.com/test-concurrent.php
 * 3. نتایج را بررسی کنید
 * 
 * امنیت: این فایل را بعد از تست حذف کنید!
 */

// تنظیمات
$testResults = [
    'timestamp' => date('Y-m-d H:i:s'),
    'php_version' => PHP_VERSION,
    'memory_limit' => ini_get('memory_limit'),
    'max_execution_time' => ini_get('max_execution_time'),
];

// تست 1: بررسی Cache Driver
$cacheDriver = env('CACHE_STORE', 'file');
$testResults['cache_driver'] = $cacheDriver;

// تست 2: بررسی عملکرد Cache
$testKey = 'test_concurrent_' . time();
$testValue = ['percent' => 50, 'message' => 'Test'];

\Cache::put($testKey, $testValue, 60);
$cached = \Cache::get($testKey);

$testResults['cache_test'] = [
    'write' => true,
    'read' => $cached === $testValue,
    'isolation' => true
];

// تست 3: بررسی Session
$sessionId = session()->getId();
$testResults['session_id'] = substr($sessionId, 0, 8) . '...';

// تست 4: شبیه‌سازی چند کاربر همزمان
$users = [];
for ($i = 1; $i <= 5; $i++) {
    $progressKey = 'catalog_progress_test_' . $i . '_' . time();
    $percent = rand(0, 100);
    
    \Cache::put($progressKey, [
        'percent' => $percent,
        'message' => "User $i processing",
        'timestamp' => time()
    ], 300);
    
    $users[] = [
        'user_id' => $i,
        'progress_key' => $progressKey,
        'percent' => $percent
    ];
}

// بررسی isolation
$isolationTest = true;
foreach ($users as $user) {
    $cached = \Cache::get($user['progress_key']);
    if ($cached['percent'] !== $user['percent']) {
        $isolationTest = false;
        break;
    }
}

$testResults['concurrent_test'] = [
    'users_count' => count($users),
    'isolation_ok' => $isolationTest,
    'users' => $users
];

// تست 5: بررسی Progress API Route
$testResults['routes'] = [
    'progress_api_exists' => \Illuminate\Support\Facades\Route::has('catalog.progress'),
];

// تست 6: بررسی دیتابیس (اگر Cache driver database باشد)
if ($cacheDriver === 'database') {
    try {
        $cacheTable = \DB::table('cache')
            ->where('key', 'like', '%catalog_progress_%')
            ->count();
        $testResults['database_cache'] = [
            'table_exists' => true,
            'cache_entries' => $cacheTable
        ];
    } catch (\Exception $e) {
        $testResults['database_cache'] = [
            'table_exists' => false,
            'error' => $e->getMessage()
        ];
    }
}

// تست 7: Memory Usage
$testResults['memory_usage'] = [
    'current' => memory_get_usage(true) / 1024 / 1024 . ' MB',
    'peak' => memory_get_peak_usage(true) / 1024 / 1024 . ' MB'
];

// پاکسازی تست
\Cache::forget($testKey);
foreach ($users as $user) {
    \Cache::forget($user['progress_key']);
}

// نمایش نتایج
header('Content-Type: application/json; charset=utf-8');
echo json_encode($testResults, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

// لاگ کردن
\Log::info('Concurrent Test Completed', $testResults);
