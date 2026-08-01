<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class OtpVerification extends Model
{
    protected $fillable = [
        'mobile',
        'code',
        'expires_at',
        'is_used',
        'ip_address',
        'attempts',


    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'is_used' => 'boolean',
        'attempts' => 'integer',
    ];

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * فقط کدهای منقضی نشده
     */
    public function scopeValid(Builder $query): Builder
    {
        return $query->where('is_used', false)
            ->where('expires_at', '>', now());
    }

    /**
     * کدهای مربوط به یک شماره موبایل خاص
     */
    public function scopeForMobile(Builder $query, string $mobile): Builder
    {
        return $query->where('mobile', $mobile);
    }

    /*
    |--------------------------------------------------------------------------
    | Helper Methods
    |--------------------------------------------------------------------------
    */

    /**
     * بررسی معتبر بودن کد
     */
    public function isValid(): bool
    {
        return !$this->is_used && $this->expires_at->isFuture();
    }

    /**
     * علامت‌گذاری به عنوان استفاده شده
     */
    public function markAsUsed(): bool
    {
        return $this->update(['is_used' => true]);
    }

    /**
     * بررسی منقضی شدن
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * ساخت کد تصادفی 6 رقمی
     */
    public static function generateCode(): string
    {
        return str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * پاک کردن کدهای منقضی شده (برای cleanup job)
     */
    public static function cleanupExpired(): int
    {
        return static::where('expires_at', '<', now()->subDay())
            ->delete();
    }
}
