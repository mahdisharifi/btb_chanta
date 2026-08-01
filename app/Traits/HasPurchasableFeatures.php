<?php

namespace App\Traits;

use App\Models\Feature;
use App\Models\UserSubscription;

trait HasPurchasableFeatures
{
    public function purchasedFeatures()
    {
        return $this->belongsToMany(Feature::class, 'user_features')
            ->withPivot('ends_at', 'is_active', 'purchased_at', 'paid_amount', 'purchased_days')
            ->withTimestamps();
    }

    public function hasFeature(string $featureSlug): bool
    {
        $feature = $this->purchasedFeatures()
            ->where('slug', $featureSlug)
            ->wherePivot('is_active', true)
            ->first();

        if (!$feature) {
            return false;
        }

        // بررسی انقضا
        if ($feature->pivot->expires_at && $feature->pivot->expires_at < now()) {
            return false;
        }

        return true;
    }

    // دریافت اشتراک فعال کاربر (برای محاسبه زمان باقی‌مانده)
    public function getActiveSubscription()
    {
        return UserSubscription::where('user_id', $this->id)
            ->where('status', 'active') // یا هر فیلدی که برای وضعیت دارید
            ->where('ends_at', '>', now())
            ->latest()
            ->first();
    }
}
