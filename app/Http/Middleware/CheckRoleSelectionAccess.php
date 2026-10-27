<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRoleSelectionAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();
        
        // اگر کاربر supplier یا retailer تایید شده است، نباید به role-selection دسترسی داشته باشد
        if ($user && $user->hasRole(['supplier', 'regular_supplier', 'retailer_premium', 'retailer_basic', 'super_admin', 'admin'])) {
            // استفاده از helper function که در routes/auth.php تعریف شده
            if (function_exists('redirectByRole')) {
                return redirectByRole($user);
            }
            
            // fallback
            return redirect()->route('dashboard');
        }
        
        return $next($request);
    }
}
