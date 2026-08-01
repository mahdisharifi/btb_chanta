<?php

namespace App\Livewire\Supplier;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use App\Models\Product;
use App\Models\ProductSupplier;
use App\Enums\PriceType;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

#[Layout('components.layouts.supplier')]
class Pricing extends Component
{
    #[Url]
    public $product_id;

    public ?Product $product = null;
    public ?ProductSupplier $productSupplier = null;

    public array $prices = [];
    public ?string $sku = '';
    public ?int $stock_quantity = null;
    public bool $is_active = true;

    public function mount()
    {
        if (!$this->product_id) {
            session()->flash('error', 'لطفا ابتدا یک محصول را انتخاب کنید.');
            return redirect()->route('supplier.product-selection');
        }

        $this->product = Product::findOrFail($this->product_id);
        $supplierId = Auth::user()->supplier->id;

        $this->productSupplier = ProductSupplier::firstOrCreate(
            [
                'product_id' => $this->product->id,
                'supplier_id' => $supplierId,
            ],
            [
                'is_active' => true,
            ]
        );

        $this->sku = $this->productSupplier->sku;
        $this->stock_quantity = $this->productSupplier->stock_quantity ?? 0;
        $this->is_active = $this->productSupplier->is_active;

        $this->initializePrices();
    }

    protected function initializePrices()
    {
        $existingPrices = $this->productSupplier->prices->keyBy('price_type');

        foreach (PriceType::cases() as $type) {
            $typeValue = $type->value;
            $this->prices[$typeValue] = [
                'price' => $existingPrices->has($typeValue) ? $existingPrices->get($typeValue)->price : '',
                'min_quantity' => $existingPrices->has($typeValue) ? $existingPrices->get($typeValue)->min_quantity : 1,
            ];
        }
    }

    protected function rules()
    {
        return [
            'prices.*.price' => 'nullable|numeric|min:0',
            'prices.*.min_quantity' => 'required_with:prices.*.price|nullable|integer|min:1',
            'sku' => 'nullable|string|max:255',
            'stock_quantity' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ];
    }

    public function save()
    {
        $this->validate();

        // استفاده از تراکنش برای جلوگیری از ثبت ناقص اطلاعات در دو جدول
        DB::transaction(function () {
            $this->productSupplier->update([
                'sku' => $this->sku,
                'stock_quantity' => $this->stock_quantity,
                'is_active' => $this->is_active,
            ]);

            foreach (PriceType::cases() as $type) {
                $typeValue = $type->value;
                $priceData = $this->prices[$typeValue] ?? null;

                if (!empty($priceData['price']) && is_numeric($priceData['price'])) {
                    $this->productSupplier->prices()->updateOrCreate(
                        ['price_type' => $typeValue],
                        [
                            'product_id' => $this->product->id,
                            'price' => $priceData['price'],
                            'min_quantity' => $priceData['min_quantity'] ?? 1,
                        ]
                    );
                } else {
                    $this->productSupplier->prices()->where('price_type', $typeValue)->delete();
                }
            }
        });

        session()->flash('success', 'اطلاعات قیمت و موجودی با موفقیت ذخیره شد.');

        // ریدایرکت به صفحه لیست محصولات تامین‌کننده به‌جای انتخاب محصول
        return redirect()->route('supplier.product-selection');
    }

    public function render()
    {
        return view('livewire.supplier.pricing');
    }
}
