<?php

namespace App\Livewire\Supplier\Products;

use App\Models\Product;
use App\Models\ProductSupplier;
use App\Models\PriceType;
use App\Models\CheckPeriod;
use App\Models\SupplierExchangeRateSetting;
use App\Models\Supplier;
use App\Models\ProductPrice;
use App\Models\User;
use App\Services\SupplierBranchPriceSyncService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.layouts.supplier')]
class EditPrice extends Component
{
    public Product $product;
    public ?ProductSupplier $productSupplier;
    public int $foreignPriceRevision = 0;
    public string $priceMatrixFingerprint = '';
    public ?string $foreignPriceRevisionToken = null;
    public bool $isBranchCopy = false;
    public string $branchPricePercentage = '0.00';
    public ?int $branchReferencePrice = null;

    // Properties for the form
    public array $priceMatrix = []; // ساختار: [tier_index][price_type_id] = ['price' => ..., 'warranty_*' => ...]
    public ?string $sku = '';
    public bool $is_active = false;

    // واحد مبلغ نقدی تیراژ مرجع و تنظیم به‌روزرسانی نرخ ارز
    #[Locked]
    public string $priceCurrency = 'TOM';
    #[Locked]
    public int $pricingCurrencyRevision = 1;
    #[Locked]
    public int $tierPricingRevision = 1;
    public ?string $foreignPriceAmount = null;
    public bool $foreignPriceRebasePending = false;
    public bool $foreignPricePreviewValid = false;
    public ?string $latestExchangeRate = null;
    public ?string $latestExchangeFetchedAt = null;
    public ?string $latestExchangeSource = null;
    public ?string $latestExchangeError = null;
    private bool $usingStoredRateFallback = false;
    public ?string $foreignPriceUpdatedAt = null;
    public ?string $foreignPriceLastError = null;
    
    // ✅ قیمت‌گذاری چک
    public array $checkPriceMode = []; // [tier_index][period_id] = 'default' | 'percentage' | 'manual'
    public array $checkPercentageOverride = []; // [tier_index][period_id] = float (درصد اختصاصی)
    public array $checkPeriodEnabled = []; // [tier_index][period_id] = true/false (فعال/غیرفعال)
    public array $effectivePercentages = []; // [period_id] = ['value' => float, 'source' => string]
    protected array $effectivePercentageCache = [];
    protected bool $effectivePercentageCacheLoaded = false;
    protected array $effectiveTierPercentageCache = [];
    protected array $effectiveTierPercentageCacheLoaded = [];
    
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
    
    // ✅ محاسبه خودکار تیراژها
    #[Locked]
    public int $referenceTierIndex = 1; // تیراژ مرجع (1 = کارتن)
    public array $tierPercentages = []; // [tier_index] => percentage (+/- درصد نسبت به مرجع)
    public array $tierPriceMode = []; // [tier_index] => 'default' | 'percentage' (مشابه checkPriceMode)
    public bool $autoCalculateTiers = false; // فعال/غیرفعال محاسبه خودکار
    public array $tierEnabled = []; // [tier_index] => فعال/غیرفعال بودن فروش؛ مستقل از مرجع محاسباتی

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

    public function updatedTierEnabled($value, $key): void
    {
        $tierIndex = (int) $key;
        if (!array_key_exists($tierIndex, $this->quantityTiers)) {
            return;
        }

        // تغییر وضعیت فروش تیراژ نباید مرجع را حذف کند؛ پیش‌نمایش درصدها را تازه کن.
        if ($this->priceCurrency !== 'TOM' || $this->autoCalculateTiers) {
            $this->calculateTierPrices(forceCurrencyDerived: $this->priceCurrency !== 'TOM');
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
        $supplier = Auth::user()->currentSupplier();
        $supplierId = $supplier->id;

        if ($supplier->isBranch()) {
            $this->product = Product::query()
                ->whereKey($product->id)
                ->where(function ($query) use ($supplierId): void {
                    $query->where('supplier_id', $supplierId)
                        ->orWhereHas('productSuppliers', fn ($offers) => $offers->where('supplier_id', $supplierId));
                })
                ->firstOrFail();

            $this->productSupplier = ProductSupplier::query()
                ->where('product_id', $this->product->id)
                ->where('supplier_id', $supplierId)
                ->first();

            if (!$this->productSupplier && (int) $this->product->supplier_id === (int) $supplierId) {
                $this->productSupplier = ProductSupplier::create([
                    'product_id' => $this->product->id,
                    'supplier_id' => $supplierId,
                    'is_active' => false,
                ]);
            }

            abort_unless($this->productSupplier, 403, 'این محصول در شعبهٔ جاری ثبت نشده است.');
        } else {
            $this->product = $product;
            $this->productSupplier = ProductSupplier::withTrashed()
                ->where('product_id', $this->product->id)
                ->where('supplier_id', $supplierId)
                ->first();

            $isAlreadyOwned = (int) $this->product->supplier_id === (int) $supplierId;
            $hasActiveOffer = $this->productSupplier && !$this->productSupplier->trashed();
            if (!$isAlreadyOwned && !$hasActiveOffer) {
                abort_unless(
                    Product::query()
                        ->canonical()
                        ->where('status', \App\Enums\ProductStatus::ACTIVE->value)
                        ->whereKey($this->product->id)
                        ->exists(),
                    404
                );
            }

            if ($this->productSupplier?->trashed()) {
                $this->productSupplier = $this->restoreProductSupplierDraft($this->productSupplier);
            } elseif (!$this->productSupplier) {
                $this->productSupplier = ProductSupplier::withTrashed()->firstOrCreate(
                    ['product_id' => $this->product->id, 'supplier_id' => $supplierId],
                    ['is_active' => false]
                );

                if ($this->productSupplier->trashed()) {
                    $this->productSupplier = $this->restoreProductSupplierDraft($this->productSupplier);
                }
            }
        }

        // Load data
        $this->sku = $this->productSupplier->sku;
        $this->is_active = $this->productSupplier->is_active;
        $supplierPricing = Supplier::query()->findOrFail($supplierId);
        $this->priceCurrency = $supplierPricing->pricing_currency ?? 'TOM';
        $this->pricingCurrencyRevision = (int) $supplierPricing->pricing_currency_revision;
        $this->tierPricingRevision = (int) $supplierPricing->tier_pricing_revision;
        $this->foreignPriceAmount = $this->productSupplier->price_currency !== $this->priceCurrency
            || $this->productSupplier->foreign_price_amount === null
                ? null
                : rtrim(rtrim(number_format((float) $this->productSupplier->foreign_price_amount, 4, '.', ''), '0'), '.');
        $this->foreignPriceUpdatedAt = $this->productSupplier->foreign_price_updated_at?->timezone('Asia/Tehran')->format('Y/m/d H:i');
        $this->foreignPriceLastError = $this->productSupplier->foreign_price_last_error;
        $this->loadLatestExchangeRate();
        
        // ✅ بارگذاری تنظیمات گارانتی
        $this->warrantyType = $this->productSupplier->warranty_type ?? 'none';
        $this->warrantyValue = $this->productSupplier->warranty_value;
        $this->hasWarranty = $this->productSupplier->has_warranty ?? false;
        $this->warrantyProvider = $this->productSupplier->warranty_provider ?? 'none';
        $this->warrantyCoverageType = $this->productSupplier->warranty_coverage_type ?? 'full';
        $this->warrantyTypeCoverage = $this->productSupplier->warranty_type_coverage ?? 'limited'; // ✅
        $this->warrantyDurationDays = $this->productSupplier->warranty_duration_days;

        $this->isBranchCopy = $supplier->isBranch()
            && $this->product->family_product_id !== null
            && (int) $this->product->family_product_id !== (int) $this->product->id;

        if ($this->isBranchCopy) {
            $this->branchPricePercentage = rtrim(rtrim(number_format(
                (float) ($this->productSupplier->branch_price_percentage ?? 0),
                12,
                '.',
                ''
            ), '0'), '.');
            $this->branchReferencePrice = app(SupplierBranchPriceSyncService::class)
                ->primaryReferencePrice($this->product, $supplier);

            return;
        }
        
        // تیراژهای محصول
        $this->quantityTiers = $this->product->quantity_tiers ?? [];
        
        // ✅ مقداردهی اولیه tierEnabled (همه تیراژها فعال)
        foreach ($this->quantityTiers as $tierIndex => $tier) {
            $this->tierEnabled[$tierIndex] = true;
        }
        
        // ✅ بارگذاری تنظیمات تیراژ از جدول (اگر وجود داشته باشد)
        $this->loadTierPercentages();
        
        // ✅ بارگذاری تنظیمات tierEnabled از دیتابیس
        $this->autoCalculateTiers = $this->productSupplier->auto_calculate_tiers ?? false;
        if (!empty($this->productSupplier->tier_enabled)) {
            $this->tierEnabled = $this->productSupplier->tier_enabled;
        }
        
        // ✅ فقط در صورتی که product_supplier دارای tier_percentages باشد، آن را overwrite کن
        // اگر null باشد، از مقادیر بارگذاری شده در loadTierPercentages استفاده کن
        if (!empty($this->productSupplier->tier_percentages) && is_array($this->productSupplier->tier_percentages)) {
            $this->tierPercentages = $this->productSupplier->tier_percentages;
        }
        
        // بارگذاری انواع قیمت و دوره‌های چک
        $this->priceTypes = PriceType::where('is_active', true)->orderBy('sort_order')->get();
        
        // ✅ دوره‌های چک: فقط دوره‌های فعال شده توسط supplier (از جدول pivot)
        $this->checkPeriods = CheckPeriod::whereHas('suppliers', function($query) use ($supplierId) {
                $query->where('supplier_id', $supplierId)
                      ->where('supplier_check_period.is_active', true);
            })
            ->active()
            ->orderBy('days')
            ->get();

        $this->loadEffectivePercentages();
        
        $this->initializePriceMatrix();
        $cashPriceType = $this->priceTypes->firstWhere('code', 'cash');
        $this->foreignPriceRebasePending = $cashPriceType !== null
            && $this->productSupplier->price_currency !== $this->priceCurrency
            && is_numeric($this->priceMatrix[$this->referenceTierIndex][$cashPriceType->id]['price'] ?? null);
        $this->foreignPricePreviewValid = $this->priceCurrency !== 'TOM'
            && $this->foreignPriceAmount !== null
            && $cashPriceType !== null
            && is_numeric($this->priceMatrix[$this->referenceTierIndex][$cashPriceType->id]['price'] ?? null);
        $this->refreshForeignPriceRevisionToken();
    }

    /**
     * مقداردهی اولیه ماتریس قیمت
     */
    protected function initializePriceMatrix(): void
    {
        // بارگذاری قیمت‌های موجود
        $existingPriceRows = $this->productSupplier->prices()
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get()
            ->values();
        $this->priceMatrixFingerprint = $this->fingerprintPriceRows($existingPriceRows->sortBy('id')->values());
        $existingPrices = $existingPriceRows->whereNotNull('price_type_id')->groupBy(function($price) {
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
                        
                        // ✅ پیش‌فرض: همه دوره‌ها غیرفعال (فقط اگر رکورد وجود داشته باشد فعال می‌شوند)
                        if ($checkPrice) {
                            // دوره فعال است (چون رکورد وجود دارد)
                            $this->checkPeriodEnabled[$tierIndex][$period->id] = true;
                            
                            if ($checkPrice->percentage_override !== null) {
                                // mode: percentage (درصد اختصاصی وجود دارد)
                                $this->checkPriceMode[$tierIndex][$period->id] = 'percentage';
                                $this->checkPercentageOverride[$tierIndex][$period->id] = (float)$checkPrice->percentage_override;
                                // قیمت از دیتابیس خوانده می‌شود (محاسبه شده ذخیره شده است)
                                $this->priceMatrix[$tierIndex][$key]['check_periods'][$period->id]['price'] = $checkPrice->price;
                            } elseif ($checkPrice->calculation_mode === 'percentage') {
                                $this->checkPriceMode[$tierIndex][$period->id] = 'manual';
                                $this->priceMatrix[$tierIndex][$key]['check_periods'][$period->id]['price'] = $checkPrice->price;
                            } elseif (in_array($checkPrice->calculation_mode, ['default', 'manual'], true)) {
                                $this->checkPriceMode[$tierIndex][$period->id] = $checkPrice->calculation_mode;
                                $this->priceMatrix[$tierIndex][$key]['check_periods'][$period->id]['price'] = $checkPrice->price;
                            } elseif ($checkPrice->price !== null && $checkPrice->price > 0) {
                                // ✅ بررسی: آیا این قیمت manual است یا default؟
                                // برای تشخیص، قیمت را با محاسبه default مقایسه می‌کنیم
                                
                                // محاسبه قیمت پیش‌فرض برای مقایسه
                                $cashPriceType = $this->priceTypes->firstWhere('code', 'cash');
                                $cashPrice = $existingPrices->get($tierIndex . '_' . $cashPriceType->id . '_null')?->first()?->price;
                                
                                if ($cashPrice && is_numeric($cashPrice)) {
                                    // ✅ محاسبه قیمت default با در نظر گرفتن rounding
                                    $percentage = $this->getEffectivePercentageValue($period->id);
                                    
                                    if ($percentage !== null) {
                                        // ✅ استفاده از Helper برای محاسبه و rounding
                                        $supplier = Auth::user()->currentSupplier();
                                        $expectedDefaultPrice = \App\Helpers\PriceRoundingHelper::calculateCheckPrice(
                                            $cashPrice,
                                            $percentage,
                                            $supplier // ✅ Model پاس می‌دهیم، نه string
                                        );
                                        
                                        // مقایسه با tolerance 0.01 تومان (به دلیل round-off errors)
                                        if ($expectedDefaultPrice !== null && abs($checkPrice->price - $expectedDefaultPrice) < 0.01) {
                                            // قیمت برابر با default است → mode = default
                                            $this->checkPriceMode[$tierIndex][$period->id] = 'default';
                                            $this->priceMatrix[$tierIndex][$key]['check_periods'][$period->id]['price'] = $checkPrice->price;
                                        } else {
                                            // قیمت متفاوت است → mode = manual
                                            $this->checkPriceMode[$tierIndex][$period->id] = 'manual';
                                            $this->priceMatrix[$tierIndex][$key]['check_periods'][$period->id]['price'] = $checkPrice->price;
                                        }
                                    } else {
                                        // درصد پیدا نشد → فرض می\u200cکنیم manual است
                                        $this->checkPriceMode[$tierIndex][$period->id] = 'manual';
                                        $this->priceMatrix[$tierIndex][$key]['check_periods'][$period->id]['price'] = $checkPrice->price;
                                    }
                                } else {
                                    // نمی‌توانیم محاسبه کنیم، فرض می‌کنیم manual است
                                    $this->checkPriceMode[$tierIndex][$period->id] = 'manual';
                                    $this->priceMatrix[$tierIndex][$key]['check_periods'][$period->id]['price'] = $checkPrice->price;
                                }
                            } else {
                                // price = 0 یا null → نباید اتفاق بیفتد با logic جدید، اما برای backward compatibility
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

    private function restoreProductSupplierDraft(ProductSupplier $productSupplier): ProductSupplier
    {
        $productSupplierId = $productSupplier->getKey();

        return DB::transaction(function () use ($productSupplierId): ProductSupplier {
            $productSupplier = ProductSupplier::withTrashed()
                ->whereKey($productSupplierId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($productSupplier->trashed()) {
                $productSupplier->forceFill(['is_active' => false]);
                $productSupplier->restore();
            }

            return $productSupplier;
        });
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
            'priceCurrency' => ['required', 'string', 'in:TOM,USD,CNY,AED'],
            'foreignPriceAmount' => [
                'nullable',
                'numeric',
                'gt:0',
                'max:9999999999999999',
                function ($attribute, $value, $fail) {
                    if ($this->priceCurrency !== 'TOM' && ($value === null || $value === '')) {
                        $fail('مبلغ محصول به ارز انتخاب‌شده را وارد کنید.');
                        return;
                    }

                    if ($value !== null
                        && $value !== ''
                        && !preg_match('/^\d+(?:\.\d{1,2})?$/', (string) $value)
                        && !$this->isUnchangedStoredForeignPrice()) {
                        $fail('مبلغ جدید باید حداکثر دو رقم اعشار داشته باشد.');
                    }
                },
            ],
            
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
        $supplier = Auth::user()->currentSupplier();
        $this->resolveOwnedProductAndOffer($supplier);
        $supplierId = $this->authorizePriceWrite();
        abort_unless($supplierId === (int) $supplier->id, 403, 'شما مجاز به ویرایش این محصول نیستید.');

        $this->isBranchCopy = $supplier->isBranch()
            && $this->product->family_product_id !== null
            && (int) $this->product->family_product_id !== (int) $this->product->id;
        if ($this->isBranchCopy) {
            return $this->saveBranchPricing($supplier);
        }

        $this->normalizeForeignPriceAmount();
        // امنیت: بررسی دسترسی تامین‌کننده به این محصول
        abort_unless((int) $this->productSupplier->supplier_id === $supplierId, 403, 'شما مجاز به ویرایش این محصول نیستید.');

        $saved = ProductPrice::withoutEvents(function () use ($supplierId): bool {
            return DB::transaction(function () use ($supplierId): bool {
            return app(\App\Services\CatalogSnapshotRevisionService::class)->batch(function () use ($supplierId): bool {
            $lockedSupplier = Supplier::query()->lockForUpdate()->findOrFail($supplierId);
            if ($lockedSupplier->pricing_currency !== $this->priceCurrency
                || (int) $lockedSupplier->pricing_currency_revision !== $this->pricingCurrencyRevision) {
                $this->addError('priceCurrency', 'ارز پایه هنگام بازبودن فرم تغییر کرده است. صفحه را تازه کنید تا واحد جدید بارگذاری شود.');
                return false;
            }

            if ((int) $lockedSupplier->tier_pricing_revision !== $this->tierPricingRevision) {
                $this->addError('priceMatrix', 'درصدهای سراسری تیراژ هنگام بازبودن فرم تغییر کرده‌اند. صفحه را تازه کنید تا قیمت‌ها با تنظیم جدید محاسبه شوند.');
                return false;
            }

            $lockedProductSupplier = ProductSupplier::query()
                ->with('product')
                ->where('supplier_id', $supplierId)
                ->lockForUpdate()
                ->findOrFail($this->productSupplier->getKey());

            abort_unless((int) $lockedProductSupplier->supplier_id === $supplierId, 403, 'شما مجاز به ویرایش این محصول نیستید.');

            if (!$this->matchesForeignPriceRevision($lockedProductSupplier)) {
                $this->addError('priceMatrix', 'قیمت این محصول هنگام بازبودن فرم به‌روزرسانی شده است. صفحه را تازه کنید و تغییرها را دوباره اعمال کنید.');
                return false;
            }

            $this->productSupplier = $lockedProductSupplier;

            $lockedExchangeRateSetting = null;
            if ($this->priceCurrency !== 'TOM') {
                $lockedExchangeRateSetting = SupplierExchangeRateSetting::query()
                    ->where('supplier_id', $supplierId)
                    ->where('currency', $this->priceCurrency)
                    ->lockForUpdate()
                    ->first();
            }

            if (!$this->syncForeignPriceToToman(requireAmount: true, rateOverride: $lockedExchangeRateSetting)) {
                return false;
            }

            if ($this->priceCurrency !== 'TOM') {
                $this->calculateTierPrices(forceCurrencyDerived: true);
            }

            $this->validate();

            // 1. بروزرسانی اطلاعات تامین‌کننده
            // ✅ محاسبه tier_percentages: فقط تیراژهایی که mode='percentage' دارند
            $tierPercentagesToSave = [];
            foreach ($this->tierPercentages as $tierIndex => $percentage) {
                $mode = $this->tierPriceMode[$tierIndex] ?? 'default';
                if ($mode === 'percentage') {
                    // فقط درصدهای اختصاصی ذخیره شوند
                    $tierPercentagesToSave[$tierIndex] = $percentage;
                }
            }
            
            $this->productSupplier->update([
                'sku' => $this->sanitizeInput($this->sku),
                'is_active' => (bool)$this->is_active,
                'price_currency' => $this->priceCurrency,
                'foreign_price_amount' => $this->foreignPriceAmountForStorage(),
                // Automatic refresh policy is stored once per supplier and currency.
                'foreign_price_auto_update' => false,
                'foreign_price_refresh_hours' => null,
                'foreign_price_rate' => $this->priceCurrency === 'TOM' ? null : $this->latestExchangeRate,
                'foreign_price_source' => $this->priceCurrency === 'TOM' ? null : $this->latestExchangeSource,
                'foreign_price_applied_currency' => $this->priceCurrency !== 'TOM' && !$this->usingStoredRateFallback
                    ? $this->priceCurrency
                    : null,
                'foreign_price_applied_setting_revision' => $this->priceCurrency !== 'TOM' && !$this->usingStoredRateFallback
                    ? (int) ($lockedExchangeRateSetting?->revision ?? 0)
                    : 0,
                'foreign_price_applied_tier_revision' => $this->priceCurrency !== 'TOM' && !$this->usingStoredRateFallback
                    ? (int) $lockedSupplier->tier_pricing_revision
                    : null,
                'foreign_price_updated_at' => $this->priceCurrency === 'TOM'
                    ? null
                    : ($this->usingStoredRateFallback ? $this->productSupplier->foreign_price_updated_at : now()),
                'foreign_price_next_update_at' => null,
                'foreign_price_last_attempt_at' => $this->priceCurrency === 'TOM'
                    ? null
                    : ($this->usingStoredRateFallback ? $this->productSupplier->foreign_price_last_attempt_at : now()),
                'foreign_price_last_error' => $this->priceCurrency === 'TOM'
                    ? null
                    : ($this->usingStoredRateFallback ? $this->productSupplier->foreign_price_last_error : null),
                'foreign_price_revision' => ((int) $this->productSupplier->foreign_price_revision) + 1,
                'warranty_type' => $this->warrantyType,
                'warranty_value' => $this->warrantyValue,
                'has_warranty' => $this->hasWarranty,
                'warranty_provider' => $this->warrantyProvider,
                'warranty_coverage_type' => $this->warrantyCoverageType,
                'warranty_type_coverage' => $this->warrantyTypeCoverage,     // ✅ جدید
                'warranty_duration_days' => $this->warrantyDurationDays,
                // ✅ ذخیره تنظیمات تیراژها
                'auto_calculate_tiers' => (bool)$this->autoCalculateTiers,
                'tier_percentages' => empty($tierPercentagesToSave) ? null : $tierPercentagesToSave,
                'tier_enabled' => $this->tierEnabled,
            ]);

            // ✅ 1.5. ذخیره تنظیمات تیراژ
            $this->saveTierPercentages();

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
                                    // ✅ محاسبه و ذخیره قیمت نهایی بر اساس سلسله مراتب درصد
                                    $calculatedPrice = $this->calculateCheckPrice($tierIndex, $periodId);
                                    
                                    // اگر محاسبه موفق نبود، از ذخیره صرف‌نظر کن
                                    if ($calculatedPrice === null || $calculatedPrice <= 0) {
                                        continue 2; // skip this record
                                    }
                                    
                                    $price = $calculatedPrice;
                                    $percentageOverride = null;
                                    break;
                                    
                                case 'percentage':
                                    // ✅ محاسبه و ذخیره قیمت نهایی بر اساس درصد اختصاصی
                                    $percentageOverride = $this->checkPercentageOverride[$tierIndex][$periodId] ?? null;
                                    
                                    // validation: درصد باید بین 0 تا 100 باشد
                                    if ($percentageOverride === null || !is_numeric($percentageOverride) || $percentageOverride < 0 || $percentageOverride > 100) {
                                        continue 2; // skip this record
                                    }
                                    
                                    $calculatedPrice = $this->calculateCheckPrice($tierIndex, $periodId);
                                    
                                    // اگر محاسبه موفق نبود، از ذخیره صرف‌نظر کن
                                    if ($calculatedPrice === null || $calculatedPrice <= 0) {
                                        continue 2; // skip this record
                                    }
                                    
                                    $price = $calculatedPrice;
                                    break;
                                    
                                case 'manual':
                                    // ذخیره قیمت دستی (بدون تغییر)
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
                                'calculation_mode' => $mode,
                                'is_active' => (bool)$this->is_active,
                            ]);
                        }
                    }
                }
            }
            app(\App\Services\ProductPriceRevisionService::class)
                ->increment((int) $lockedProductSupplier->product_id, (int) $lockedProductSupplier->supplier_id);

            $matrixPrices = ProductPrice::query()
                ->where('product_id', $lockedProductSupplier->product_id)
                ->where('product_supplier_id', $lockedProductSupplier->id)
                ->get();
            app(SupplierBranchPriceSyncService::class)->syncPrimaryMatrix(
                $lockedProductSupplier->product->refresh(),
                $lockedSupplier,
                $matrixPrices,
            );

            return true;
            });
        }, 3);
        });

        if (!$saved) {
            return;
        }

        $this->productSupplier->refresh();
        $this->foreignPriceRebasePending = false;
        // ✅ بارگذاری مجدد قیمت‌های ذخیره شده
        $this->initializePriceMatrix();
        $this->refreshForeignPriceRevisionToken();

        \App\Jobs\BroadcastSupplierProductPrices::dispatch(
            (int) $this->productSupplier->supplier_id,
            [(int) $this->productSupplier->id],
        );
        $this->foreignPriceUpdatedAt = $this->priceCurrency === 'TOM'
            ? null
            : ($this->usingStoredRateFallback
                ? $this->productSupplier->foreign_price_updated_at?->timezone('Asia/Tehran')->format('Y/m/d H:i')
                : now()->timezone('Asia/Tehran')->format('Y/m/d H:i'));
        $this->foreignPriceLastError = $this->usingStoredRateFallback
            ? $this->productSupplier->foreign_price_last_error
            : null;

        $this->dispatch('notify',
            message: 'ماتریس قیمت با موفقیت ذخیره شد.',
            type: 'success'
        );
        
        session()->flash('message', 'ماتریس قیمت با موفقیت ذخیره شد.');
    }

    private function saveBranchPricing(\App\Models\Supplier $supplier): void
    {
        $this->validate([
            'branchPricePercentage' => 'required|numeric|decimal:0,12|between:-99.999999,999.999999',
            'warrantyType' => 'required|in:none,percentage,fixed,custom',
            'warrantyValue' => 'nullable|numeric|min:0|max:1000000000',
            'hasWarranty' => 'boolean',
            'warrantyProvider' => 'required|in:none,manufacturer,seller',
            'warrantyCoverageType' => 'required|in:full,limited,parts_only,service_only',
            'warrantyTypeCoverage' => 'required|in:test,limited,lifetime',
            'warrantyDurationDays' => 'nullable|integer|min:0|max:36500',
        ]);

        $percentage = round((float) $this->branchPricePercentage, 12, PHP_ROUND_HALF_UP);
        DB::transaction(function () use ($supplier, $percentage): void {
            // Match primary-promotion lock order (primary, then branch) to avoid
            // lock inversion when a branch is promoted during a price edit.
            $lockedPrimary = $supplier->parent_supplier_id !== null
                ? Supplier::query()->lockForUpdate()->find($supplier->parent_supplier_id)
                : null;
            $lockedBranch = Supplier::query()->lockForUpdate()->findOrFail($supplier->id);
            abort_unless(
                $lockedPrimary
                    && (int) $lockedBranch->parent_supplier_id === (int) $lockedPrimary->id,
                403,
                'ساختار شعبه هنگام ثبت قیمت تغییر کرده است.'
            );
            $this->assertPriceWriteContext(Auth::user(), $lockedBranch, $lockedPrimary, true);

            $lockedProduct = Product::query()->lockForUpdate()->findOrFail($this->product->id);
            abort_unless(
                $lockedBranch->isBranch()
                    && (int) $lockedProduct->supplier_id === (int) $lockedBranch->id
                    && $lockedProduct->family_product_id !== null
                    && (int) $lockedProduct->family_product_id !== (int) $lockedProduct->id,
                403,
                'این محصول دیگر نسخهٔ قابل‌ویرایش شعبه نیست.'
            );

            $lockedOffer = ProductSupplier::query()
                ->whereKey($this->productSupplier->getKey())
                ->where('product_id', $lockedProduct->id)
                ->where('supplier_id', $lockedBranch->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedOffer->update([
                'branch_price_percentage' => $percentage,
                'warranty_type' => $this->warrantyType,
                'warranty_value' => $this->warrantyValue,
                'has_warranty' => $this->hasWarranty,
                'warranty_provider' => $this->warrantyProvider,
                'warranty_coverage_type' => $this->warrantyCoverageType,
                'warranty_type_coverage' => $this->warrantyTypeCoverage,
                'warranty_duration_days' => $this->warrantyDurationDays,
            ]);

            app(SupplierBranchPriceSyncService::class)
                ->syncBranchProduct($lockedProduct, $lockedBranch, $percentage);
        });

        $this->branchReferencePrice = app(SupplierBranchPriceSyncService::class)
            ->primaryReferencePrice($this->product, $supplier);
        unset($this->branchCalculatedPrice);
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'درصد قیمت شعبه ذخیره شد و قیمت‌های آن بر اساس شعبهٔ اصلی به‌روز شدند.',
        ]);
    }

    #[Computed]
    public function branchCalculatedPrice(): ?int
    {
        if ($this->branchReferencePrice === null || !is_numeric($this->branchPricePercentage)) {
            return null;
        }

        return max(0, (int) round(
            $this->branchReferencePrice * (1 + ((float) $this->branchPricePercentage / 100)),
            0,
            PHP_ROUND_HALF_UP
        ));
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
        
        // دریافت supplier برای rounding
        $supplier = Auth::user()->currentSupplier();
        
        switch ($mode) {
            case 'default':
                // محاسبه با سلسله مراتب
                $percentage = $this->getEffectivePercentageValue($periodId);
                if ($percentage === null) return null;
                
                // ✅ استفاده از Helper برای محاسبه و رُند
                return \App\Helpers\PriceRoundingHelper::calculateCheckPrice(
                    $cashPrice, 
                    $percentage, 
                    $supplier
                );
                
            case 'percentage':
                // محاسبه با درصد اختصاصی
                $percentage = $this->checkPercentageOverride[$tierIndex][$periodId] ?? null;
                if ($percentage === null || !is_numeric($percentage)) return null;
                
                // ✅ استفاده از Helper برای محاسبه و رُند
                return \App\Helpers\PriceRoundingHelper::calculateCheckPrice(
                    $cashPrice, 
                    $percentage, 
                    $supplier
                );
                
            case 'manual':
                // قیمت دستی - بدون تغییر
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
        $this->primeEffectivePercentageCache();

        return $this->effectivePercentageCache[$periodId]
            ?? $this->resolveEffectivePercentage($periodId);
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
        return (float) ($this->getEffectivePercentage($periodId)['details']['default_percentage'] ?? 0);
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
        $this->primeEffectivePercentageCache();
        $this->effectivePercentages = $this->effectivePercentageCache;
    }

    private function primeEffectivePercentageCache(): void
    {
        if ($this->effectivePercentageCacheLoaded) {
            return;
        }

        $this->effectivePercentageCacheLoaded = true;
        $periodIds = collect($this->checkPeriods ?? [])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($periodIds === []) {
            return;
        }

        $supplierId = (int) Auth::user()->currentSupplier()->id;
        $defaultPercentages = DB::table('supplier_check_period')
            ->where('supplier_id', $supplierId)
            ->whereIn('check_period_id', $periodIds)
            ->where('is_active', true)
            ->pluck('percentage_increase', 'check_period_id')
            ->all();
        $categoryId = $this->product->category_id;
        $categoryPercentages = $categoryId
            ? DB::table('category_check_period')
                ->where('supplier_id', $supplierId)
                ->where('category_id', $categoryId)
                ->whereIn('check_period_id', $periodIds)
                ->where('is_active', true)
                ->pluck('percentage_increase', 'check_period_id')
                ->all()
            : [];
        $categoryName = $categoryPercentages !== []
            ? ($this->product->category?->name ?? 'دسته‌بندی')
            : null;

        foreach ($periodIds as $periodId) {
            $defaultPercentage = (float) ($defaultPercentages[$periodId] ?? 0);

            $this->effectivePercentageCache[$periodId] = array_key_exists($periodId, $categoryPercentages)
                ? [
                    'value' => (float) $categoryPercentages[$periodId],
                    'source' => 'category',
                    'details' => [
                        'category_name' => $categoryName,
                        'category_percentage' => (float) $categoryPercentages[$periodId],
                        'default_percentage' => $defaultPercentage,
                    ],
                ]
                : [
                    'value' => $defaultPercentage,
                    'source' => 'default',
                    'details' => ['default_percentage' => $defaultPercentage],
                ];
        }
    }

    private function resolveEffectivePercentage(int $periodId): array
    {
        $supplierId = (int) Auth::user()->currentSupplier()->id;
        $defaultPercentage = (float) (DB::table('supplier_check_period')
            ->where('supplier_id', $supplierId)
            ->where('check_period_id', $periodId)
            ->where('is_active', true)
            ->value('percentage_increase') ?? 0);
        $categoryPercentage = $this->product->category_id
            ? DB::table('category_check_period')
                ->where('supplier_id', $supplierId)
                ->where('category_id', $this->product->category_id)
                ->where('check_period_id', $periodId)
                ->where('is_active', true)
                ->value('percentage_increase')
            : null;

        $result = $categoryPercentage === null
            ? ['value' => $defaultPercentage, 'source' => 'default', 'details' => ['default_percentage' => $defaultPercentage]]
            : [
                'value' => (float) $categoryPercentage,
                'source' => 'category',
                'details' => [
                    'category_name' => $this->product->category?->name ?? 'دسته‌بندی',
                    'category_percentage' => (float) $categoryPercentage,
                    'default_percentage' => $defaultPercentage,
                ],
            ];

        $this->effectivePercentageCache[$periodId] = $result;

        return $result;
    }

    // ══════════════════════════════════════════════════════════
    //  TIER AUTO-CALCULATION METHODS
    // ══════════════════════════════════════════════════════════

    /**
     * بارگذاری تنظیمات درصد تیراژها
     */
    public function loadTierPercentages(): void
    {
        $supplierId = Auth::user()->currentSupplier()->id;
        $categoryId = $this->product->category_id ?? null;
        
        // بارگذاری از جدول supplier_tier_percentages (اگر وجود داشته باشد)
        $savedSettings = \App\Models\SupplierTierPercentage::forSupplier($supplierId)
            ->where('product_id', $this->product->id)
            ->active()
            ->ordered()
            ->get()
            ->keyBy('tier_index');
        
        $productOverrides = is_array($this->productSupplier->tier_percentages)
            ? $this->productSupplier->tier_percentages
            : [];

        // The product_supplier JSON is the explicit override source. Do not
        // infer override mode by comparing old snapshots with today's defaults:
        // changing a supplier/category default would otherwise pin the old value
        // to this product on its next save.
        $this->referenceTierIndex = 1;
        foreach ($this->quantityTiers as $index => $tier) {
            $index = (int) $index;

            if (array_key_exists($index, $productOverrides) || array_key_exists((string) $index, $productOverrides)) {
                $override = array_key_exists($index, $productOverrides)
                    ? $productOverrides[$index]
                    : $productOverrides[(string) $index];
                $this->tierPercentages[$index] = (float) $override;
                $this->tierPriceMode[$index] = 'percentage';
            } else {
                $this->tierPercentages[$index] = $this->getEffectiveTierPercentage($supplierId, $categoryId, $index);
                $this->tierPriceMode[$index] = 'default';
            }
        }

        $this->autoCalculateTiers = $savedSettings->isNotEmpty();
        
        // ✅ تیراژ مرجع همیشه 0% است
        $this->tierPercentages[$this->referenceTierIndex] = 0;
    }

    /**
     * بارگذاری درصدهای پیشفرض از تنظیمات تامین‌کننده
     */
    public function loadDefaultTierPercentages(): void
    {
        $supplierId = Auth::user()->currentSupplier()->id;
        
        // بارگذاری تنظیمات پیشفرض
        $defaultTierSettings = \App\Models\SupplierTierDiscount::getAllForSupplier($supplierId);
        
        if ($defaultTierSettings->isEmpty()) {
            $this->dispatch('notify', 
                message: 'هیچ تنظیمات پیشفرضی یافت نشد. لطفاً ابتدا در بخش تنظیمات، درصدهای پیشفرض را تعیین کنید.',
                type: 'warning'
            );
            return;
        }
        
        // بارگذاری درصدها
        foreach ($this->quantityTiers as $index => $tier) {
            $defaultSetting = $defaultTierSettings->firstWhere('tier_index', $index);
            $this->tierPercentages[$index] = $defaultSetting ? (float)$defaultSetting->percentage_change : 0;
            $this->tierPriceMode[$index] = 'default'; // ✅ تنظیم mode به پیشفرض
        }
        
        // ✅ تیراژ مرجع همیشه 0% است
        $this->tierPercentages[$this->referenceTierIndex] = 0;
        
        $this->dispatch('notify', 
            message: '✅ درصدهای پیشفرض با موفقیت بارگذاری شدند.',
            type: 'success'
        );
    }

    /**
     * ذخیره تنظیمات درصد تیراژها
     */
    public function saveTierPercentages(): void
    {
        $supplier = Auth::user()->currentSupplier();
        $this->resolveOwnedProductAndOffer($supplier);
        $this->isBranchCopy = $supplier->isBranch()
            && $this->product->family_product_id !== null
            && (int) $this->product->family_product_id !== (int) $this->product->id;
        abort_unless(!$this->isBranchCopy, 403, 'تنظیم درصد تیراژ برای کالای کپی‌شدهٔ شعبه در دسترس نیست.');

        $supplierId = $this->authorizePriceWrite();
        abort_unless($supplierId === (int) $supplier->id, 403, 'شما مجاز به ویرایش این محصول نیستید.');
        abort_unless((int) $this->productSupplier->supplier_id === $supplierId, 403, 'شما مجاز به ویرایش این محصول نیستید.');
        $categoryId = $this->product->category_id ?? null;
        
        DB::transaction(function () use ($supplierId, $categoryId) {
            // حذف تنظیمات قبلی این محصول
            \App\Models\SupplierTierPercentage::where('supplier_id', $supplierId)
                ->where('product_id', $this->product->id)
                ->delete();
            
            // اگر محاسبه خودکار فعال است، ذخیره کن
            if ($this->autoCalculateTiers) {
                foreach ($this->quantityTiers as $index => $tier) {
                    // ✅ تشخیص درصد بر اساس mode
                    $mode = $this->tierPriceMode[$index] ?? 'default';
                    
                    if ($mode === 'default') {
                        // استفاده از درصد مؤثر (دسته‌بندی یا پیشفرض)
                        $percentage = $this->getEffectiveTierPercentage($supplierId, $categoryId, $index);
                    } else {
                        // استفاده از درصد اختصاصی
                        $percentage = (float) ($this->tierPercentages[$index] ?? 0);
                    }
                    
                    \App\Models\SupplierTierPercentage::create([
                        'supplier_id' => $supplierId,
                        'product_id' => $this->product->id,
                        'tier_index' => (int) $index,
                        'tier_label' => $tier['name'] ?? "تیراژ " . ($index + 1),
                        'percentage' => $percentage,
                        'is_reference' => ($index == $this->referenceTierIndex),
                        'is_active' => true,
                        'sort_order' => $index,
                    ]);
                }
            }
        });
    }

    private function authorizePriceWrite(): int
    {
        $user = Auth::user();
        $supplier = $user?->currentSupplier();
        $this->assertPriceWriteContext($user, $supplier);

        return (int) $supplier->id;
    }

    private function assertPriceWriteContext(
        ?User $user,
        ?Supplier $supplier,
        ?Supplier $primarySupplier = null,
        bool $requireCurrentSelection = false,
    ): void
    {
        abort_unless(
            $user && $user->hasAnyRole(['supplier', 'regular_supplier', 'admin', 'super_admin']),
            403,
            'شما مجاز به ویرایش قیمت نیستید.'
        );

        abort_unless($supplier, 403, 'حساب تامین‌کننده برای این کار در دسترس نیست.');
        if ($requireCurrentSelection) {
            $currentSupplier = $user->currentSupplier();
            abort_unless(
                (int) $currentSupplier->id === (int) $supplier->id,
                403,
                'شعبهٔ جاری هنگام ثبت قیمت تغییر کرده است.'
            );
        }

        abort_unless(
            (int) $supplier->user_id === (int) $user->id
                && $supplier->is_active
                && $supplier->is_verified,
            403,
            'حساب تامین‌کننده فعال و تاییدشده نیست.'
        );

        if ($supplier->isBranch()) {
            $primarySupplier ??= $supplier->parentSupplier;
            abort_unless(
                $primarySupplier
                    && (int) $primarySupplier->user_id === (int) $user->id
                    && $primarySupplier->is_active
                    && $primarySupplier->is_verified,
                403,
                'حساب تامین‌کننده اصلی فعال و تاییدشده نیست.'
            );
        }

        if ($user->hasAnyRole(['supplier', 'regular_supplier'])) {
            abort_unless(
                $supplier->verification_status === 'approved'
                    && (!$supplier->isBranch() || $primarySupplier?->verification_status === 'approved'),
                403,
                'حساب تامین‌کننده فعال و تاییدشده نیست.'
            );
        }
    }

    /**
     * محاسبه خودکار قیمت تیراژها بر اساس تیراژ مرجع
     */
    public function calculateTierPrices(bool $forceCurrencyDerived = false): void
    {
        if (!$this->autoCalculateTiers && !$forceCurrencyDerived) {
            return;
        }
        
        // دریافت قیمت نقدی تیراژ مرجع
        $cashPriceType = $this->priceTypes->firstWhere('code', 'cash');
        if (!$cashPriceType) return;
        
        $referencePrice = $this->priceMatrix[$this->referenceTierIndex][$cashPriceType->id]['price'] ?? null;
        
        if (!$referencePrice || !is_numeric($referencePrice)) {
            return;
        }
        
        $supplier = Auth::user()->currentSupplier();
        $supplierId = $supplier->id;
        
        // ✅ دریافت دسته‌بندی محصول برای بررسی درصد اختصاصی
        $categoryId = $this->product->category_id ?? null;
        
        // محاسبه قیمت سایر تیراژها
        foreach ($this->quantityTiers as $index => $tier) {
            // خود تیراژ مرجع را skip کن
            if ($index == $this->referenceTierIndex) {
                continue;
            }
            
            // فعال‌بودن تیراژ فقط فروش آن را کنترل می‌کند؛ قیمت هر تیراژ باید
            // حتی در حالت غیرفعال هم از مرجع ثابت کارتن و درصد خودش محاسبه شود.

            // ✅ تشخیص درصد بر اساس mode
            $mode = $this->tierPriceMode[$index] ?? 'default';
            
            if ($mode === 'default') {
                // ✅ اولویت‌بندی: دسته‌بندی > پیشفرض
                $percentage = $this->getEffectiveTierPercentage($supplierId, $categoryId, $index);
            } else {
                // استفاده از درصد اختصاصی (وارد شده توسط کاربر)
                $percentage = $this->tierPercentages[$index] ?? 0;
            }
            
            // محاسبه قیمت بر اساس درصد
            $calculatedPrice = $referencePrice * (1 + ($percentage / 100));
            
            // رُند کردن با سیستم رنج‌ها
            $roundedPrice = \App\Helpers\PriceRoundingHelper::round($calculatedPrice, $supplier);
            
            // بروزرسانی در priceMatrix
            $this->priceMatrix[$index][$cashPriceType->id]['price'] = $roundedPrice;
        }
        
        // بروزرسانی قیمت‌های چکی برای همه تیراژها
        $this->updateAllCheckPrices();
    }
    
    /**
     * دریافت درصد مؤثر برای تیراژ (با اولویت‌بندی)
     * اولویت: دسته‌بندی > پیشفرض
     * 
     * @param int $supplierId
     * @param int|null $categoryId
     * @param int $tierIndex
     * @return float
     */
    private function getEffectiveTierPercentage(int $supplierId, ?int $categoryId, int $tierIndex): float
    {
        $cacheKey = $supplierId . ':' . ($categoryId ?? 'none');

        if (!array_key_exists($cacheKey, $this->effectiveTierPercentageCacheLoaded)) {
            $tierIndexes = collect(array_keys($this->quantityTiers ?? []))
                ->map(fn ($index) => (int) $index)
                ->push($tierIndex)
                ->unique()
                ->values()
                ->all();
            $defaults = \App\Models\SupplierTierDiscount::getAllForSupplier($supplierId)
                ->keyBy('tier_index');
            $categoryPercentages = $categoryId
                ? DB::table('category_tier_discount')
                    ->where('supplier_id', $supplierId)
                    ->where('category_id', $categoryId)
                    ->whereIn('tier_index', $tierIndexes)
                    ->where('is_active', true)
                    ->pluck('percentage_change', 'tier_index')
                    ->all()
                : [];

            foreach ($tierIndexes as $index) {
                $default = $defaults->get($index);
                $this->effectiveTierPercentageCache[$cacheKey][$index] = array_key_exists($index, $categoryPercentages)
                    ? ['percentage' => (float) $categoryPercentages[$index], 'source' => 'category']
                    : ['percentage' => $default
                        ? (float) $default->percentage_change
                        : \App\Models\SupplierTierDiscount::defaultPercentage((int) $index), 'source' => 'default'];
            }

            $this->effectiveTierPercentageCacheLoaded[$cacheKey] = true;
        }

        return (float) ($this->effectiveTierPercentageCache[$cacheKey][$tierIndex]['percentage'] ?? 0);
    }

    /**
     * دریافت درصد مؤثر و منبع آن برای نمایش در UI
     * 
     * @param int $tierIndex
     * @return array ['percentage' => float, 'source' => string]
     */
    public function getEffectiveTierPercentageForUI(int $tierIndex): array
    {
        $supplierId = (int) Auth::user()->currentSupplier()->id;
        $categoryId = $this->product->category_id;
        $this->getEffectiveTierPercentage($supplierId, $categoryId, $tierIndex);
        $cacheKey = $supplierId . ':' . ($categoryId ?? 'none');

        return $this->effectiveTierPercentageCache[$cacheKey][$tierIndex]
            ?? ['percentage' => 0.0, 'source' => 'default'];
    }

    /**
     * بروزرسانی قیمت‌های چکی برای همه تیراژها
     */
    private function updateAllCheckPrices(): void
    {
        $checkPriceType = $this->priceTypes->firstWhere('code', 'check');
        if (!$checkPriceType) return;
        
        foreach ($this->quantityTiers as $tierIndex => $tier) {
            foreach ($this->checkPeriods as $period) {
                // فقط دوره‌های فعال
                $isEnabled = $this->checkPeriodEnabled[$tierIndex][$period->id] ?? false;
                if (!$isEnabled) continue;
                
                $mode = $this->checkPriceMode[$tierIndex][$period->id] ?? 'default';
                
                // فقط برای mode های default و percentage محاسبه مجدد کن
                if (in_array($mode, ['default', 'percentage'])) {
                    $calculatedCheckPrice = $this->calculateCheckPrice($tierIndex, $period->id);
                    
                    if ($calculatedCheckPrice !== null) {
                        if (!isset($this->priceMatrix[$tierIndex][$checkPriceType->id]['check_periods'])) {
                            $this->priceMatrix[$tierIndex][$checkPriceType->id]['check_periods'] = [];
                        }
                        
                        $this->priceMatrix[$tierIndex][$checkPriceType->id]['check_periods'][$period->id]['price'] = $calculatedCheckPrice;
                    }
                }
            }
        }
    }

    /**
     * تغییر تیراژ مرجع
     */
    public function updateReferenceTier($newReferenceTierIndex): void
    {
        if ((int) $newReferenceTierIndex !== 1) {
            $this->addError('referenceTierIndex', 'تیراژ مرجع قیمت‌گذاری همیشه کارتن (تیراژ ۲) است.');
            return;
        }

        $this->referenceTierIndex = 1;
        $this->tierPercentages[1] = 0;
    }

    /**
     * Hook: وقتی قیمت نقدی تیراژ مرجع تغییر می‌کند
     */
    public function updatedPriceMatrix($value, $key): void
    {
        // فرمت کلید: priceMatrix.{tierIndex}.{priceTypeId}.price
        if (!preg_match('/^(\d+)\.(\d+)\.price$/', $key, $matches)) {
            return;
        }

        $priceType = $this->priceTypes->firstWhere('id', (int) $matches[2]);
        $tierIndex = (int) $matches[1];
        $priceTypeId = (int) $matches[2];

        if ($priceType?->code === 'cash' && $this->priceCurrency !== 'TOM') {
            if ($tierIndex === $this->referenceTierIndex) {
                $storedPrice = ProductPrice::query()
                    ->where('product_supplier_id', $this->productSupplier->id)
                    ->where('price_type_id', $priceType->id)
                    ->where('tier_index', $tierIndex)
                    ->whereNull('check_period_id')
                    ->where('is_active', true)
                    ->value('price');
                $this->priceMatrix[$tierIndex][$priceTypeId]['price'] = $storedPrice;
                $this->addError('foreignPriceAmount', 'قیمت پایهٔ این محصول باید به ارز انتخاب‌شده وارد شود؛ ورود تومانی در این حالت مجاز نیست.');
            } else {
                $this->calculateTierPrices(forceCurrencyDerived: true);
            }

            return;
        }
        
        // بررسی: آیا این تیراژ مرجع است؟
        if ($tierIndex !== $this->referenceTierIndex) {
            return;
        }
        
        // بررسی: آیا نوع قیمت = نقدی است؟
        $cashPriceType = $this->priceTypes->firstWhere('code', 'cash');
        if (!$cashPriceType || $priceTypeId !== $cashPriceType->id) {
            return;
        }
        
        // اگر محاسبه خودکار فعال است، قیمت سایر تیراژها را محاسبه کن
        if ($this->autoCalculateTiers) {
            $this->calculateTierPrices();
            
            $this->dispatch('notify', 
                message: 'قیمت سایر تیراژها خودکار محاسبه شد.',
                type: 'success'
            );
        }
    }

    public function updatedForeignPriceAmount(): void
    {
        $this->normalizeForeignPriceAmount();
        $this->syncForeignPriceToToman();
    }

    private function normalizeForeignPriceAmount(): void
    {
        if ($this->foreignPriceAmount === null) {
            return;
        }

        $digits = [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ];

        $amount = strtr(trim((string) $this->foreignPriceAmount), $digits);
        $amount = str_replace(['٬', ','], '', $amount);
        $amount = str_replace('٫', '.', $amount);
        $this->foreignPriceAmount = $amount === '' ? null : $amount;
    }

    private function loadLatestExchangeRate(): void
    {
        $setting = SupplierExchangeRateSetting::query()
            ->where('supplier_id', $this->productSupplier->supplier_id)
            ->where('currency', $this->priceCurrency)
            ->first();
        $rateService = app(\App\Services\ForeignCurrencyPriceService::class);
        $rateValue = $setting?->auto_update
            ? $setting?->rate_toman
            : $setting?->manual_rate_toman;

        $this->latestExchangeRate = $rateValue === null ? null : (string) $rateValue;
        $this->latestExchangeSource = $setting?->auto_update ? $setting?->source : 'manual';
        $isStale = $setting?->auto_update && !$rateService->isFreshSupplierRate($setting);
        $this->latestExchangeError = $setting?->last_error
            ?: ($isStale ? 'نرخ خودکار تازه نیست؛ آخرین قیمت ثبت‌شده حفظ می‌شود.' : null);
        $this->latestExchangeFetchedAt = $setting?->auto_update && $setting?->fetched_at
            ? $setting->fetched_at->timezone('Asia/Tehran')->format('Y/m/d H:i') . ' تهران'
            : null;
    }

    private function refreshForeignPriceRevisionToken(): void
    {
        $this->foreignPriceRevision = (int) ($this->productSupplier?->foreign_price_revision ?? 0);
        $message = implode(':', [
            $this->productSupplier->getKey(),
            $this->foreignPriceRevision,
            $this->priceMatrixFingerprint,
        ]);
        $this->foreignPriceRevisionToken = hash_hmac('sha256', $message, (string) config('app.key'));
    }

    private function matchesForeignPriceRevision(ProductSupplier $productSupplier): bool
    {
        $currentFingerprint = $this->currentPriceMatrixFingerprint($productSupplier, lockRows: true);
        $message = implode(':', [
            $productSupplier->getKey(),
            $this->foreignPriceRevision,
            $this->priceMatrixFingerprint,
        ]);
        $expectedToken = hash_hmac('sha256', $message, (string) config('app.key'));

        return $this->foreignPriceRevisionToken !== null
            && hash_equals($expectedToken, $this->foreignPriceRevisionToken)
            && (int) $productSupplier->foreign_price_revision === $this->foreignPriceRevision
            && hash_equals($this->priceMatrixFingerprint, $currentFingerprint);
    }

    private function currentPriceMatrixFingerprint(ProductSupplier $productSupplier, bool $lockRows = false): string
    {
        $rows = ProductPrice::query()
            ->where('product_supplier_id', $productSupplier->getKey())
            ->orderBy('id')
            ->when($lockRows, fn ($query) => $query->lockForUpdate())
            ->get([
                'id',
                'product_id',
                'price_type_id',
                'tier_index',
                'check_period_id',
                'price',
                'percentage_override',
                'calculation_mode',
                'is_active',
                'valid_from',
                'valid_until',
            ]);

        return $this->fingerprintPriceRows($rows);
    }

    private function fingerprintPriceRows(\Illuminate\Support\Collection $rows): string
    {
        $normalizedRows = $rows
            ->map(fn (ProductPrice $price) => [
                (int) $price->id,
                (int) $price->product_id,
                (int) $price->price_type_id,
                (int) $price->tier_index,
                $price->check_period_id === null ? null : (int) $price->check_period_id,
                (string) $price->price,
                $price->percentage_override === null ? null : (string) $price->percentage_override,
                $price->calculation_mode,
                (bool) $price->is_active,
                $price->valid_from?->format('Y-m-d'),
                $price->valid_until?->format('Y-m-d'),
            ])
            ->all();

        return hash('sha256', json_encode($normalizedRows, JSON_THROW_ON_ERROR));
    }

    private function syncForeignPriceToToman(bool $requireAmount = false, ?SupplierExchangeRateSetting $rateOverride = null): bool
    {
        $this->usingStoredRateFallback = false;
        $this->foreignPricePreviewValid = false;

        if ($this->priceCurrency === 'TOM') {
            return true;
        }

        if ($this->foreignPriceAmount === null || $this->foreignPriceAmount === '') {
            if ($requireAmount) {
                $this->addError('foreignPriceAmount', 'مبلغ محصول به ارز انتخاب‌شده را وارد کنید.');
                return false;
            }

            return true;
        }

        $rateService = app(\App\Services\ForeignCurrencyPriceService::class);
        $rateSetting = $rateOverride ?? SupplierExchangeRateSetting::query()
            ->where('supplier_id', $this->productSupplier->supplier_id)
            ->where('currency', $this->priceCurrency)
            ->first();

        // داده‌های قدیمی با دقت چهار رقم را تا وقتی کاربر مبلغ را عوض نکرده، حفظ می‌کنیم.
        $isUnchangedStoredPrice = $this->isUnchangedStoredForeignPrice();

        if (!is_numeric($this->foreignPriceAmount)
            || (float) $this->foreignPriceAmount <= 0
            || (!preg_match('/^\d+(?:\.\d{1,2})?$/', (string) $this->foreignPriceAmount) && !$isUnchangedStoredPrice)) {
            $this->addError('foreignPriceAmount', 'مبلغ جدید باید بزرگ‌تر از صفر و حداکثر با دو رقم اعشار باشد.');
            return false;
        }

        $isUsableRate = $rateService->isUsableSupplierRate($rateSetting);

        if (!$isUsableRate && !$isUnchangedStoredPrice) {
            $this->loadLatestExchangeRate();
            $this->addError('foreignPriceAmount', 'برای تبدیل این مبلغ، نرخ دستی معتبر یا نرخ خودکار تازه لازم است. آخرین قیمت ثبت‌شده تا آماده‌شدن نرخ معتبر حفظ می‌شود.');
            return false;
        }

        $cashPriceType = $this->priceTypes->firstWhere('code', 'cash');
        if (!$cashPriceType || !isset($this->priceMatrix[$this->referenceTierIndex][$cashPriceType->id])) {
            $this->addError('foreignPriceAmount', 'قیمت نقدی تیراژ مرجع برای این محصول در دسترس نیست.');
            return false;
        }

        $this->usingStoredRateFallback = $isUnchangedStoredPrice && !$isUsableRate;
        $conversionRate = $this->usingStoredRateFallback
            ? (float) $this->productSupplier->foreign_price_rate
            : (float) ($rateSetting->auto_update ? $rateSetting->rate_toman : $rateSetting->manual_rate_toman);

        try {
            $tomanPrice = app(\App\Services\ForeignCurrencyPriceService::class)
                ->calculateToman((float) $this->foreignPriceAmount, $conversionRate, $this->productSupplier);
        } catch (\InvalidArgumentException $exception) {
            $this->addError('foreignPriceAmount', $exception->getMessage());
            return false;
        }

        if ($this->usingStoredRateFallback) {
            $this->latestExchangeRate = (string) $this->productSupplier->foreign_price_rate;
            $this->latestExchangeSource = $this->productSupplier->foreign_price_source;
            $this->latestExchangeFetchedAt = $this->foreignPriceUpdatedAt
                ? $this->foreignPriceUpdatedAt . ' تهران'
                : $this->latestExchangeFetchedAt;
        } else {
            $this->latestExchangeRate = (string) $conversionRate;
            $this->latestExchangeSource = $rateSetting->auto_update ? $rateSetting->source : 'manual';
            $this->latestExchangeFetchedAt = $rateSetting->auto_update
                ? $rateSetting->fetched_at?->timezone('Asia/Tehran')->format('Y/m/d H:i') . ' تهران'
                : null;
            $this->latestExchangeError = null;
        }

        $this->priceMatrix[$this->referenceTierIndex][$cashPriceType->id]['price'] = $tomanPrice;
        $this->foreignPricePreviewValid = true;

        if ($this->autoCalculateTiers || $this->priceCurrency !== 'TOM') {
            $this->calculateTierPrices(forceCurrencyDerived: $this->priceCurrency !== 'TOM');
        } else {
            $this->updateAllCheckPrices();
        }

        $this->foreignPriceUpdatedAt = $this->productSupplier->foreign_price_updated_at?->timezone('Asia/Tehran')->format('Y/m/d H:i');
        $this->foreignPriceLastError = $this->productSupplier->foreign_price_last_error;
        $this->resetErrorBag('foreignPriceAmount');

        return true;
    }

    private function foreignPriceAmountForStorage(): ?string
    {
        if ($this->priceCurrency === 'TOM') {
            return null;
        }

        $storedAmount = $this->productSupplier->foreign_price_amount;
        $isUnchangedStoredPrice = $this->isUnchangedStoredForeignPrice();

        return $isUnchangedStoredPrice
            ? (string) $storedAmount
            : number_format((float) $this->foreignPriceAmount, 2, '.', '');
    }

    private function isUnchangedStoredForeignPrice(): bool
    {
        if ($this->productSupplier === null
            || $this->productSupplier->price_currency !== $this->priceCurrency
            || $this->productSupplier->foreign_price_amount === null
            || $this->productSupplier->foreign_price_rate === null
            || $this->foreignPriceAmount === null) {
            return false;
        }

        $storedAmount = $this->canonicalDecimalString($this->productSupplier->foreign_price_amount);
        $submittedAmount = $this->canonicalDecimalString($this->foreignPriceAmount);

        return $storedAmount !== null && $storedAmount === $submittedAmount;
    }

    private function canonicalDecimalString(mixed $value): ?string
    {
        $value = trim((string) $value);
        if (!preg_match('/^\d+(?:\.\d+)?$/', $value)) {
            return null;
        }

        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $whole = ltrim($whole, '0') ?: '0';
        $fraction = rtrim($fraction, '0');

        return $fraction === '' ? $whole : $whole . '.' . $fraction;
    }

    private function resolveOwnedProductAndOffer(\App\Models\Supplier $supplier): void
    {
        $productId = $this->product?->id;
        abort_unless($productId, 403, 'محصول معتبر نیست.');

        $product = Product::query()
            ->whereKey($productId)
            ->where(function ($query) use ($supplier): void {
                $query->where('supplier_id', $supplier->id)
                    ->orWhereHas('productSuppliers', fn ($offers) => $offers->where('supplier_id', $supplier->id));
            })
            ->firstOrFail();

        $offerId = $this->productSupplier?->id;
        abort_unless($offerId, 403, 'پیشنهاد این محصول در شعبهٔ جاری موجود نیست.');
        $offer = ProductSupplier::query()
            ->whereKey($offerId)
            ->where('product_id', $product->id)
            ->where('supplier_id', $supplier->id)
            ->firstOrFail();

        $this->product = $product;
        $this->productSupplier = $offer;
    }

    public function render()
    {
        return view($this->isBranchCopy
            ? 'livewire.supplier.products.edit-branch-pricing'
            : 'livewire.supplier.products.edit-price');
    }
}
