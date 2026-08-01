<?php

namespace App\Livewire\Supplier\Products;

use App\Enums\PriceType;
use App\Models\Product;
use App\Models\ProductSupplier;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
#[Layout('components.layouts.supplier')]

class EditPrice extends Component
{
    public Product $product;
    public ?ProductSupplier $productSupplier;

    // Properties for the form
    public array $prices = [];
    public ?string $sku = '';
    public bool $is_active = false;

    /**
     * Mounts the component, loads the product and supplier-specific data.
     */
    public function mount(Product $product)
    {
        $this->product = $product;
        $supplierId = Auth::user()->supplier->id;
        // Find or create the relationship record between the product and the supplier
        $this->productSupplier = ProductSupplier::firstOrCreate(
            [
                'product_id' => $this->product->id,
                'supplier_id' => $supplierId,
            ],
            [
                'is_active' => false, // Default to inactive
            ]
        );

        // Load existing data into form properties
        $this->sku = $this->productSupplier->sku;
        $this->is_active = $this->productSupplier->is_active;

        $this->initializePrices();
    }

    /**
     * Initializes the prices array for the form, loading existing values
     * or setting defaults for all available price types.
     */
    protected function initializePrices(): void
    {

        // Load existing prices from the database, keyed by their type for easy access
        $existingPrices = $this->productSupplier->prices->keyBy('price_type');



        // Prepare the prices array for the form, ensuring all types are present
        foreach (PriceType::cases() as $priceType) {
            $typeValue = $priceType->value;
            $this->prices[$typeValue] = [
                'price' => $existingPrices->get($typeValue)->price ?? '',
                'min_quantity' => $existingPrices->get($typeValue)->min_quantity ?? 1,
            ];
        }
    }

    /**
     * Validation rules for the form.
     */
    public function rules(): array
    {
        return [
            // Validate each item in the prices array
            'prices.*.price' => ['nullable', 'numeric', 'min:0'],
            'prices.*.min_quantity' => ['required_with:prices.*.price', 'nullable', 'integer', 'min:1'],

            // Validate general supplier product info
            'sku' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * A helper method to get the Persian label for a price type.
     * This can be moved to the Enum or a Helper class for better architecture.
     */
    public function getPriceTypeLabel(string $type): string
    {
        return match ($type) {
            PriceType::SUB_CARTON->value => 'قیمت خرده (زیر کارتن)',
            PriceType::CARTON->value => 'قیمت کارتنی',
            PriceType::WARRANTY->value => 'قیمت با گارانتی',
            PriceType::CHECK_1_MONTH->value => 'قیمت چک ۱ ماهه',
            PriceType::CHECK_2_MONTH->value => 'قیمت چک ۲ ماهه',
            PriceType::CHECK_3_MONTH->value => 'قیمت چک ۳ ماهه',
            PriceType::CUSTOM->value => 'قیمت سفارشی',
            default => 'ناشناخته',
        };
    }

    /**
     * Saves the form data to the database.
     */
    public function save()
    {
        $this->validate();

        // 1. Update the main ProductSupplier record
        $this->productSupplier->update([
            'sku' => $this->sku,
            'is_active' => $this->is_active,
        ]);

        // 2. Update or create prices for each type
        foreach ($this->prices as $type => $data) {
            // Only process if a price has been entered
            if (!empty($data['price']) && is_numeric($data['price'])) {
                $this->productSupplier->prices()->updateOrCreate(

                    ['price_type' => $type], // Condition to find the record
                    [                 // Data to update or create
                        'product_id' => $this->product->id, // این خط اضافه شود
                        'price' => $data['price'],
                        'min_quantity' => $data['min_quantity'] ?? 1,

                    ]
                );
            } else {
                // If the price is removed (empty), delete the record from the database
                $this->productSupplier->prices()->where('price_type', $type)->delete();
            }
        }

        session()->flash('message', 'اطلاعات قیمت با موفقیت ذخیره شد.');
        $this->redirectRoute('supplier.products.index');
    }

    public function render()
    {
        return view('livewire.supplier.products.edit-price');
    }
}
