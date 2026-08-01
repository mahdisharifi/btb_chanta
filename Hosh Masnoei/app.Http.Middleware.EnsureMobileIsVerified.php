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
        $user = $request->user();

        // اگر کاربر لاگین نیست، ادامه بده
        if (!$user) {

            return $next($request);
        }


        // اگر کاربر نیاز به تایید موبایل دارد
        if ($user->needsMobileVerification()) {

            // اگر در مسیر verify-mobile نیست، redirect کن
            if (!$request->routeIs('verify.mobile*')&& !$request->routeIs('logout')) {
                return redirect()->route('verify.mobile')
                    ->with('info', 'لطفاً ابتدا شماره موبایل خود را تایید کنید');
            }
        }

        return $next($request);
    }
}
