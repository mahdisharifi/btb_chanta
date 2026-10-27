<?php
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$request = Illuminate\Http\Request::create('http://127.0.0.1:8000/categories', 'GET');
$response = $kernel->handle($request);
echo 'status='.$response->getStatusCode().PHP_EOL;
echo 'icon_class='.(class_exists(App\Support\CategoryIcon::class) ? 'yes' : 'no').PHP_EOL;
echo 'bytes='.strlen($response->getContent()).PHP_EOL;
if ($response->getStatusCode() >= 400) {
    echo substr(strip_tags($response->getContent()), 0, 300).PHP_EOL;
}
