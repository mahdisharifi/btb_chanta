<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierBranchProductTransfer extends Model
{
    use HasFactory;

    public const PENDING = 'pending';
    public const ACCEPTED = 'accepted';
    public const REJECTED = 'rejected';
    public const CANCELLED = 'cancelled';

    protected $fillable = [
        'source_supplier_id',
        'target_supplier_id',
        'source_product_id',
        'destination_product_id',
        'status',
        'decided_at',
    ];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
    }

    public function sourceSupplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'source_supplier_id');
    }

    public function targetSupplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'target_supplier_id');
    }

    public function sourceProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'source_product_id');
    }

    public function destinationProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'destination_product_id');
    }
}
