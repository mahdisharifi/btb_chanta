<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscriptionExpiry
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // اگر کاربر لاگین نیست یا نقش خرده فروش ویژه ندارد، عبور کند
        // نقش ها با توجه به ساختار Spatie در نظر گرفته شده است
        if (!$user || !$user->hasRole('RETAILER_PREMIUM')) {
            return $next($request);
        }

        // کلید کش یکتا برای این کاربر در امروز
        $cacheKey = 'vip_check_' . $user->id . '_' . now()->format('Y_m_d');

        // بررسی وضعیت اشتراک با کش
        // نکته: برای تست روی 15 ثانیه تنظیم شده است. در سرور اصلی به now()->endOfDay() تغییر یابد.
        $isVipValid = Cache::remember($cacheKey, now()->addSeconds(15), function () use ($user) {
            $activeSubscription = $user->subscriptions()
                ->where('ends_at', '>=', now())
                ->where('status', 'active')
                ->exists();

            if (!$activeSubscription) {
                // گرفتن نقش بلافاصله برای جلوگیری از دسترسی
                $user->removeRole('RETAILER_PREMIUM');
                // بازگردانی به نقش پایه (در صورت نیاز)
                if (!$user->hasRole('RETAILER_BASIC')) {
                    $user->assignRole('RETAILER_BASIC');
                }
                return false;
            }

            return true;
        });

        // اگر اشتراک معتبر نبود، هدایت به صفحه اشتراک
        if (!$isVipValid) {
            return redirect()->route('retailer.subscriptions.index')
                ->with('error', 'اشتراک شما به پایان رسیده است. برای دسترسی، لطفاً اشتراک خود را تمدید کنید.');
        }

        return $next($request);
    }
}
