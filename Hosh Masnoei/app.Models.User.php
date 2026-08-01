<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;
use App\Traits\HasPurchasableFeatures;


class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;
    use HasPurchasableFeatures;

    protected $fillable = [
        'name',
        'email',
        'mobile',
        'password',
        'mobile_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /*
    |--------------------------------------------------------------------------
    | Boot Method
    |--------------------------------------------------------------------------
    */

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }
    /**
     * دریافت نقش اصلی کاربر بر اساس وضعیت تایید
     * اگر تایید نشده باشد، نقش پایه برمی‌گردد
     */
    public function getPrimaryRole()
    {
        // اگر سرپرست یا ادمین است، نقش اصلی
        if ($this->hasRole(['admin', 'super_admin'])) {
            return $this->roles->first();
        }

        // اگر supplier یا retailer دارد
        if ($this->supplier) {
            // اگر تایید نشده، نقش پایه
            if ($this->supplier->verification_status !== 'approved') {
                return $this->roles()->where('name', 'user')->first() ?? $this->roles->first();
            }
        }

        // نقش اصلی فعلی
        return $this->roles->first();
    }

    /**
     * چک کردن اینکه آیا حساب کاربر تایید شده یا نه
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'mobile_verified_at' => 'datetime', // اضافه شد
            'password' => 'hashed',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function supplier(): HasOne
    {
        return $this->hasOne(Supplier::class);
    }

    // اضافه کردن رابطه پروفایل تامین‌کننده
    public function supplierProfile()
    {
        return $this->hasOne(SupplierProfile::class);
    }

    public function retailPrices(): HasMany
    {
        return $this->hasMany(RetailPrice::class);
    }

    // ارتباط کاربر با اشتراک‌ها
    public function subscriptions()
    {
        return $this->hasMany(UserSubscription::class);
    }

    // ارتباط کاربر با تراکنش‌ها
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Helper Methods
    |--------------------------------------------------------------------------
    */

    public function isAdmin(): bool
    {
        return $this->hasRole(UserRole::SUPER_ADMIN->value);
    }

    public function isSupplier(): bool
    {
        return $this->hasRole(UserRole::SUPPLIER->value);
    }

    public function isRegularSupplier(): bool
    {
        return $this->hasRole(UserRole::REGULAR_SUPPLIER->value);
    }

    public function isRetailerPremium(): bool
    {
        return $this->hasRole(UserRole::RETAILER_PREMIUM->value);
    }

    public function isRetailerBasic(): bool
    {
        return $this->hasRole(UserRole::RETAILER_BASIC->value);
    }

    public function isEndUser(): bool
    {
        return $this->hasRole(UserRole::END_USER->value);
    }

    public function isRetailer(): bool
    {
        return $this->isRetailerPremium() || $this->isRetailerBasic();
    }

    public function canViewSupplierPrices(): bool
    {
        return $this->isAdmin() || $this->isSupplier() || $this->isRetailerPremium();
    }

    public function canViewSupplierInfo(): bool
    {
        return $this->isAdmin() || $this->isRetailerPremium();
    }

    public function getRoleLabel(): string
    {
        $role = $this->roles->first();
        if (!$role) {
            return 'بدون نقش';
        }

        $enum = UserRole::tryFrom($role->name);
        return $enum ? $enum->label() : $role->name;
    }

    // متد کلیدی برای بررسی وضعیت VIP کاربر
    public function hasActiveVip(): bool
    {
        return $this->subscriptions()
            ->where('status', 'active')
            ->where('ends_at', '>', now())
            ->exists();
    }

    /*
    |--------------------------------------------------------------------------
    | Mobile Verification Methods (جدید)
    |--------------------------------------------------------------------------
    */

    /**
     * بررسی اینکه آیا کاربر نیاز به تایید موبایل دارد یا خیر
     * - کاربران END_USER نیازی به تایید موبایل ندارند
     * - کاربرانی که موبایل ندارند نیازی به تایید ندارند
     * - سایر نقش‌ها باید موبایلشان تایید شده باشد
     */
    public function needsMobileVerification(): bool
    {

        // کاربران عادی (END_USER) نیازی به تایید موبایل ندارند
        if ($this->isEndUser()) {
            return false;
        }

        // اگر موبایل ندارد، نیاز به تایید ندارد
        if (empty($this->mobile)) {
            return false;
        }

        // اگر موبایل تایید نشده، باید تایید کند
        return is_null($this->mobile_verified_at);

    }

    /**
     * بررسی اینکه آیا موبایل تایید شده است
     */
    public function hasMobileVerified(): bool
    {
        return !is_null($this->mobile_verified_at);
    }

    /**
     * علامت‌گذاری موبایل به عنوان تایید شده
     */
    public function markMobileAsVerified(): bool
    {
        return $this->forceFill([
            'mobile_verified_at' => now(),
        ])->save();
    }


    public function isAccountApproved(): bool
    {
        if (!$this->supplier) {
            return true; // کاربر عادی
        }

        return $this->supplier->verification_status === 'approved';
    }



}
