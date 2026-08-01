<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SubscriptionPlan extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'duration_days',
        'price',
        'old_price',
        'features',
        'is_active',
        'sort_order',
        'target_role', // این فیلد اضافه شد
    ];

    protected $casts = [
        'price' => 'decimal:0',
        'old_price' => 'decimal:0',
        'is_active' => 'boolean',
        'features' => 'array',
    ];

    public function userSubscriptions()
    {
        return $this->hasMany(UserSubscription::class);
    }
}
