<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OutsourcingSettlementPayment extends Model
{
    protected $guarded = ['id'];
    protected $hidden = ['idempotency_key'];
    protected function casts(): array { return ['amount' => 'decimal:2', 'paid_on' => 'date']; }
}
