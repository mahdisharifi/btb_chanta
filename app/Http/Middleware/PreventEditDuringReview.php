<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PreventEditDuringReview
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if (!$user) {
            return redirect()->route('login');
        }

        $supplier = $user->supplier;

        if ($supplier && $supplier->verification_status === 'under_review') {
            return redirect()
                ->route('supplier.dashboard')
                ->with('error', 'امکان ویرایش پروفایل در حین بررسی وجود ندارد');
        }

        return $next($request);
    }
}
