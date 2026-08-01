<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckFeatureAccess
{
    public function handle(Request $request, Closure $next, string $featureSlug): Response
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        if (!auth()->user()->hasFeature($featureSlug)) {
            // ریدایرکت به صفحه خرید امکان با ارسال slug به عنوان خطا یا پیام
            abort(403, 'شما به این امکان دسترسی ندارید. لطفا برای استفاده، آن را خریداری کنید.');
        }

        return $next($request);
    }
}
