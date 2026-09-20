<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMobileIsVerified
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // اگر کاربر لاگین نیست، ادامه بده (بدون query)
        if (!$request->user()) {
            return $next($request);
        }

        // اگر در مسیرهای مجاز است، ادامه بده (بدون query)
        if ($request->routeIs('verify.mobile*') || $request->routeIs('logout')) {
            return $next($request);
        }

        // فقط در صورت نیاز، user را از cache یا database بگیر
        $user = $request->user();
        
        // اگر کاربر نیاز به تایید موبایل دارد
        if ($user->needsMobileVerification()) {
            return redirect()->route('verify.mobile')
                ->with('info', 'لطفاً ابتدا شماره موبایل خود را تایید کنید');
        }

        return $next($request);
    }
}
