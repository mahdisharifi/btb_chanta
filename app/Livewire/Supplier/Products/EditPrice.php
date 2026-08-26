<?php

namespace App\Livewire\Supplier\Products;

use App\Models\Product;
use App\Models\ProductSupplier;
use App\Models\PriceType;
use App\Models\CheckPeriod;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.supplier')]
class EditPrice extends Component
{
    public Product $product;
    public ?ProductSupplier $productSupplier;

    // Properties for the form
    public array $priceMatrix = []; // ساختار: [tier_index][price_type_id] = ['price' => ..., 'warranty_*' => ...]
    public ?string $sku = '';
    public bool $is_active = false;
    
    // تیراژها و انواع قیمت
    public array $quantityTiers = [];
    public $priceTypes;
    public $checkPeriods;

    /**
     * Mount component
     */
    public function mount(Product $product)
    {
        $this->product = $product;
        $supplierId = Auth::user()->supplier->id;
        
        // Find or create the relationship
        $this->productSupplier = ProductSupplier::firstOrCreate(
            [
                'product_id' => $this->product->id,
                'supplier_id' => $supplierId,
            ],
            [
                'is_active' => false,
            ]
        );

        // Load data
        $this->sku = $this->productSupplier->sku;
        $this->is_active = $this->productSupplier->is_active;
        
        // تیراژهای محصول
        $this->quantityTiers = $this->product->quantity_tiers ?? [];
        
        // بارگذاری انواع قیمت و دوره‌های چک
        $this->priceTypes = PriceType::where('is_active', true)->orderBy('sort_order')->get();
        
        // دوره‌های پیش‌فرض + دوره‌های اختصاصی این تامین‌کننده (مرتب شده بر اساس تعداد روز)
        $this->checkPeriods = CheckPeriod::where(function($query) use ($supplierId) {
            $query->global()
                  ->orWhere('supplier_id', $supplierId);
        })
        ->active()
        ->orderBy('days') // ترتیب بر اساس تعداد روز
        ->get();
        
        $this->initializePriceMatrix();
    }

    /**
     * مقداردهی اولیه ماتریس قیمت
     */
    protected function initializePriceMatrix(): void
    {
        // بارگذاری قیمت‌های موجود
        $existingPrices = $this->productSupplier->prices()
            ->whereNotNull('price_type_id') // فقط قیمت‌های سیستم جدید
            ->get()
            ->groupBy(function($price) {
                return $price->tier_index . '_' . $price->price_type_id . '_' . ($price->check_period_id ?? 'null');
            });

        // ایجاد ماتریس خالی
        foreach ($this->quantityTiers as $tierIndex => $tier) {
            if (!isset($this->priceMatrix[$tierIndex])) {
                $this->priceMatrix[$tierIndex] = [];
            }
            
            foreach ($this->priceTypes as $priceType) {
                $key = $priceType->id;
                
                // قیمت نقدی یا گارانتی
                $existingPrice = $existingPrices->get($tierIndex . '_' . $key . '_null')?->first();
                
                $this->priceMatrix[$tierIndex][$key] = [
                    'price' => $existingPrice->price ?? null,
                    'warranty_type' => $existingPrice->warranty_type ?? null,
                    'warranty_duration' => $existingPrice->warranty_duration ?? null,
                ];
                
                // قیمت‌های چکی (اگر نوع قیمت = چکی باشد)
                if ($priceType->code === 'check') {
                    $this->priceMatrix[$tierIndex][$key]['check_periods'] = [];
                    
                    foreach ($this->checkPeriods as $period) {
                        $checkKey = $tierIndex . '_' . $key . '_' . $period->id;
                        $checkPrice = $existingPrices->get($checkKey)?->first();
                        
                        $this->priceMatrix[$tierIndex][$key]['check_periods'][$period->id] = [
                            'price' => $checkPrice->price ?? null,
                        ];
                    }
                }
            }
        }
    }

    /**
     * Validation rules
     */
    public function rules(): array
    {
        return [
            // اطلاعات عرضه
            'sku' => ['nullable', 'string', 'max:255', 'regex:/^[a-zA-Z0-9\-_]+$/'],
            'is_active' => ['boolean'],
            
            // ماتریس قیمت
            'priceMatrix' => ['array'],
            'priceMatrix.*' => ['array'],
            'priceMatrix.*.*' => ['array'],
            
            // قیمت اصلی (نقدی/گارانتی)
            'priceMatrix.*.*.price' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            
            // فیلدهای گارانتی
            'priceMatrix.*.*.warranty_type' => ['nullable', 'string', 'in:test,limited,lifetime'],
            'priceMatrix.*.*.warranty_duration' => [
                'nullable',
                'integer',
                'min:1',
                'max:3650', // حداکثر 10 سال
                'required_if:priceMatrix.*.*.warranty_type,limited'
            ],
            
            // قیمت‌های چکی
            'priceMatrix.*.*.check_periods' => ['nullable', 'array'],
            'priceMatrix.*.*.check_periods.*' => ['array'],
            'priceMatrix.*.*.check_periods.*.price' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
        ];
    }
    
    /**
     * پیام‌های خطای سفارشی
     */
    public function messages(): array
    {
        return [
            'sku.regex' => 'SKU فقط می‌تواند شامل حروف انگلیسی، اعداد، خط تیره و زیرخط باشد.',
            'priceMatrix.*.*.price.numeric' => 'قیمت باید عدد باشد.',
            'priceMatrix.*.*.price.min' => 'قیمت نمی‌تواند منفی باشد.',
            'priceMatrix.*.*.price.max' => 'قیمت بیش از حد مجاز است.',
            'priceMatrix.*.*.warranty_type.in' => 'نوع گارانتی نامعتبر است.',
            'priceMatrix.*.*.warranty_duration.required_if' => 'برای گارانتی مدت‌دار، مدت گارانتی الزامی است.',
            'priceMatrix.*.*.warranty_duration.max' => 'مدت گارانتی نمی‌تواند بیشتر از 10 سال باشد.',
            'priceMatrix.*.*.check_periods.*.price.numeric' => 'قیمت چک باید عدد باشد.',
            'priceMatrix.*.*.check_periods.*.price.min' => 'قیمت چک نمی‌تواند منفی باشد.',
        ];
    }

    /**
     * ذخیره قیمت‌ها
     */
    public function save()
    {
        $this->validate();
        
        // امنیت: بررسی دسترسی تامین‌کننده به این محصول
        $supplierId = Auth::user()->supplier->id;
        if ($this->productSupplier->supplier_id !== $supplierId) {
            abort(403, 'شما مجاز به ویرایش این محصول نیستید.');
        }

        DB::transaction(function () use ($supplierId) {
            // 1. بروزرسانی اطلاعات تامین‌کننده
            $this->productSupplier->update([
                'sku' => $this->sanitizeInput($this->sku),
                'is_active' => (bool)$this->is_active,
            ]);

            // 2. حذف قیمت‌های قبلی سیستم جدید
            $this->productSupplier->prices()
                ->whereNotNull('price_type_id')
                ->delete();

            // 3. ذخیره قیمت‌های جدید
            foreach ($this->priceMatrix as $tierIndex => $priceTypes) {
                // امنیت: بررسی tier_index معتبر
                if (!is_numeric($tierIndex) || $tierIndex < 0 || $tierIndex >= count($this->quantityTiers)) {
                    continue;
                }
                
                foreach ($priceTypes as $priceTypeId => $data) {
                    // امنیت: بررسی price_type_id معتبر
                    if (!is_numeric($priceTypeId)) {
                        continue;
                    }
                    
                    $priceType = $this->priceTypes->firstWhere('id', $priceTypeId);
                    if (!$priceType) continue;
                    
                    // قیمت نقدی یا گارانتی
                    if (!empty($data['price']) && is_numeric($data['price'])) {
                        $warrantyType = $data['warranty_type'] ?? null;
                        $warrantyDuration = $data['warranty_duration'] ?? null;
                        
                        // امنیت: Sanitize warranty fields
                        if ($warrantyType && !in_array($warrantyType, ['test', 'limited', 'lifetime'])) {
                            $warrantyType = null;
                        }
                        
                        if ($warrantyDuration && (!is_numeric($warrantyDuration) || $warrantyDuration < 0)) {
                            $warrantyDuration = null;
                        }
                        
                        $this->productSupplier->prices()->create([
                            'product_id' => $this->product->id,
                            'price_type_id' => (int)$priceTypeId,
                            'tier_index' => (int)$tierIndex,
                            'check_period_id' => null,
                            'price' => (float)$data['price'],
                            'warranty_type' => $warrantyType,
                            'warranty_duration' => $warrantyDuration ? (int)$warrantyDuration : null,
                            'is_active' => (bool)$this->is_active,
                        ]);
                    }
                    
                    // قیمت‌های چکی
                    if ($priceType->code === 'check' && isset($data['check_periods']) && is_array($data['check_periods'])) {
                        foreach ($data['check_periods'] as $periodId => $periodData) {
                            // امنیت: بررسی period_id معتبر
                            if (!is_numeric($periodId)) {
                                continue;
                            }
                            
                            // امنیت: بررسی دسترسی به این دوره
                            $period = $this->checkPeriods->firstWhere('id', $periodId);
                            if (!$period) {
                                continue;
                            }
                            
                            // بررسی دوره global یا متعلق به این تامین‌کننده
                            if ($period->scope === 'supplier' && $period->supplier_id !== $supplierId) {
                                continue;
                            }
                            
                            if (!empty($periodData['price']) && is_numeric($periodData['price'])) {
                                $this->productSupplier->prices()->create([
                                    'product_id' => $this->product->id,
                                    'price_type_id' => (int)$priceTypeId,
                                    'tier_index' => (int)$tierIndex,
                                    'check_period_id' => (int)$periodId,
                                    'price' => (float)$periodData['price'],
                                    'is_active' => (bool)$this->is_active,
                                ]);
                            }
                        }
                    }
                }
            }
        });

        // بارگذاری مجدد دوره‌های چک (برای نمایش دوره‌های جدید)
        $this->checkPeriods = CheckPeriod::where(function($query) use ($supplierId) {
            $query->global()
                  ->orWhere('supplier_id', $supplierId);
        })
        ->active()
        ->orderBy('days')
        ->get();
        
        // بارگذاری مجدد قیمت‌های ذخیره شده
        $this->initializePriceMatrix();

        $this->dispatch('notify',
            message: 'ماتریس قیمت با موفقیت ذخیره شد.',
            type: 'success'
        );
        
        session()->flash('message', 'ماتریس قیمت با موفقیت ذخیره شد.');
    }
    
    /**
     * Sanitize user input
     */
    private function sanitizeInput(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }
        
        return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
    }

    public function render()
    {
        return view('livewire.supplier.products.edit-price');
    }
}
