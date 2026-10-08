<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    $view = app('view')->make('pages.suppliers.index', [
        'suppliers' => collect([]),
        'stats' => ['total' => 0, 'verified' => 0, 'cities' => 0],
        'provinces' => [],
        'breadcrumbs' => [],
        'searchQuery' => '',
        'sortBy' => 'newest'
    ]);
    $html = $view->render();
    echo "Success!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}
