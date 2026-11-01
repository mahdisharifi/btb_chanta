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

        $supplierId = Auth::user()->currentSupplier()->id;

        $selectedProduct = Product::query()->whereKey($this->product_id)->firstOrFail();
        if (Auth::user()->currentSupplier()->isBranch()) {
            $familyId = (int) ($selectedProduct->family_product_id ?: $selectedProduct->id);
            $this->product = Product::query()
                ->where('supplier_id', $supplierId)
                ->where('family_product_id', $familyId)
                ->firstOrFail();
        } else {
            $this->product = $selectedProduct;
            abort_unless(
                (int) $this->product->supplier_id === (int) $supplierId
                    || ProductSupplier::query()->where('product_id', $this->product->id)->where('supplier_id', $supplierId)->exists(),
                403,
                'این محصول در شعبهٔ جاری ثبت نشده است.'
            );
        }

        $this->productSupplier = ProductSupplier::query()
            ->where('product_id', $this->product->id)
            ->where('supplier_id', $supplierId)
            ->first();

        if (!$this->productSupplier && (int) $this->product->supplier_id === (int) $supplierId) {
            $this->productSupplier = ProductSupplier::create([
                'product_id' => $this->product->id,
                'supplier_id' => $supplierId,
                'is_active' => true,
            ]);
        }

        abort_unless($this->productSupplier, 403, 'این محصول در شعبهٔ جاری ثبت نشده است.');

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
                'warranty_months' => $existingPrices->has($typeValue) ? $existingPrices->get($typeValue)->warranty_months : null,
            ];
        }
    }

    protected function rules()
    {
        return [
            'prices.*.price' => 'nullable|numeric|min:0',
            'prices.*.min_quantity' => 'required_with:prices.*.price|nullable|integer|min:1',
            'prices.*.warranty_months' => 'nullable|integer|min:1|max:120',
            'sku' => 'nullable|string|max:255',
            'stock_quantity' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ];
    }

    public function save()
    {
        $this->validate();

        $supplier = Auth::user()->currentSupplier();
        $supplierId = (int) $supplier->id;
        $blockedByCurrency = false;
        $this->product = Product::query()
            ->whereKey($this->product->id)
            ->where(function ($query) use ($supplier): void {
                $query->where('supplier_id', $supplier->id)
                    ->orWhereHas('productSuppliers', fn ($offers) => $offers->where('supplier_id', $supplier->id));
            })
            ->firstOrFail();
        $this->productSupplier = ProductSupplier::query()
            ->whereKey($this->productSupplier->id)
            ->where('product_id', $this->product->id)
            ->where('supplier_id', $supplier->id)
            ->firstOrFail();

        // استفاده از تراکنش برای جلوگیری از ثبت ناقص اطلاعات در دو جدول
        $saved = DB::transaction(function () use ($supplierId, &$blockedByCurrency): bool {
            $supplier = \App\Models\Supplier::query()->whereKey($supplierId)->lockForUpdate()->firstOrFail();
            $productSupplier = ProductSupplier::query()
                ->whereKey($this->productSupplier->getKey())
                ->where('supplier_id', $supplierId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($supplier->pricing_currency !== 'TOM' || $productSupplier->price_currency !== 'TOM') {
                foreach (PriceType::cases() as $type) {
                    $typeValue = $type->value;
                    $priceData = $this->prices[$typeValue] ?? [];
                    $existing = $productSupplier->prices()->where('price_type', $typeValue)->first();
                    $newPrice = $priceData['price'] ?? null;
                    $unchanged = $existing
                        ? ($newPrice !== null && $newPrice !== '' && (float) $newPrice === (float) $existing->price)
                        : ($newPrice === null || $newPrice === '');

                    if (!$unchanged) {
                        $blockedByCurrency = true;
                        return false;
                    }
                }
            }

            $productSupplier->update([
                'sku' => $this->sku,
                'stock_quantity' => $this->stock_quantity,
                'is_active' => $this->is_active,
            ]);

            foreach (PriceType::cases() as $type) {
                $typeValue = $type->value;
                $priceData = $this->prices[$typeValue] ?? null;

                if (!empty($priceData['price']) && is_numeric($priceData['price'])) {
                    $productSupplier->prices()->updateOrCreate(
                        ['price_type' => $typeValue],
                        [
                            'product_id' => $this->product->id,
                            'price' => $priceData['price'],
                            'min_quantity' => $priceData['min_quantity'] ?? 1,
                            'warranty_months' => $priceData['warranty_months'] ?? null,
                        ]
                    );
                } else {
                    $productSupplier->prices()->where('price_type', $typeValue)->delete();
                }
            }
            app(\App\Services\ProductPriceRevisionService::class)
                ->increment((int) $productSupplier->product_id, (int) $productSupplier->supplier_id);

            $this->productSupplier = $productSupplier;
            return true;
        });

        if (!$saved && $blockedByCurrency) {
            $this->addError('prices', 'قیمت نقدی این کالا از ارز پایهٔ تامین‌کننده پیروی می‌کند؛ برای تغییر قیمت از صفحهٔ ماتریس قیمت استفاده کنید.');
            return;
        }

        \App\Jobs\BroadcastSupplierProductPrices::dispatch(
            (int) $this->productSupplier->supplier_id,
            [(int) $this->productSupplier->id],
        );

        session()->flash('success', 'اطلاعات قیمت و موجودی با موفقیت ذخیره شد.');

        // ریدایرکت به صفحه لیست محصولات تامین‌کننده به‌جای انتخاب محصول
        return redirect()->route('supplier.product-selection');
    }

    public function render()
    {
        return view('livewire.supplier.pricing');
    }
}
