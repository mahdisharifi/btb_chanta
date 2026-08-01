<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckAccountApproval
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // اگر کاربر لاگین نکرده، ادامه بده
        if (!$user) {
            return $next($request);
        }

        // فقط برای supplier و retailer چک می‌کنیم
        if (!$user->hasAnyRole(['supplier', 'regular_supplier', 'retailer_basic', 'retailer_premium'])) {
            return $next($request);
        }

        // اگر supplier داره، وضعیتش رو چک کن
        if ($user->supplier) {
            if ($user->supplier->verification_status === 'pending') {
                // اگر الان توی صفحه pending هست، بذار بره
                if ($request->routeIs('account.pending')) {
                    return $next($request);
                }

                // اگر نه، ریدایرکت به صفحه pending
                return redirect()->route('account.pending');
            }

            if ($user->supplier->verification_status === 'under_review') {
                // اگر الان توی صفحه under-review هست، بذار بره
                if ($request->routeIs('account.under-review')) {
                    return $next($request);
                }

                // اگر نه، ریدایرکت به صفحه under-review
                return redirect()->route('account.under-review');
            }

            if ($user->supplier->verification_status === 'rejected') {
                // اگر الان توی صفحه rejected هست، بذار بره
                if ($request->routeIs('account.rejected')) {
                    return $next($request);
                }

                // اگر نه، ریدایرکت به صفحه rejected
                return redirect()->route('account.rejected');
            }
        }

        return $next($request);
    }
}
