<?php

namespace App\Models;

use App\Traits\HasLockableFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Supplier extends Model
{
    use SoftDeletes;

    protected $fillable = [
        // User relation
        'user_id',

        // Unified business profile (new schema)
        'type',
        'name',
        'slug',
        'description',
        'phone',
        'mobile',
        'email',
        'website',
        'social_networks',

        // Verification
        'verification_status',
        'verified_at',
        'verified_by',
        'rejection_reason',
        'verification_documents',

        // Company info (legacy/extended)
        'company_name',
        'company_name_en',
        'brand_name',
        'logo_path',

        // Address
        'address',
        'city',
        'province',
        'postal_code',
        'province_id',
        'city_id',

        // Legal info
        'tax_id',
        'national_id',

        // Status flags (legacy)
        'is_verified',
        'is_active',
    ];

    protected $casts = [

        'is_verified' => 'boolean',
        'is_active' => 'boolean',
        'verified_at' => 'datetime',
        'social_networks' => 'array',
        'verification_documents' => 'array',

    ];

    // Relations
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ownedProducts(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_supplier')
            ->withPivot('sku', 'is_primary', 'is_active', 'sort_order')
            ->withTimestamps();
    }

    public function activeProducts(): BelongsToMany
    {
        return $this->products()->wherePivot('is_active', true);
    }

    public function productSuppliers(): HasMany
    {
        return $this->hasMany(ProductSupplier::class);
    }

    public function profile(): HasOne
    {
        return $this->hasOne(SupplierProfile::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    // Status helpers
    public function isSupplier(): bool
    {
        return $this->type === 'supplier';
    }

    public function isRetailer(): bool
    {
        return $this->type === 'retailer';
    }

    public function isPending(): bool
    {
        return $this->verification_status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->verification_status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->verification_status === 'rejected';
    }

// متد برای چک کردن اینکه آیا کاربر می‌تواند ویرایش کند
    public function canEdit(): bool
    {
        return in_array($this->verification_status, ['pending', 'rejected']);
    }

// متد برای چک کردن اینکه آیا در حال بررسی است
    public function isUnderReview(): bool
    {
        return $this->verification_status === 'under_review';
    }

    public function socialNetworks(): BelongsToMany
    {
        return $this->belongsToMany(SocialNetwork::class, 'supplier_social_network')
            ->withPivot('link')
            ->withTimestamps();
    }

    public function province()
    {
        return $this->belongsTo(Province::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }
}
