<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OutsourcingSettlementItem extends Model
{
    protected $guarded = ['id'];
    protected function casts(): array
    {
        return ['snapshot_json' => 'array', 'amount' => 'decimal:2', 'is_previous' => 'boolean'];
    }
}
