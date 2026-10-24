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

            Route::middleware(['web'])
                ->group(base_path('routes/outsourcing.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        // The framework validates this signed cookie before the web middleware runs.
        $middleware->encryptCookies(except: ['laravel_maintenance']);
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
            'check.role.selection.access' => \App\Http\Middleware\CheckRoleSelectionAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->respond(function ($response, \Throwable $exception, \Illuminate\Http\Request $request) {
            $status = $response->getStatusCode();
            if ($status < 400 || $status > 599 || $request->expectsJson()
                || $request->is('api/*') || $request->hasHeader('X-Livewire')
                || str_contains($response->headers->get('Content-Type', ''), 'json')) {
                return $response;
            }

            // Preserve Laravel's native exception page whenever debug mode is enabled.
            // In production (APP_DEBUG=false), public HTML requests still receive the safe branded page.
            if ((bool) config('app.debug') || $request->is('admin/*')) {
                return $response;
            }

            // Keep protocol headers (Allow, Retry-After, cookies, etc.), never expose exception details.
            $response->setContent(view('errors.page', ['status' => $status])->render());
            $response->headers->set('Content-Type', 'text/html; charset=UTF-8');
            $response->headers->set('Cache-Control', 'no-store, private');
            $response->headers->remove('Content-Length');

            return $response;
        });
        $exceptions->render(function (\App\Exceptions\ProductSlugRedirectException $e) {
            return redirect()->route('products.show', $e->newSlug, 301);
        });
    })->create();
