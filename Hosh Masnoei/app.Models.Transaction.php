<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'amount',
        'gateway',
        'authority',
        'ref_id',
        'status',
        'description',
        'ip_address',
        'gateway_response',
    ];

    protected $casts = [
        'amount' => 'decimal:0',
        'gateway_response' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function userSubscription()
    {
        return $this->hasOne(UserSubscription::class);
    }

    // متدهای کمکی برای بررسی وضعیت
    public function isSuccessful(): bool
    {
        return $this->status === 'success';
    }
}
