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
    
    // ✅ هزینه گارانتی سراسری
    public string $warrantyType = 'none';  // none, percentage, fixed, custom
    public ?float $warrantyValue = null;
    
    // ✅ اطلاعات گارانتی محصول
    public bool $hasWarranty = false;           // آیا محصول با گارانتی است؟
    public string $warrantyProvider = 'none';   // none, manufacturer, seller
    public string $warrantyCoverageType = 'full'; // full, limited, parts_only, service_only
    public string $warrantyTypeCoverage = 'limited'; // ✅ جدید: test, limited, lifetime (نوع گارانتی)
    public ?int $warrantyDurationDays = null;   // مدت گارانتی (روز)
    
    // تیراژها و انواع قیمت
    public array $quantityTiers = [];
    public $priceTypes;
    public $checkPeriods;

    /**
     * Updated hook - اجرا می‌شود وقتی property تغییر می‌کند
     */
    public function updatedWarrantyType($value)
    {
        // اگر از custom به گزینه دیگر سویچ شد، قیمت‌های گارانتی در ماتریس را پاک کن
        if ($value !== 'custom') {
            $this->clearWarrantyPricesFromMatrix();
        }
    }
    
    /**
     * پاک کردن قیمت‌های گارانتی از ماتریس
     */
    protected function clearWarrantyPricesFromMatrix(): void
    {
        // پیدا کردن price_type_id مربوط به گارانتی
        $warrantyPriceType = $this->priceTypes->firstWhere('code', 'warranty');
        
        if (!$warrantyPriceType) {
            return;
        }
        
        // پاک کردن قیمت‌های گارانتی از تمام تیراژها
        foreach ($this->priceMatrix as $tierIndex => $priceTypes) {
            if (isset($priceTypes[$warrantyPriceType->id]['price'])) {
                $this->priceMatrix[$tierIndex][$warrantyPriceType->id]['price'] = null;
            }
        }
    }

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
        
        // ✅ بارگذاری تنظیمات گارانتی
        $this->warrantyType = $this->productSupplier->warranty_type ?? 'none';
        $this->warrantyValue = $this->productSupplier->warranty_value;
        $this->hasWarranty = $this->productSupplier->has_warranty ?? false;
        $this->warrantyProvider = $this->productSupplier->warranty_provider ?? 'none';
        $this->warrantyCoverageType = $this->productSupplier->warranty_coverage_type ?? 'full';
        $this->warrantyTypeCoverage = $this->productSupplier->warranty_type_coverage ?? 'limited'; // ✅
        $this->warrantyDurationDays = $this->productSupplier->warranty_duration_days;
        
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
            
            // ✅ هزینه گارانتی سراسری
            'warrantyType' => ['required', 'string', 'in:none,percentage,fixed,custom'],
            'warrantyValue' => [
                'nullable',
                'numeric',
                'min:0',
                function ($attribute, $value, $fail) {
                    // فقط اگر نوع percentage یا fixed است، value الزامی است
                    if (in_array($this->warrantyType, ['percentage', 'fixed']) && ($value === null || $value === '')) {
                        $fail('مقدار هزینه گارانتی الزامی است.');
                    }
                    
                    // بررسی محدوده درصد
                    if ($this->warrantyType === 'percentage' && $value !== null && $value > 100) {
                        $fail('درصد گارانتی نمی‌تواند بیشتر از 100 باشد.');
                    }
                    
                    // بررسی محدوده مبلغ
                    if ($this->warrantyType === 'fixed' && $value !== null && $value > 999999999) {
                        $fail('مبلغ گارانتی بیش از حد مجاز است.');
                    }
                },
            ],
            
            // ✅ اطلاعات گارانتی محصول
            'hasWarranty' => ['boolean'],
            'warrantyProvider' => [
                function ($attribute, $value, $fail) {
                    if ($this->hasWarranty && ($value === null || $value === '' || $value === 'none')) {
                        $fail('ارائه‌دهنده گارانتی الزامی است. لطفاً شرکتی یا فروشنده را انتخاب کنید.');
                    }
                    if ($value !== null && !in_array($value, ['none', 'manufacturer', 'seller'])) {
                        $fail('ارائه‌دهنده گارانتی نامعتبر است.');
                    }
                },
            ],
            'warrantyCoverageType' => [
                function ($attribute, $value, $fail) {
                    if ($this->hasWarranty && ($value === null || $value === '')) {
                        $fail('نوع پوشش گارانتی الزامی است.');
                    }
                    if ($value !== null && !in_array($value, ['full', 'limited', 'parts_only', 'service_only'])) {
                        $fail('نوع پوشش گارانتی نامعتبر است.');
                    }
                },
            ],
            'warrantyTypeCoverage' => [
                function ($attribute, $value, $fail) {
                    if ($this->hasWarranty && ($value === null || $value === '')) {
                        $fail('نوع گارانتی الزامی است.');
                    }
                    if ($value !== null && !in_array($value, ['test', 'limited', 'lifetime'])) {
                        $fail('نوع گارانتی نامعتبر است.');
                    }
                },
            ],
            'warrantyDurationDays' => [
                'nullable',
                'integer',
                'min:1',
                'max:7300', // حداکثر 20 سال
                function ($attribute, $value, $fail) {
                    // اگر نوع گارانتی تست یا مدت‌دار است، مدت الزامی است
                    if ($this->hasWarranty && in_array($this->warrantyTypeCoverage, ['test', 'limited']) && ($value === null || $value === '')) {
                        $fail('برای گارانتی تست و مدت‌دار، تعیین مدت گارانتی الزامی است.');
                    }
                },
            ],
            
            // ماتریس قیمت
            'priceMatrix' => ['array'],
            'priceMatrix.*' => ['array'],
            'priceMatrix.*.*' => ['array'],
            
            // قیمت اصلی (نقدی/گارانتی)
            'priceMatrix.*.*.price' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            
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
            
            'warrantyType.required' => 'نوع هزینه گارانتی الزامی است.',
            'warrantyType.in' => 'نوع هزینه گارانتی نامعتبر است.',
            'warrantyValue.required_if' => 'مقدار هزینه گارانتی الزامی است.',
            'warrantyValue.numeric' => 'مقدار گارانتی باید عدد باشد.',
            'warrantyValue.min' => 'مقدار گارانتی نمی‌تواند منفی باشد.',
            
            'warrantyProvider.required_if' => 'ارائه‌دهنده گارانتی الزامی است.',
            'warrantyProvider.in' => 'ارائه‌دهنده گارانتی نامعتبر است.',
            'warrantyCoverageType.required_if' => 'نوع پوشش گارانتی الزامی است.',
            'warrantyCoverageType.in' => 'نوع پوشش گارانتی نامعتبر است.',
            'warrantyTypeCoverage.required_if' => 'نوع گارانتی الزامی است.',
            'warrantyTypeCoverage.in' => 'نوع گارانتی نامعتبر است.',
            'warrantyDurationDays.integer' => 'مدت گارانتی باید عدد صحیح باشد.',
            'warrantyDurationDays.min' => 'مدت گارانتی نمی‌تواند کمتر از 1 روز باشد.',
            'warrantyDurationDays.max' => 'مدت گارانتی نمی‌تواند بیشتر از 20 سال (7300 روز) باشد.',
            
            'priceMatrix.*.*.price.numeric' => 'قیمت باید عدد باشد.',
            'priceMatrix.*.*.price.min' => 'قیمت نمی‌تواند منفی باشد.',
            'priceMatrix.*.*.price.max' => 'قیمت بیش از حد مجاز است.',
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
                'warranty_type' => $this->warrantyType,
                'warranty_value' => $this->warrantyValue,
                'has_warranty' => $this->hasWarranty,
                'warranty_provider' => $this->warrantyProvider,
                'warranty_coverage_type' => $this->warrantyCoverageType,
                'warranty_type_coverage' => $this->warrantyTypeCoverage,     // ✅ جدید
                'warranty_duration_days' => $this->warrantyDurationDays,
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
                    
                    // ✅ اگر نوع قیمت = گارانتی و warrantyType != custom، از ذخیره صرف‌نظر کن
                    if ($priceType->code === 'warranty' && $this->warrantyType !== 'custom') {
                        continue;
                    }
                    
                    // قیمت نقدی یا گارانتی
                    if (!empty($data['price']) && is_numeric($data['price'])) {
                        $this->productSupplier->prices()->create([
                            'product_id' => $this->product->id,
                            'price_type_id' => (int)$priceTypeId,
                            'tier_index' => (int)$tierIndex,
                            'check_period_id' => null,
                            'price' => (float)$data['price'],
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
