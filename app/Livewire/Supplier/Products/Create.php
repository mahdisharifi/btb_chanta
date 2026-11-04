<?php

namespace App\Livewire\Supplier\Products;

use App\Enums\ProductStatus;
use App\Livewire\Admin\Products\Create as AdminProductCreate;
use App\Models\Product;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;

#[Layout('components.layouts.supplier')]
class Create extends AdminProductCreate
{
    public function mount(?Product $product = null): void
    {
        $user = auth()->user();
        abort_unless($user && $user->supplier()->exists(), 403);

        $supplier = $user->currentSupplier();

        $this->supplierDraftMode = true;
        $this->supplier_id = $supplier->id;
        $this->status = ProductStatus::DRAFT->value;
        $this->is_active = false;
        $this->sku = 'محصول-' . strtoupper(Str::random(8));

        $this->loadDefaultCategories();
    }

    protected function isSupplierDraftModeContext(): bool
    {
        return true;
    }
}
