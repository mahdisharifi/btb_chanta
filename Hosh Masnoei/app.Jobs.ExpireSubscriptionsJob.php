<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\UserSubscription;
use App\Enums\UserRole;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ExpireSubscriptionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // پیدا کردن اشتراک‌های فعالی که تاریخ انقضای آن‌ها گذشته است
        UserSubscription::where('status', 'active')
            ->where('ends_at', '<', now())
            ->with('user') // Eager load برای جلوگیری از N+1
            ->chunkById(100, function ($subscriptions) {
                foreach ($subscriptions as $subscription) {
                    $user = $subscription->user;

                    if ($user) {
                        // 1. حذف نقش کاسب ویژه و اختصاص نقش کاسب عادی
                        if ($user->hasRole(UserRole::RETAILER_PREMIUM->value)) {
                            $user->removeRole(UserRole::RETAILER_PREMIUM->value);

                            // اگر کاربر نقش بیسیک را ندارد به او اختصاص دهیم
                            if (!$user->hasRole(UserRole::RETAILER_BASIC->value)) {
                                $user->assignRole(UserRole::RETAILER_BASIC->value);
                            }
                        }

                        // 2. پاک کردن کش بازدید روزانه (در صورت نیاز)
                        $cacheKey = 'subscription_checked_today_' . $user->id;
                        Cache::forget($cacheKey);

                        // 3. ثبت در لاگ
                        Log::info("اشتراک کاربر {$user->id} منقضی و نقش وی به کاسب عادی تغییر یافت.");
                    }

                    // 4. تغییر وضعیت اشتراک به منقضی شده
                    $subscription->update(['status' => 'expired']);
                }
            });
    }
}
