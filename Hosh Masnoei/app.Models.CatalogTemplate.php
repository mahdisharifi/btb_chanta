<?php

namespace App\Models;

use App\Services\CatalogQueryService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_id',
        'config', // اضافه شد

        'name',
        'selected_categories',
        'selected_price_types',
        'settings',
    ];

    protected $casts = [
        'selected_categories' => 'array',
        'selected_price_types' => 'array',
        'settings' => 'array',
        'config' => 'array', // اضافه شد

    ];

    /**
     * Get the supplier that owns the catalog template.
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function getProducts()
    {
        return app(CatalogQueryService::class)->getProductsForTemplate($this);
    }
}
