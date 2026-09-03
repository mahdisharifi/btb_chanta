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
    
    // ✅ قیمت‌گذاری چک
    public array $checkPriceMode = []; // [tier_index][period_id] = 'default' | 'percentage' | 'manual'
    public array $checkPercentageOverride = []; // [tier_index][period_id] = float (درصد اختصاصی)
    public array $checkPeriodEnabled = []; // [tier_index][period_id] = true/false (فعال/غیرفعال)
    public array $effectivePercentages = []; // [period_id] = ['value' => float, 'source' => string]
    
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
            
            // مقداردهی اولیه checkPriceMode و checkPercentageOverride
            if (!isset($this->checkPriceMode[$tierIndex])) {
                $this->checkPriceMode[$tierIndex] = [];
            }
            if (!isset($this->checkPercentageOverride[$tierIndex])) {
                $this->checkPercentageOverride[$tierIndex] = [];
            }
            if (!isset($this->checkPeriodEnabled[$tierIndex])) {
                $this->checkPeriodEnabled[$tierIndex] = [];
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
                        
                        // ✅ تعیین mode بر اساس داده‌های موجود
                        if ($checkPrice) {
                            // دوره فعال است (چون رکورد وجود دارد)
                            $this->checkPeriodEnabled[$tierIndex][$period->id] = true;
                            
                            if ($checkPrice->percentage_override !== null) {
                                // mode: percentage
                                $this->checkPriceMode[$tierIndex][$period->id] = 'percentage';
                                $this->checkPercentageOverride[$tierIndex][$period->id] = (float)$checkPrice->percentage_override;
                                $this->priceMatrix[$tierIndex][$key]['check_periods'][$period->id]['price'] = null;
                            } elseif ($checkPrice->price !== null && $checkPrice->price > 0) {
                                // mode: manual (قیمت دستی بزرگتر از 0)
                                $this->checkPriceMode[$tierIndex][$period->id] = 'manual';
                                $this->priceMatrix[$tierIndex][$key]['check_periods'][$period->id]['price'] = $checkPrice->price;
                            } else {
                                // mode: default (price = 0 یا null)
                                $this->checkPriceMode[$tierIndex][$period->id] = 'default';
                                $this->priceMatrix[$tierIndex][$key]['check_periods'][$period->id]['price'] = null;
                            }
                        } else {
                            // هیچ رکوردی وجود ندارد → دوره غیرفعال است
                            $this->checkPeriodEnabled[$tierIndex][$period->id] = false;
                            $this->checkPriceMode[$tierIndex][$period->id] = 'default';
                            $this->priceMatrix[$tierIndex][$key]['check_periods'][$period->id]['price'] = null;
                        }
                    }
                }
            }
        }
        
        // بارگذاری درصدهای مؤثر
        $this->loadEffectivePercentages();
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
            
            // ✅ قیمت‌گذاری چک
            'checkPriceMode' => ['array'],
            'checkPriceMode.*' => ['array'],
            'checkPriceMode.*.*' => ['nullable', 'string', 'in:default,percentage,manual'],
            
            'checkPercentageOverride' => ['array'],
            'checkPercentageOverride.*' => ['array'],
            'checkPercentageOverride.*.*' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
                function ($attribute, $value, $fail) {
                    // استخراج tierIndex و periodId از attribute path
                    // مثال: checkPercentageOverride.0.5 → tier=0, period=5
                    preg_match('/checkPercentageOverride\.(\d+)\.(\d+)/', $attribute, $matches);
                    if (count($matches) === 3) {
                        $tierIndex = (int)$matches[1];
                        $periodId = (int)$matches[2];
                        $mode = $this->checkPriceMode[$tierIndex][$periodId] ?? null;
                        
                        // اگر mode='percentage'، درصد الزامی است
                        if ($mode === 'percentage' && ($value === null || $value === '')) {
                            $fail('درصد اختصاصی الزامی است.');
                        }
                    }
                },
            ],
            
            'checkPeriodEnabled' => ['array'],
            'checkPeriodEnabled.*' => ['array'],
            'checkPeriodEnabled.*.*' => ['nullable', 'boolean'],
            
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
            'priceMatrix.*.*.check_periods.*.price' => [
                'nullable',
                'numeric',
                'min:0',
                'max:999999999999',
                function ($attribute, $value, $fail) {
                    // استخراج tierIndex و periodId از attribute path
                    // مثال: priceMatrix.0.2.check_periods.5.price → tier=0, priceType=2, period=5
                    preg_match('/priceMatrix\.(\d+)\.(\d+)\.check_periods\.(\d+)\.price/', $attribute, $matches);
                    if (count($matches) === 4) {
                        $tierIndex = (int)$matches[1];
                        $priceTypeId = (int)$matches[2];
                        $periodId = (int)$matches[3];
                        $mode = $this->checkPriceMode[$tierIndex][$periodId] ?? null;
                        
                        // اگر mode='manual'، قیمت الزامی است
                        if ($mode === 'manual' && ($value === null || $value === '')) {
                            $fail('قیمت دستی الزامی است.');
                        }
                        
                        // بررسی: قیمت چک باید بیشتر یا مساوی قیمت نقدی باشد
                        if ($value !== null && is_numeric($value)) {
                            $cashPriceType = $this->priceTypes->firstWhere('code', 'cash');
                            if ($cashPriceType) {
                                $cashPrice = $this->priceMatrix[$tierIndex][$cashPriceType->id]['price'] ?? null;
                                if ($cashPrice && is_numeric($cashPrice) && $value < $cashPrice) {
                                    $fail('قیمت چک نمی‌تواند کمتر از قیمت نقدی باشد.');
                                }
                            }
                        }
                    }
                },
            ],
        ];
    }
    
    /**
     * پیام‌های خطای سفارشی
     */
    public function messages(): array
    {
        return [
            'sku.regex' => 'SKU فقط می‌تواند شامل حروف انگلیسی، اعداد، خط تیره و زیرخط باشد.',
            
            // قیمت‌گذاری چک
            'checkPriceMode.*.*.in' => 'حالت قیمت‌گذاری نامعتبر است.',
            'checkPercentageOverride.*.*.numeric' => 'درصد اختصاصی باید عدد باشد.',
            'checkPercentageOverride.*.*.min' => 'درصد اختصاصی نمی‌تواند منفی باشد.',
            'checkPercentageOverride.*.*.max' => 'درصد اختصاصی نمی‌تواند بیشتر از 100 باشد.',
            
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
            'priceMatrix.*.*.check_periods.*.price.max' => 'قیمت چک بیش از حد مجاز است.',
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
                            
                            // ✅ بررسی: فقط دوره‌های فعال شده ذخیره شوند
                            $isEnabled = $this->checkPeriodEnabled[$tierIndex][$periodId] ?? false;
                            if (!$isEnabled) {
                                continue; // دوره غیرفعال است، skip
                            }
                            
                            // ✅ تعیین price و percentage_override بر اساس mode
                            $mode = $this->checkPriceMode[$tierIndex][$periodId] ?? 'default';
                            $price = null;
                            $percentageOverride = null;
                            
                            switch ($mode) {
                                case 'default':
                                    // ✅ ذخیره رکورد با price=0 برای نشان دادن فعال بودن دوره
                                    // قیمت واقعی از سلسله مراتب درصد محاسبه می‌شود
                                    $price = 0;
                                    $percentageOverride = null;
                                    break;
                                    
                                case 'percentage':
                                    // ذخیره درصد اختصاصی
                                    $price = 0; // قیمت از درصد محاسبه می‌شود
                                    $percentageOverride = $this->checkPercentageOverride[$tierIndex][$periodId] ?? null;
                                    
                                    // validation: درصد باید بین 0 تا 100 باشد
                                    if ($percentageOverride === null || !is_numeric($percentageOverride) || $percentageOverride < 0 || $percentageOverride > 100) {
                                        continue 2; // skip this record
                                    }
                                    break;
                                    
                                case 'manual':
                                    // ذخیره قیمت دستی
                                    $price = $periodData['price'] ?? null;
                                    $percentageOverride = null;
                                    
                                    // validation: قیمت باید معتبر باشد
                                    if ($price === null || !is_numeric($price) || $price < 0) {
                                        continue 2; // skip this record
                                    }
                                    
                                    $price = (float)$price;
                                    break;
                                    
                                default:
                                    continue 2; // mode نامعتبر
                            }
                            
                            // ✅ ذخیره رکورد برای همه mode‌ها
                            $this->productSupplier->prices()->create([
                                'product_id' => $this->product->id,
                                'price_type_id' => (int)$priceTypeId,
                                'tier_index' => (int)$tierIndex,
                                'check_period_id' => (int)$periodId,
                                'price' => $price,
                                'percentage_override' => $percentageOverride,
                                'is_active' => (bool)$this->is_active,
                            ]);
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

    // ══════════════════════════════════════════════════════════
    //  CHECK PRICE CALCULATION METHODS
    // ══════════════════════════════════════════════════════════

    /**
     * محاسبه قیمت چک بر اساس mode انتخاب شده
     * 
     * @param int $tierIndex
     * @param int $periodId
     * @return float|null
     */
    public function calculateCheckPrice(int $tierIndex, int $periodId): ?float
    {
        // دریافت قیمت نقدی
        $cashPriceType = $this->priceTypes->firstWhere('code', 'cash');
        if (!$cashPriceType) return null;
        
        $cashPrice = $this->priceMatrix[$tierIndex][$cashPriceType->id]['price'] ?? null;
        if (!$cashPrice || !is_numeric($cashPrice)) return null;
        
        $mode = $this->checkPriceMode[$tierIndex][$periodId] ?? 'default';
        
        switch ($mode) {
            case 'default':
                // محاسبه با سلسله مراتب
                $percentage = $this->getEffectivePercentageValue($periodId);
                if ($percentage === null) return null;
                return $cashPrice * (1 + ($percentage / 100));
                
            case 'percentage':
                // محاسبه با درصد اختصاصی
                $percentage = $this->checkPercentageOverride[$tierIndex][$periodId] ?? null;
                if ($percentage === null || !is_numeric($percentage)) return null;
                return $cashPrice * (1 + ($percentage / 100));
                
            case 'manual':
                // قیمت دستی
                $checkPriceType = $this->priceTypes->firstWhere('code', 'check');
                if (!$checkPriceType) return null;
                
                $manualPrice = $this->priceMatrix[$tierIndex][$checkPriceType->id]['check_periods'][$periodId]['price'] ?? null;
                return is_numeric($manualPrice) ? (float)$manualPrice : null;
                
            default:
                return null;
        }
    }

    /**
     * دریافت درصد مؤثر با سلسله مراتب برای یک دوره
     * 
     * @param int $periodId
     * @return array ['value' => float, 'source' => string, 'details' => array]
     */
    public function getEffectivePercentage(int $periodId): array
    {
        $supplierId = Auth::user()->supplier->id;
        
        // 1. بررسی درصد دسته‌بندی
        if ($this->product->category_id) {
            $categoryPercentage = DB::table('category_check_period')
                ->where('category_id', $this->product->category_id)
                ->where('check_period_id', $periodId)
                ->where('supplier_id', $supplierId)
                ->where('is_active', true)
                ->value('percentage_increase');
            
            if ($categoryPercentage !== null) {
                $category = \App\Models\Category::find($this->product->category_id);
                return [
                    'value' => (float)$categoryPercentage,
                    'source' => 'category',
                    'details' => [
                        'category_name' => $category ? $category->name : 'دسته‌بندی',
                        'category_percentage' => (float)$categoryPercentage,
                        'default_percentage' => $this->getDefaultPeriodPercentage($periodId),
                    ],
                ];
            }
        }
        
        // 2. درصد پیش‌فرض دوره چک
        $defaultPercentage = $this->getDefaultPeriodPercentage($periodId);
        
        return [
            'value' => $defaultPercentage,
            'source' => 'default',
            'details' => [
                'default_percentage' => $defaultPercentage,
            ],
        ];
    }

    /**
     * دریافت مقدار درصد مؤثر (فقط عدد)
     */
    private function getEffectivePercentageValue(int $periodId): ?float
    {
        $result = $this->getEffectivePercentage($periodId);
        return $result['value'] ?? null;
    }

    /**
     * دریافت درصد پیش‌فرض یک دوره از جدول supplier_check_period
     */
    private function getDefaultPeriodPercentage(int $periodId): float
    {
        $supplierId = Auth::user()->supplier->id;
        
        $percentage = DB::table('supplier_check_period')
            ->where('supplier_id', $supplierId)
            ->where('check_period_id', $periodId)
            ->where('is_active', true)
            ->value('percentage_increase');
        
        return $percentage !== null ? (float)$percentage : 0.0;
    }

    /**
     * کپی کردن تنظیمات از یک تیراژ به تیراژهای دیگر
     * 
     * @param int $sourceTierIndex
     * @param array $targetTierIndexes
     */
    public function copyTierSettings(int $sourceTierIndex, array $targetTierIndexes = []): void
    {
        // اگر target ها مشخص نشده، به همه تیراژها کپی کن
        if (empty($targetTierIndexes)) {
            $targetTierIndexes = array_keys($this->quantityTiers);
            // حذف خود source از لیست
            $targetTierIndexes = array_filter($targetTierIndexes, fn($i) => $i !== $sourceTierIndex);
        }
        
        foreach ($targetTierIndexes as $targetIndex) {
            if (!isset($this->quantityTiers[$targetIndex])) continue;
            
            // کپی mode ها
            if (isset($this->checkPriceMode[$sourceTierIndex])) {
                $this->checkPriceMode[$targetIndex] = $this->checkPriceMode[$sourceTierIndex];
            }
            
            // کپی درصدهای اختصاصی
            if (isset($this->checkPercentageOverride[$sourceTierIndex])) {
                $this->checkPercentageOverride[$targetIndex] = $this->checkPercentageOverride[$sourceTierIndex];
            }
            
            // کپی وضعیت فعال/غیرفعال دوره‌ها
            if (isset($this->checkPeriodEnabled[$sourceTierIndex])) {
                $this->checkPeriodEnabled[$targetIndex] = $this->checkPeriodEnabled[$sourceTierIndex];
            }
            
            // کپی قیمت‌های دستی چک
            $checkPriceType = $this->priceTypes->firstWhere('code', 'check');
            if ($checkPriceType && isset($this->priceMatrix[$sourceTierIndex][$checkPriceType->id]['check_periods'])) {
                if (!isset($this->priceMatrix[$targetIndex][$checkPriceType->id]['check_periods'])) {
                    $this->priceMatrix[$targetIndex][$checkPriceType->id]['check_periods'] = [];
                }
                
                $this->priceMatrix[$targetIndex][$checkPriceType->id]['check_periods'] = 
                    $this->priceMatrix[$sourceTierIndex][$checkPriceType->id]['check_periods'];
            }
        }
        
        $this->dispatch('notify',
            message: 'تنظیمات با موفقیت کپی شد.',
            type: 'success'
        );
    }

    /**
     * بارگذاری درصدهای مؤثر برای تمام دوره‌ها (برای نمایش)
     */
    public function loadEffectivePercentages(): void
    {
        $this->effectivePercentages = [];
        
        foreach ($this->checkPeriods as $period) {
            $this->effectivePercentages[$period->id] = $this->getEffectivePercentage($period->id);
        }
    }

    public function render()
    {
        return view('livewire.supplier.products.edit-price');
    }
}
