<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\EnsureMobileIsVerified;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware(['web'])
                ->group(base_path('routes/admin.php'));

            Route::middleware(['web'])
                ->group(base_path('routes/supplier.php'));

            Route::middleware(['web'])
                ->group(base_path('routes/retailer.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        // اضافه کردن به گروه web
        $middleware->web(append: [
            EnsureMobileIsVerified::class,
        ]);

        // alias ها
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'check.subscription' => \App\Http\Middleware\CheckSubscriptionExpiry::class,
            'verified.mobile' => \App\Http\Middleware\EnsureMobileIsVerified::class,
            'check.approval' => \App\Http\Middleware\CheckAccountApproval::class,

        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (\App\Exceptions\ProductSlugRedirectException $e) {
            return redirect()->route('products.show', $e->newSlug, 301);
        });
    })->create();
