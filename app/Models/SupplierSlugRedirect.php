<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierSlugRedirect extends Model
{
    public $timestamps = false;

    protected $fillable = ['supplier_id', 'old_slug', 'new_slug', 'created_by'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $redirect) => $redirect->created_at ??= now());
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
