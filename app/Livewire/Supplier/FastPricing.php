<?php

namespace App\Livewire\Supplier;

use App\Enums\PriceType;
use App\Models\ProductPrice;
use App\Models\ProductSupplier;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.supplier')]
class FastPricing extends Component
{
    use WithPagination;

    #[Url(history: true)]
    public string $search = '';

    #[Url(history: true)]
    public string $viewMode = 'table'; // 'table' or 'grid'

    public array $pricingData = [];

    public function mount()
    {
        // آماده‌سازی اولیه در صورت نیاز
    }

    public function setViewMode($mode)
    {
        $this->viewMode = in_array($mode, ['table', 'grid']) ? $mode : 'table';
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    protected function loadProductData($products)
    {
        foreach ($products as $ps) {
            if (!isset($this->pricingData[$ps->product_id])) {
                $this->pricingData[$ps->product_id] = [
                    'is_active' => $ps->is_active,
                    'stock_quantity' => $ps->stock_quantity,
                    'prices' => []
                ];

                foreach (PriceType::cases() as $type) {
                    $priceModel = $ps->prices->where('price_type', $type->value)->first();
                    $this->pricingData[$ps->product_id]['prices'][$type->value] = [
                        'price' => $priceModel ? $priceModel->price : null,
                        'min_quantity' => $priceModel ? $priceModel->min_quantity : 1,
                        'max_quantity' => $priceModel ? $priceModel->max_quantity : null,
                    ];
                }
            }
        }
    }

    public function save($productId)
    {
        $supplier = auth()->user()->supplier;

        if (!isset($this->pricingData[$productId])) {
            return;
        }

        try {
            DB::beginTransaction();

            $data = $this->pricingData[$productId];

            $productSupplier = ProductSupplier::where('supplier_id', $supplier->id)
                ->where('product_id', $productId)
                ->firstOrFail();

            $productSupplier->update([
                'is_active' => $data['is_active'] ?? false,
                'stock_quantity' => $data['stock_quantity'] ?? 0,
            ]);

            foreach (PriceType::cases() as $type) {
                if (isset($data['prices'][$type->value])) {
                    $priceData = $data['prices'][$type->value];

                    if (!empty($priceData['price'])) {
                        ProductPrice::updateOrCreate(
                            [
                                'product_supplier_id' => $productSupplier->id,
                                'price_type' => $type->value,
                            ],
                            [
                                'product_id' => $productId, // اضافه شدن این خط برای رفع خطای ۱۳۶۴
                                'price' => $priceData['price'],
                                'min_quantity' => $priceData['min_quantity'] ?? 1,
                                'max_quantity' => empty($priceData['max_quantity']) ? null : $priceData['max_quantity'],
                            ]
                        );
                    }
                }
            }

            DB::commit();

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'قیمت و وضعیت محصول با موفقیت ذخیره شد.'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'خطا در ذخیره اطلاعات: ' . $e->getMessage()
            ]);
        }
    }

    public function render()
    {
        $supplier = auth()->user()->supplier;

        $products = ProductSupplier::with(['product.primaryImage', 'prices'])
            ->where('supplier_id', $supplier->id)
            ->when($this->search, function ($query) {
                $query->whereHas('product', function ($q) {
                    $q->where('title_fa', 'like', '%' . $this->search . '%')
                        ->orWhere('title_en', 'like', '%' . $this->search . '%');
                });
            })
            ->paginate(15);

        $this->loadProductData($products->items());

        return view('livewire.supplier.fast-pricing', [
            'products' => $products
        ]);
    }
}
