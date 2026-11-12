<?php

namespace App\Livewire\Concerns;

use App\Helpers\QuantityTierValidator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

trait HasProductFormSteps
{
    protected bool $continueToPricing = false;
    #[Locked]
    public int $formStep = 1;

    #[Locked]
    public bool $firstFormStepSaved = false;

    public function nextFormStep(): void
    {
        $this->authorizeProductFormStep();
        $this->validateFirstFormStep();
        \Illuminate\Support\Facades\DB::transaction(fn () => $this->persistProductForm(true));
        $this->firstFormStepSaved = true;
        $this->formStep = 2;
        $this->dispatch('product-form-step-changed');
    }

    public function previousFormStep(): void
    {
        $this->formStep = max(1, $this->formStep - 1);
        $this->dispatch('product-form-step-changed');
    }

    public function submitProductForm()
    {
        if ($this->formStep === 1) {
            $this->nextFormStep();
            return null;
        }

        if ($this->supplierDraftMode || $this instanceof \App\Livewire\Supplier\Products\Edit) {
            $this->continueToPricing = true;
            try {
                $this->save();
            } finally {
                $this->continueToPricing = false;
            }
            $this->goToPricingStep();
            return null;
        }

        return $this->save();
    }

    public function goToPricingStep(): void
    {
        $this->authorizeProductFormStep();
        abort_unless($this->productId && $this->firstFormStepSaved, 403);
        abort_unless($this->supplierDraftMode || $this instanceof \App\Livewire\Supplier\Products\Edit, 403);
        $product = \App\Models\Product::findOrFail($this->productId);
        try {
            QuantityTierValidator::validate($product->quantity_tiers ?? []);
        } catch (ValidationException $exception) {
            $this->formStep = 1;
            $this->addError('quantity_tiers', 'برای قیمت‌گذاری ابتدا تیراژهای محصول را در مرحلهٔ اول ذخیره کنید.');
            $this->dispatch('product-form-step-changed');
            return;
        }
        $this->formStep = 3;
        $this->dispatch('product-form-step-changed');
    }

    protected function validateFirstFormStep(): void
    {
        try {
            $this->syncPackagingTier();
            foreach ($this->quantity_tiers as $index => $tier) {
                $this->quantity_tiers[$index]['max_quantity'] = $this->normalizeMaxQuantity($tier['max_quantity'] ?? null);
            }

            $this->validate(array_merge([
                'title' => 'required|string|max:255',
                'slug' => ['required', 'string', 'max:255', Rule::unique('products', 'slug')->ignore($this->productId)],
                'category_id' => 'required|exists:categories,id',
                'supplier_id' => 'required|exists:suppliers,id',
                'primary_image' => ($this->supplierDraftMode && !$this->isEdit ? 'required' : 'nullable').'|image|max:2048',
                'gallery_images' => 'array',
                'gallery_images.*' => 'nullable|image|max:2048',
                'product_video' => 'nullable|file|mimes:mp4,webm,ogg,mov|max:51200',
                'video_poster' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
                'packaging_unit_type' => 'required|string|in:carton,jean,jowab,package,bundle,roll,piece',
                'quantity_per_packaging' => 'required|integer|min:1|max:999999',
                'quantity_tiers' => 'required|array|min:3|max:4',
                'quantity_tiers.*.name' => 'required|string|max:100',
                'quantity_tiers.*.min_quantity' => 'required|integer|min:0',
                'quantity_tiers.*.max_quantity' => 'nullable|integer',
            ], $this->firstFormStepAdditionalRules()), [
                'primary_image.required' => 'افزودن یک تصویر اصلی برای محصول الزامی است.',
            ]);
            QuantityTierValidator::validate($this->quantity_tiers);
        } catch (ValidationException $exception) {
            $this->formStep = 1;
            $this->dispatch('product-form-step-changed');
            throw $exception;
        }
    }

    protected function firstFormStepAdditionalRules(): array
    {
        return [];
    }
}
