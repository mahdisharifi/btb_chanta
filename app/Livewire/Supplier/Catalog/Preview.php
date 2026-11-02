<?php

namespace App\Livewire\Supplier\Catalog;

use App\Enums\ProductStatus;
use App\Enums\PriceType;
use Livewire\Component;
use Livewire\Attributes\Renderless;
use App\Models\CatalogTemplate;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\ProductSupplier;
use Livewire\Attributes\Locked;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Str;
use PDF;

class Preview extends Component
{
    private const CATALOG_DATA_CACHE_VERSION = 2;

    #[Locked]
    public bool $isAdmin = false;

    #[Locked]
    public $catalog;
    #[Locked]
    public $supplierLogoUrl;
    #[Locked]
    public $supplierLogoPath;
    #[Locked]
    public $supplier;
    #[Locked]
    public $supplierName;
    #[Locked]
    public $template;
    #[Locked]
    public $settings = [];
    #[Locked]
    public ?string $catalogDataCacheKey = null;
    #[Locked]
    public bool $catalogSnapshotBusy = false;
    #[Locked]
    public $selectedPriceTypes = [];
    #[Locked]
    public $selectedCheckPeriods = []; // ✅ دوره‌های چک انتخاب شده
    #[Locked]
    public $includeWarranty = false; // ✅ نمایش گارانتی
    #[Locked]
    public ?string $displayCurrencyCode = 'TOM';
    #[Locked]
    public ?string $displayCurrencyLabel = 'تومان';
    #[Locked]
    public ?array $exchangeRateBasis = null;
    #[Locked]
    public bool $currencySnapshotWarning = false;
    #[Locked]
    public $separateCategories = false;
    #[Locked]
    public $showCoverPage = true;
    #[Locked]
    public $priceLabels = [];
    #[Locked]
    public $progressKey = null;
    #[Locked]
    public ?string $pdfDownloadToken = null;
    private $enableProgress = false;

    public function mount($catalog)
    {
        // تولید کلید یونیک برای tracking progress در ابتدا
        $this->progressKey = 'catalog_progress_' . (string) Str::uuid();
        
        $user = Auth::user();
        $this->isAdmin = $this->authenticatedUserIsAdmin();

        $this->template = CatalogTemplate::findOrFail($catalog);
        $this->authorizeCurrentTemplate();

        if ($this->isAdmin) {
            // ادمین: supplier صاحب کاتالوگ را بگیر (برای صفحه اول)
            $this->supplier = \App\Models\Supplier::find($this->template->supplier_id);

            if (!$this->supplier) {
                // اگر کاتالوگ supplier نداشت، supplier خود ادمین را بگیر
                $this->supplier = \App\Models\Supplier::where('user_id', $user->id)
                    ->where('type', 'admin')
                    ->first();
            }

            abort_if(! $this->supplier, 403, 'شما پروفایل تامین‌کننده ندارید.');
        } else {
            // تامین‌کننده: فقط کاتالوگ خودش
            $this->supplier = $user->currentSupplier();
            abort_if(! $this->supplier, 403, 'شما پروفایل تامین‌کننده ندارید.');

            // بررسی مالکیت
            abort_if($this->template->supplier_id !== $this->supplier->id, 403, 'این کاتالوگ متعلق به شما نیست.');
        }

        $this->supplierLogoPath = $this->supplier->logoAbsolutePath('pdf');

        $this->supplierName = $this->supplier->company_name ?? 'مدیریت مرکزی';
        $this->supplierLogoUrl = $this->supplier->logoUrl('medium');

        $this->settings = $this->template->settings ?? [];
        
        // Resolve settings: اگر source_catalog_id دارد، از تنظیمات کاتالوگ source استفاده کن
        $this->resolveSettings();

        $this->refreshCurrencyDisplayState();

        $this->separateCategories = filter_var($this->settings['separate_categories'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $this->showCoverPage = filter_var(
            $this->settings['show_cover_page']
            ?? $this->settings['show_cover']
            ?? true,
            FILTER_VALIDATE_BOOLEAN
        );

        $this->priceLabels = PriceType::options();

        $selectedPrices = $this->settings['price_types']
            ?? $this->settings['prices']
            ?? 'all';

        // پشتیبانی از ساختار جدید: ['tier_index' => [price_type_ids]]
        if ($selectedPrices === 'all' || empty($selectedPrices)) {
            // همه تیراژها با همه انواع قیمت
            $this->selectedPriceTypes = 'all';
        } elseif (is_array($selectedPrices)) {
            // بررسی ساختار: آیا ساختار جدید است یا قدیم؟
            // ساختار قدیمی: [[1,3], [1,2,3], ...] - 4 عضو، همه آرایه
            // ساختار جدید: {"0": [1,3], "1": [1,2,3], ...} - کلیدها نام دارند
            
            $isNewStructure = false;
            
            // روش 1: اگر کلیدها string باشند (از JSON)
            $keys = array_keys($selectedPrices);
            if (!empty($keys) && is_string($keys[0])) {
                $isNewStructure = true;
            }
            
            // روش 2: اگر تعداد عناصر کمتر یا بیشتر از 4 باشد (قدیمی همیشه 4 تا دارد)
            if (!$isNewStructure && count($selectedPrices) !== 4) {
                $isNewStructure = true;
            }
            
            // روش 3: اگر کلیدها متوالی نباشند (0,1,2,3)
            if (!$isNewStructure) {
                $expectedKeys = [0, 1, 2, 3];
                $actualKeys = array_keys($selectedPrices);
                sort($actualKeys);
                if ($actualKeys !== $expectedKeys) {
                    $isNewStructure = true;
                }
            }
            
            if ($isNewStructure) {
                // ساختار جدید: ['tier_index' => [price_type_ids]]
                $this->selectedPriceTypes = $selectedPrices;
                
                // ✅ بارگذاری دوره‌های چک (اگر وجود داشته باشد)
                $this->selectedCheckPeriods = $this->settings['check_periods'] ?? [];
            } else {
                // ساختار قدیم: [[price_type_ids], ...] - برای backward compatibility
                // تبدیل به ساختار جدید: هر تیراژ با قیمت‌های خودش
                $this->selectedPriceTypes = [];
                foreach ($selectedPrices as $tierIndex => $priceTypeIds) {
                    if (is_array($priceTypeIds)) {
                        $this->selectedPriceTypes[$tierIndex] = $priceTypeIds;
                    }
                }
                
                // برای ساختار قدیمی، دوره‌های چک وجود ندارد
                $this->selectedCheckPeriods = [];
            }
        } else {
            // مقدار نامعتبر - استفاده از پیشفرض
            $this->selectedPriceTypes = 'all';
        }
        
        // ✅ بارگذاری نمایش گارانتی
        $this->includeWarranty = $this->settings['include_warranty'] ?? false;

        // بارگذاری داده‌ها برای preview (بدون progress)
        $this->loadCatalogData($this->authorizedSupplierScope());
    }

    private function authenticatedUserIsAdmin(): bool
    {
        $user = Auth::user();

        return $user !== null && (
            $user->hasRole('admin')
            || $user->hasRole('super_admin')
            || $user->hasRole('super-admin')
        );
    }

    private function authorizeCurrentTemplate(): void
    {
        abort_if(!Auth::check(), 403);

        if ($this->authenticatedUserIsAdmin()) {
            return;
        }

        $supplierId = Auth::user()?->currentSupplier()?->id;
        abort_if(!$supplierId || (int) $this->template?->supplier_id !== (int) $supplierId, 403);
    }

    private function authorizedSupplierScope(): ?int
    {
        if ($this->authenticatedUserIsAdmin()) {
            return null;
        }

        $supplierId = Auth::user()?->currentSupplier()?->id;
        abort_if(!$supplierId, 403, 'پروفایل تأمین‌کننده پیدا نشد.');
        abort_if((int) $this->template->supplier_id !== (int) $supplierId, 403);

        return (int) $supplierId;
    }

    private function refreshCurrencyDisplayState(): void
    {
        $supplier = $this->resolveCurrencySupplier();
        $currency = ($this->settings['price_display_currency'] ?? 'toman') === 'supplier'
            ? $this->eligibleCatalogCurrencyCode()
            : 'TOM';

        $this->displayCurrencyCode = $currency === 'TOM'
            ? 'TOM'
            : (app(\App\Services\CatalogPriceFormatter::class)
                ->isSupportedForeignCurrency($currency) ? strtoupper((string) $currency) : null);
        $this->displayCurrencyLabel = app(\App\Services\CatalogPriceFormatter::class)
            ->label($this->displayCurrencyCode);
        $this->exchangeRateBasis = $supplier
            ? app(\App\Services\CatalogExchangeRateBasisResolver::class)->resolve($supplier)
            : null;
    }

    private function resolveCurrencySupplier(): ?\App\Models\Supplier
    {
        if ($this->authenticatedUserIsAdmin()) {
            $supplierIds = $this->template->config['supplier_ids'] ?? [];
            if (!is_array($supplierIds)) {
                return null;
            }

            $supplierIds = array_values(array_unique(array_filter(array_map('intval', $supplierIds))));
            if (count($supplierIds) !== 1) {
                return null;
            }

            return \App\Models\Supplier::query()
                ->where('verification_status', 'approved')
                ->find($supplierIds[0]);
        }

        $supplierId = $this->catalogBranchSupplierIds($this->authorizedSupplierScope())[0] ?? null;

        return $supplierId ? \App\Models\Supplier::query()->find($supplierId) : null;
    }

    private function eligibleCatalogCurrencyCode(): ?string
    {
        $supplierIds = $this->authenticatedUserIsAdmin()
            ? array_values(array_unique(array_map('intval', $this->template->config['supplier_ids'] ?? [])))
            : $this->catalogBranchSupplierIds($this->authorizedSupplierScope());

        if ($supplierIds === [] || ($this->authenticatedUserIsAdmin() && count($supplierIds) !== 1)) {
            return null;
        }

        $suppliers = \App\Models\Supplier::query()->whereIn('id', $supplierIds)->get();
        if ($suppliers->count() !== count(array_unique($supplierIds))) {
            return null;
        }

        $currencies = $suppliers
            ->map(static fn ($supplier): string => strtoupper((string) $supplier->pricing_currency))
            ->unique()
            ->values();
        if ($currencies->count() !== 1) {
            return null;
        }

        $currency = $currencies->first();
        if ($currency === 'TOM') {
            return 'TOM';
        }

        $resolver = app(\App\Services\CatalogExchangeRateBasisResolver::class);
        $rates = $resolver->resolveMany($suppliers)->map(static function (?array $basis): ?float {
            return $basis && is_numeric($basis['rate_value'] ?? null)
                ? (float) $basis['rate_value']
                : null;
        });

        if ($rates->contains(null)) {
            return null;
        }

        $firstRate = (float) $rates->first();
        if ($rates->contains(static fn (?float $rate): bool => abs((float) $rate - $firstRate) >= 0.00005)) {
            return null;
        }

        return app(\App\Services\CatalogPriceFormatter::class)
            ->isSupportedForeignCurrency($currency) ? $currency : null;
    }

    /** @return array<int, int> */
    private function catalogBranchSupplierIds(?int $ownerSupplierId): array
    {
        if (!$ownerSupplierId) {
            return [];
        }

        $owner = \App\Models\Supplier::query()->findOrFail($ownerSupplierId);
        $primary = $owner->parent_supplier_id
            ? $owner->parentSupplier
            : $owner;

        abort_unless(
            $primary
                && (int) $primary->user_id === (int) $owner->user_id
                && (bool) $primary->is_active,
            403
        );

        $family = collect([(int) $primary->id])->merge(
            $primary->branches()
                ->where('is_active', true)
                ->where('is_verified', true)
                ->where('user_id', $primary->user_id)
                ->orderBy('id')
                ->pluck('id')
                ->map(static fn ($id): int => (int) $id)
        )->unique()->values();

        $scope = $this->settings['catalog_branch_scope'] ?? 'single';
        if ($scope === 'all') {
            return $family->all();
        }

        $selectedId = (int) ($this->settings['catalog_branch_supplier_id'] ?? $ownerSupplierId);
        abort_unless($family->contains($selectedId), 403, 'شعبهٔ انتخاب‌شده متعلق به این فروشگاه نیست یا فعال نیست.');

        return [$selectedId];
    }

    /**
     * Resolve settings: اگر source_catalog_id دارد، از تنظیمات کاتالوگ source استفاده کن
     */
    private function resolveSettings()
    {
        if (isset($this->settings['custom_cover']['source_catalog_id']) && $this->settings['custom_cover']['source_catalog_id']) {
            $sourceCatalogId = $this->settings['custom_cover']['source_catalog_id'];
            $sourceCatalog = CatalogTemplate::find($sourceCatalogId);
            
            if ($sourceCatalog && isset($sourceCatalog->settings['custom_cover'])) {
                // استفاده از تنظیمات کاتالوگ source
                $this->settings['custom_cover'] = $sourceCatalog->settings['custom_cover'];
            }
        }
    }
    
    private function loadCatalogData($supplierId = null): array
    {
        $this->authorizeCurrentTemplate();
        $isAdmin = $this->authenticatedUserIsAdmin();
        if (!$isAdmin) {
            $supplierId = $this->authorizedSupplierScope();
        }

        $cacheIdentity = $this->catalogCacheIdentity($supplierId);
        $selectedSupplierIds = $cacheIdentity['supplier_ids'];
        $cacheKey = $cacheIdentity['cache_key'];
        abort_unless($selectedSupplierIds !== [], 403, 'هیچ شعبهٔ فعالی برای این کاتالوگ پیدا نشد.');
        $branchAware = !$isAdmin && ($this->settings['catalog_branch_scope'] ?? 'single') === 'all';
        $branchLabels = [];

        if ($branchAware) {
            $selectedBranches = \App\Models\Supplier::query()
                ->leftJoin('cities as selected_city', function ($join): void {
                    $join->on('suppliers.city_id', '=', 'selected_city.id')
                        ->on('suppliers.province_id', '=', 'selected_city.province_id')
                        ->where('selected_city.is_active', true)
                        ->whereNull('selected_city.deleted_at');
                })
                ->whereIn('suppliers.id', $selectedSupplierIds)
                ->get([
                    'suppliers.id',
                    'suppliers.branch_name',
                    'suppliers.parent_supplier_id',
                    'suppliers.city',
                    'selected_city.name as selected_city_name',
                    'selected_city.native_name as selected_city_native_name',
                ]);
            $cityCounts = $selectedBranches
                ->map(static fn ($branch): string => mb_strtolower(trim((string) ($branch->selected_city_native_name ?: $branch->selected_city_name ?: $branch->city))))
                ->filter()
                ->countBy();

            foreach ($selectedBranches as $branch) {
                $city = trim((string) ($branch->selected_city_native_name ?: $branch->selected_city_name ?: $branch->city));
                $branchName = $branch->branchLabel();
                if ($city === '') {
                    $label = $branchName . ' · شهر ثبت نشده';
                } else {
                    $cityKey = mb_strtolower($city);
                    $label = $city;
                    if (($cityCounts[$cityKey] ?? 0) > 1) {
                        $label .= ' · ' . $branchName;
                    }
                }
                $branchLabels[(int) $branch->id] = $label;
            }
        }
        
        // دریافت timestamp آخرین تغییرات برای تشخیص نیاز به رفرش کش
        // این timestampها باعث می‌شوند وقتی داده‌ها تغییر کنند، کش خودکار رفرش شود
        $productsUpdatedAt = \App\Models\Product::max('updated_at');
        $pricesUpdatedAt = \App\Models\ProductPrice::max('updated_at');
        $supplierUpdatedAt = \App\Models\Supplier::whereIn('id', $selectedSupplierIds)->max('updated_at');
        
        // اضافه کردن timestamp برای داده‌های مرتبط دیگر
        $categoriesUpdatedAt = \App\Models\Category::max('updated_at');
        $attributesUpdatedAt = \App\Models\AttributeValue::max('updated_at');
        $imagesUpdatedAt = \App\Models\ProductGallery::max('updated_at');
        
        // تولید کلید کش یونیک برای این کاتالوگ و تنظیمات + timestamp تغییرات
        // نکته: selected_supplier_ids هم اضافه می‌شود تا با تغییر تامین‌کننده‌ها کش رفرش شود
        $cacheKey = 'catalog_' . $this->template->id . '_' . md5(json_encode([
            'payload_version' => self::CATALOG_DATA_CACHE_VERSION,
            'supplier_ids' => $selectedSupplierIds, // تغییر مهم: استفاده از supplier_ids به جای supplier_id
            'settings' => $this->settings,
            'selected_prices' => $this->selectedPriceTypes,
            'products_updated' => $productsUpdatedAt,
            'prices_updated' => $pricesUpdatedAt,
            'supplier_updated' => $supplierUpdatedAt,
            'categories_updated' => $categoriesUpdatedAt,
            'attributes_updated' => $attributesUpdatedAt,
            'images_updated' => $imagesUpdatedAt,
        ]));
        
        // تلاش برای دریافت از کش
        $cached = Cache::get($cacheKey);
        if ($cached && isset($cached['grouped_products'])) {
            $this->catalogDataCacheKey = $cacheKey;
            $this->catalogSnapshotBusy = false;
            $this->applyCatalogSnapshotWarning($cached);
            $this->updateProgress(30, 'داده‌ها از کش بازیابی شدند');
            return $cached;
        }

        $releaseBuildLock = $this->acquireCatalogSnapshotBuildLock($cacheKey);
        if ($releaseBuildLock === null) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached) && isset($cached['grouped_products'])) {
                $this->catalogDataCacheKey = $cacheKey;
                $this->catalogSnapshotBusy = false;
                $this->applyCatalogSnapshotWarning($cached);

                return $cached;
            }

            $this->catalogDataCacheKey = $cacheKey;
            $this->catalogSnapshotBusy = true;

            return [];
        }

        try {
            // Another request may have finished the same cold build while this
            // request was waiting for the shared lock.
            $cached = Cache::get($cacheKey);
            if (is_array($cached) && isset($cached['grouped_products'])) {
                $this->catalogDataCacheKey = $cacheKey;
                $this->catalogSnapshotBusy = false;
                $this->applyCatalogSnapshotWarning($cached);

                return $cached;
            }

            $this->catalogDataCacheKey = $cacheKey;
            $this->catalogSnapshotBusy = false;

        $categoryIds = $this->settings['categories']
            ?? $this->template->selected_categories
            ?? 'all';

        $this->updateProgress(10, 'در حال فیلتر کردن محصولات...');

        // ساخت کوئری اصلی برای دریافت محصولات یونیک
        $query = Product::query()
            ->where('status', ProductStatus::ACTIVE->value);

        // فیلتر بر اساس تامین‌کنندگان
        if (!empty($selectedSupplierIds) && is_array($selectedSupplierIds)) {
            $query->whereHas('productSuppliers', function ($q) use ($selectedSupplierIds) {
                $q->whereIn('supplier_id', $selectedSupplierIds)
                    ->where('is_active', true)
                    ->whereHas('prices', function ($priceQuery) {
                        $priceQuery->where('is_active', true);
                    });
            });
        } elseif ($supplierId) {
            $query->whereHas('productSuppliers', function ($q) use ($supplierId) {
                $q->where('supplier_id', $supplierId)
                    ->where('is_active', true)
                    ->whereHas('prices', function ($priceQuery) {
                        $priceQuery->where('is_active', true);
                    });
            });
        }

        // فیلتر بر اساس دسته‌بندی
        if ($categoryIds !== 'all' && is_array($categoryIds) && count($categoryIds) > 0) {
            $query->whereIn('category_id', $categoryIds);
        }

        $this->updateProgress(15, 'در حال دریافت اطلاعات محصولات...');

        // دریافت همه محصولات یونیک با روابط مورد نیاز
        // بهینه‌سازی: اضافه کردن attributeValues.attribute برای جلوگیری از N+1
        $products = $query->with([
            'category',
            'primaryImage',
            'attributeValues.attribute', // eager load برای جلوگیری از N+1
            'familyRoot.category',
            'familyRoot.primaryImage',
            'familyRoot.attributeValues.attribute',
            'productSuppliers' => function ($q) use ($selectedSupplierIds, $supplierId, $isAdmin) {
                if (!empty($selectedSupplierIds) && is_array($selectedSupplierIds)) {
                    $q->whereIn('supplier_id', $selectedSupplierIds);
                } elseif ($supplierId) {
                    $q->where('supplier_id', $supplierId);
                } elseif (!$isAdmin) {
                    $q->whereRaw('1 = 0');
                }

                    $q->where('is_active', true)
                    ->with(['supplier:id,company_name,branch_name,parent_supplier_id,city,pricing_currency', 'prices' => function ($priceQuery) {
                        $priceQuery->where('is_active', true)
                            ->with('branchSourcePrice.productSupplier');
                    }]);
            }
        ])->get();

        $this->updateProgress(18, 'در حال پردازش محصولات...');

        // ساخت آرایه محصولات با محاسبه کمترین قیمت از همه تامین‌کنندگان
        $allItems = [];
        // Resolve price types and check periods once, outside the product/tier loops.
        // These lookups used to repeat for every product and caused cold-cache N+1 queries.
        $priceTypes = \App\Models\PriceType::query()
            ->ordered()
            ->get(['id', 'code', 'name', 'is_active']);
        $priceTypesById = $priceTypes->keyBy('id');
        $allActivePriceTypeIds = $priceTypes
            ->where('is_active', true)
            ->where('code', '!=', 'warranty')
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->all();
        $warrantyPriceTypeId = $priceTypes->firstWhere('code', 'warranty')?->id;

        $selectedPriceTypeIds = $this->selectedPriceTypes === 'all'
            ? $allActivePriceTypeIds
            : collect($this->selectedPriceTypes ?? [])
                ->flatten()
                ->map(static fn ($id) => (int) $id)
                ->unique()
                ->filter(static function (int $id) use ($priceTypesById): bool {
                    $priceType = $priceTypesById->get($id);

                    return $priceType !== null
                        && $priceType->is_active
                        && $priceType->code !== 'warranty';
                })
                ->values()
                ->all();
        $usesCheckPrices = collect($selectedPriceTypeIds)->contains(
            fn ($priceTypeId) => ($priceTypesById[$priceTypeId]?->code ?? null) === 'check'
        );
        $checkPeriodsBySupplier = collect();
        if ($usesCheckPrices) {
            $supplierIds = $products
                ->flatMap(static fn ($product) => $product->productSuppliers->pluck('supplier_id'))
                ->filter()
                ->unique()
                ->map(static fn ($id): int => (int) $id)
                ->values();

            $periodSupplierIds = $supplierIds;
            $primaryBranchSupplierId = null;
            if (!$isAdmin && $selectedSupplierIds !== []) {
                $primaryBranchSupplierId = (int) $selectedSupplierIds[0];
                if (count($selectedSupplierIds) === 1) {
                    $parentSupplierId = (int) (\App\Models\Supplier::query()
                        ->whereKey($primaryBranchSupplierId)
                        ->value('parent_supplier_id') ?? 0);
                    if ($parentSupplierId > 0) {
                        $primaryBranchSupplierId = $parentSupplierId;
                    }
                }

                $periodSupplierIds = $periodSupplierIds
                    ->push((int) $primaryBranchSupplierId)
                    ->unique()
                    ->values();
            }

            $checkPeriodsBySupplier = \App\Models\CheckPeriod::getActiveForSuppliers($periodSupplierIds->all())
                ->groupBy(static fn ($period) => (int) $period->resolved_supplier_id)
                ->map(static fn ($periods) => $periods->values());

            if (!$isAdmin && $primaryBranchSupplierId) {
                $primaryPeriods = $checkPeriodsBySupplier->get((int) $primaryBranchSupplierId, collect());
                foreach ($supplierIds as $branchSupplierId) {
                    if ((int) $branchSupplierId === (int) $primaryBranchSupplierId) {
                        continue;
                    }

                    // Branch price sync preserves the source check_period_id. Keep
                    // enabled primary periods available for those copied matrix rows,
                    // while retaining branch-owned periods and labels as well.
                    // Eloquent Collection::merge() keys models by primary key, so
                    // branch periods must be merged last to keep the branch pivot label.
                    $branchPeriods = $checkPeriodsBySupplier->get($branchSupplierId, collect());
                    $checkPeriodsBySupplier[$branchSupplierId] = $primaryPeriods
                        ->merge($branchPeriods)
                        ->unique('id')
                        ->sortBy('days')
                        ->values();
                }
            }
        }
        $totalProducts = $products->count();
        $processedProducts = 0;

        foreach ($products as $product) {
            $processedProducts++;
            
            // هر 10 محصول progress را بروز کن
            if ($processedProducts % 10 === 0) {
                $percent = 18 + (($processedProducts / $totalProducts) * 7); // 18% تا 25%
                $this->updateProgress($percent, "پردازش محصول {$processedProducts} از {$totalProducts}...");
            }

            $sourceProduct = $product;
            $sourceProductSuppliers = $sourceProduct->productSuppliers;
            if ($branchAware) {
                $familyRoot = $sourceProduct->familyRoot;
                if ($familyRoot && $familyRoot->is_active && $familyRoot->status === ProductStatus::ACTIVE) {
                    $product = $familyRoot;
                }
            }

            $catName = $product->category?->name ?? 'بدون دسته‌بندی';

            // محاسبه قیمت‌ها از price_matrix با ساختار جدید
            $prices = [];
            $pricesBySupplier = [];
            
            // دریافت تیراژهای انتخاب شده
            $selectedTiers = [];
            $selectedPriceTypesByTier = [];
            
            if ($this->selectedPriceTypes === 'all') {
                // همه تیراژها (0-3) با همه انواع قیمت
                $selectedTiers = [0, 1, 2, 3];
                $allPriceTypes = $allActivePriceTypeIds;
                foreach ($selectedTiers as $tierIndex) {
                    $selectedPriceTypesByTier[$tierIndex] = $allPriceTypes;
                }
            } elseif (is_array($this->selectedPriceTypes)) {
                // ساختار: ['tier_index' => [price_type_ids]]
                $selectedTiers = array_keys($this->selectedPriceTypes);
                $selectedPriceTypesByTier = collect($this->selectedPriceTypes)
                    ->map(static fn ($priceTypeIds) => collect($priceTypeIds)
                        ->map(static fn ($id) => (int) $id)
                        ->filter(static fn (int $id) => in_array($id, $allActivePriceTypeIds, true))
                        ->values()
                        ->all())
                    ->all();
            }
            
            // ✅ برای هر تامین‌کننده، قیمت‌های این محصول را از جدول product_prices بخوان
            foreach ($sourceProductSuppliers as $ps) {
                if ($branchAware) {
                    $prices = [];
                }

                // دریافت قیمت‌های این product_supplier از relation
                $productPrices = $ps->prices; // Collection از ProductPrice
                
                if ($productPrices->isEmpty()) {
                    continue;
                }
                
                // گروه‌بندی قیمت‌ها بر اساس tier_index و price_type_id
                // کلید: "{tier_index}_{price_type_id}_{check_period_id}"
                $pricesGrouped = $productPrices->groupBy(function($price) {
                    return $price->tier_index . '_' . $price->price_type_id . '_' . ($price->check_period_id ?? 'null');
                });
                
                // برای هر تیراژ انتخاب شده
                foreach ($selectedTiers as $tierIndex) {
                    // برای هر نوع قیمت انتخاب شده برای این تیراژ
                    $priceTypesForThisTier = $selectedPriceTypesByTier[$tierIndex] ?? [];
                    
                    foreach ($priceTypesForThisTier as $priceTypeId) {
                        // کلید برای قیمت نقدی یا گارانتی (بدون check_period)
                        $key = "{$tierIndex}_{$priceTypeId}_null";
                        
                        // یافتن قیمت در collection
                        $priceRecord = $pricesGrouped->get($key)?->first();
                        
                        if ($priceRecord && $priceRecord->price > 0) {
                            // کلید برای ذخیره در آرایه prices: tier_index + price_type_id
                            $storeKey = "{$tierIndex}_{$priceTypeId}";
                            
                            // انتخاب کمترین قیمت برای این ترکیب (در صورت چند تامین‌کننده)
                            if (!isset($prices[$storeKey]) || $priceRecord->price < ($prices[$storeKey]['price'] ?? PHP_INT_MAX)) {
                                // ✅ محاسبه قیمت گارانتی از product_supplier
                                $warrantyPrice = 0;
                                $warrantyType = null;
                                $warrantyDuration = null;
                                
                                if ($ps->has_warranty && $ps->warranty_type !== 'none') {
                                    $warrantyType = $ps->warranty_type_coverage; // test, limited, lifetime
                                    $warrantyDuration = $ps->warranty_duration_days ? (int)($ps->warranty_duration_days / 30) : null; // تبدیل روز به ماه
                                    
                                    // محاسبه هزینه گارانتی
                                    switch ($ps->warranty_type) {
                                        case 'percentage':
                                            // درصدی از قیمت نقدی
                                            $warrantyPrice = (int)($priceRecord->price * ($ps->warranty_value / 100));
                                            break;
                                        case 'fixed':
                                            // مبلغ ثابت برای همه تیراژها
                                            $warrantyPrice = (int)$ps->warranty_value;
                                            break;
                                        case 'custom':
                                            // ✅ مبلغ متفاوت برای هر تیراژ - باید از product_prices بخوانیم
                                            // پیدا کردن price_type با code = warranty
                                            if ($warrantyPriceTypeId) {
                                                $warrantyKey = "{$tierIndex}_{$warrantyPriceTypeId}_null";
                                                $warrantyRecord = $pricesGrouped->get($warrantyKey)?->first();
                                                $warrantyPrice = $warrantyRecord ? (int)$warrantyRecord->price : 0;
                                            }
                                            break;
                                    }
                                }
                                
                                $prices[$storeKey] = [
                                    'price' => (int)$priceRecord->price,
                                    ...$this->catalogPriceCurrencyMetadata($priceRecord, $ps),
                                    'tier_index' => (int)$tierIndex,
                                    'price_type_id' => (int)$priceTypeId,
                                    'warranty_type' => $warrantyType,
                                    'warranty_duration' => $warrantyDuration,
                                    'warranty_price' => $warrantyPrice,
                                ];
                            }
                        }
                        
                        // ✅ اگر نوع قیمت "چک" است، قیمت‌های چکی را هم بررسی کن
                        $priceType = $priceTypesById[$priceTypeId] ?? null;
                        if (($priceType?->code ?? null) === 'check') {
                            // ✅ دریافت دوره‌های چک انتخاب شده برای این تیراژ
                            $selectedCheckPeriodsForTier = $this->selectedCheckPeriods[$tierIndex] ?? [];
                            
                            $activeSupplierPeriods = $checkPeriodsBySupplier[(int) $ps->supplier_id] ?? collect();
                            if (!empty($selectedCheckPeriodsForTier)) {
                                // Intersect selected periods with the supplier's preloaded active periods.
                                $activePeriodIds = $activeSupplierPeriods->pluck('id')->all();
                                $validPeriodIds = array_intersect($selectedCheckPeriodsForTier, $activePeriodIds);
                                $checkPeriods = $activeSupplierPeriods
                                    ->whereIn('id', $validPeriodIds)
                                    ->sortBy('days')
                                    ->values();
                            } else {
                                $checkPeriods = $activeSupplierPeriods;
                            }
                            
                            foreach ($checkPeriods as $period) {
                                $checkKey = "{$tierIndex}_{$priceTypeId}_{$period->id}";
                                $checkPriceRecord = $pricesGrouped->get($checkKey)?->first();
                                
                                if ($checkPriceRecord && $checkPriceRecord->price > 0) {
                                    // کلید برای ذخیره: tier_index + price_type_id + check_period_id
                                    $checkStoreKey = "{$tierIndex}_{$priceTypeId}_{$period->id}";
                                    
                                    if (!isset($prices[$checkStoreKey]) || $checkPriceRecord->price < ($prices[$checkStoreKey]['price'] ?? PHP_INT_MAX)) {
                                        // ✅ محاسبه قیمت گارانتی از product_supplier
                                        $warrantyPrice = 0;
                                        $warrantyType = null;
                                        $warrantyDuration = null;
                                        
                                        if ($ps->has_warranty && $ps->warranty_type !== 'none') {
                                            $warrantyType = $ps->warranty_type_coverage;
                                            $warrantyDuration = $ps->warranty_duration_days ? (int)($ps->warranty_duration_days / 30) : null;
                                            
                                            switch ($ps->warranty_type) {
                                                case 'percentage':
                                                    $warrantyPrice = (int)($checkPriceRecord->price * ($ps->warranty_value / 100));
                                                    break;
                                                case 'fixed':
                                                    $warrantyPrice = (int)$ps->warranty_value;
                                                    break;
                                                case 'custom':
                                                    if ($warrantyPriceTypeId) {
                                                        $warrantyKey = "{$tierIndex}_{$warrantyPriceTypeId}_null";
                                                        $warrantyRecord = $pricesGrouped->get($warrantyKey)?->first();
                                                        $warrantyPrice = $warrantyRecord ? (int)$warrantyRecord->price : 0;
                                                    }
                                                    break;
                                            }
                                        }
                                        
                                        $prices[$checkStoreKey] = [
                                            'price' => (int)$checkPriceRecord->price,
                                            ...$this->catalogPriceCurrencyMetadata($checkPriceRecord, $ps),
                                            'tier_index' => (int)$tierIndex,
                                            'price_type_id' => (int)$priceTypeId,
                                            'check_period_id' => (int)$period->id,
                                            'check_period_days' => (int)$period->days,
                                            'check_period_name' => (string) $period->name,
                                            'check_period_label' => $period->display_name ?? $period->name,
                                            'warranty_type' => $warrantyType,
                                            'warranty_duration' => $warrantyDuration,
                                            'warranty_price' => $warrantyPrice,
                                        ];
                                    }
                                }
                            }
                        }
                    }
                }

                if ($branchAware && $prices !== []) {
                    $pricesBySupplier[(int) $ps->supplier_id] = $prices;
                }
            }

            if ($branchAware) {
                $prices = [];
                foreach ($pricesBySupplier as $supplierPrices) {
                    foreach ($supplierPrices as $priceKey => $priceData) {
                        if (!isset($prices[$priceKey])
                            || (float) ($priceData['price'] ?? 0) < (float) ($prices[$priceKey]['price'] ?? PHP_INT_MAX)) {
                            $prices[$priceKey] = $priceData;
                        }
                    }
                }
            }

            // اگر هیچ قیمتی نداشت، این محصول رو نادیده بگیر
            if (empty($prices)) {
                continue;
            }
            
            // دریافت مقدار attribute عمومی و اختصاصی
            // بهینه‌سازی: استفاده از eager loaded data برای جلوگیری از N+1
            $attributeValues = [];
            $pdfAttributeValues = [];
            $categoryId = $product->category_id;
            $showLabels = $this->settings['show_attribute_labels'] ?? true;
            
            // Helper function برای fix کردن جهت متن در PDF
            $fixTextDirection = function($text) {
                // اضافه کردن Left-to-Right Mark برای کنترل جهت پرانتزها
                return "\u{200E}" . $text . "\u{200E}";
            };
            
            // ابتدا مشخصه عمومی - استفاده از collection به جای کوئری
            if (!empty($this->settings['global_attribute'])) {
                $attrId = $this->settings['global_attribute'];
                // استفاده از eager loaded attributeValues
                $attrValue = $product->attributeValues->firstWhere('attribute_id', $attrId);
                if ($attrValue) {
                    // استفاده از eager loaded attribute relation
                    $attrName = $attrValue->attribute?->name ?? '';
                    if ($showLabels) {
                        $attributeValues[] = $fixTextDirection('(' . $attrName . ': ' . $attrValue->value . ')');
                        $pdfAttributeValues[] = self::formatPdfAttribute($attrName, (string) $attrValue->value);
                    } else {
                        $attributeValues[] = $fixTextDirection('(' . $attrValue->value . ')');
                        $pdfAttributeValues[] = self::formatPdfAttribute(null, (string) $attrValue->value);
                    }
                }
            }
            
            // سپس مشخصات اختصاصی دسته‌بندی - استفاده از collection به جای کوئری
            if (!empty($this->settings['category_attributes'][$categoryId])) {
                $categoryAttrs = $this->settings['category_attributes'][$categoryId];
                
                foreach ($categoryAttrs as $attrId) {
                    // استفاده از eager loaded attributeValues
                    $attrValue = $product->attributeValues->firstWhere('attribute_id', $attrId);
                    if ($attrValue) {
                        // استفاده از eager loaded attribute relation
                        $attrName = $attrValue->attribute?->name ?? '';
                        if ($showLabels) {
                            $attributeValues[] = $fixTextDirection('(' . $attrName . ': ' . $attrValue->value . ')');
                            $pdfAttributeValues[] = self::formatPdfAttribute($attrName, (string) $attrValue->value);
                        } else {
                            $attributeValues[] = $fixTextDirection('(' . $attrValue->value . ')');
                            $pdfAttributeValues[] = self::formatPdfAttribute(null, (string) $attrValue->value);
                        }
                    }
                }
            }
            
            $attributeValue = !empty($attributeValues) ? implode(' ', $attributeValues) : null;

            // دریافت کیفیت تصویر از تنظیمات
            $imageQuality = $this->settings['image_quality'] ?? 'sm';
            $imagePath = null;
            
            if ($product->primaryImage) {
                // اگر کیفیت original انتخاب شده باشد
                if ($imageQuality === 'original') {
                    $imagePath = $product->primaryImage->file_path;
                } else {
                    // استفاده از version مورد نظر
                    $imagePath = $product->primaryImage->getVersionPath($imageQuality) 
                                ?? $product->primaryImage->file_path;
                }
            }

            $item = [
                'id'               => $product->id,
                'title'            => $product->title,
                'sku'              => $product->sku,
                'image'            => $imagePath,
                'brand'            => $product->brand,
                'category_name'    => $catName,
                'category_order'   => $product->category?->sort_order ?? 0,
                'prices'           => $branchAware ? [] : $prices,
                'created_at'       => $product->created_at?->timestamp ?? 0,
                'price_updated_at' => $sourceProductSuppliers->max('price_updated_at')?->timestamp ?? 0,
                'attribute_value'  => $attributeValue,
                'attribute_value_pdf' => !empty($pdfAttributeValues) ? implode(' ', $pdfAttributeValues) : null,
            ];

            if (!$branchAware) {
                $allItems[] = $item;
                continue;
            }

            $familyId = (int) ($sourceProduct->family_product_id ?: $sourceProduct->id);
            if (!isset($allItems[$familyId])) {
                $item['branch_prices'] = [];
                foreach ($selectedSupplierIds as $selectedBranchId) {
                    $item['branch_prices'][(int) $selectedBranchId] = [
                        'label' => $branchLabels[(int) $selectedBranchId] ?? 'شهر ثبت نشده',
                        'prices' => [],
                    ];
                }
                $allItems[$familyId] = $item;
            } else {
                $allItems[$familyId]['price_updated_at'] = max(
                    (int) ($allItems[$familyId]['price_updated_at'] ?? 0),
                    (int) ($item['price_updated_at'] ?? 0),
                );
            }

            foreach ($pricesBySupplier as $branchId => $branchPrices) {
                if (!isset($allItems[$familyId]['branch_prices'][$branchId])) {
                    continue;
                }

                $allItems[$familyId]['branch_prices'][$branchId]['prices'] = $branchPrices;
                foreach ($branchPrices as $priceKey => $priceData) {
                    if (!isset($allItems[$familyId]['prices'][$priceKey])
                        || (float) ($priceData['price'] ?? 0) < (float) ($allItems[$familyId]['prices'][$priceKey]['price'] ?? PHP_INT_MAX)) {
                        $allItems[$familyId]['prices'][$priceKey] = $priceData;
                    }
                }
            }

        }

        // Branch families are keyed by family ID while they are being merged.
        // Reindex before rendering so row numbers and array_chunk remain sequential.
        $allItems = array_values($allItems);

        // پردازش تنظیمات سورت - پشتیبانی از هر دو فرمت قدیم و جدید
        $sorts = $this->settings['sort'] ?? [];
        
        // اگر فرمت جدید خالی بود، از فرمت قدیمی استفاده کن
        if (empty($sorts)) {
            $sorts = [];
            for ($i = 1; $i <= 3; $i++) {
                $field = $this->settings["sort_level{$i}"] ?? '';
                $dir = $this->settings["sort_level{$i}_dir"] ?? 'asc';
                
                if ($field !== '' && $field !== null) {
                    $sorts[] = ['field' => $field, 'dir' => $dir];
                }
            }
        }
        
        if (!empty($sorts)) {
            usort($allItems, function ($a, $b) use ($sorts) {
                foreach ($sorts as $sort) {
                    $field = $sort['field'] ?? null;
                    if (!$field) continue;
                    $dir = strtolower($sort['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
                    $result = $this->compareItems($a, $b, $field, $dir);
                    if ($result !== 0) return $result;
                }
                return 0;
            });
        }


        $grouped = [];
        if ($this->separateCategories) {
            foreach ($allItems as $item) {
                $grouped[$item['category_name']][] = $item;
            }
        } else {
            $grouped['all'] = $allItems;
        }

        $this->catalogDataCacheKey = $cacheKey;
        
        // ذخیره در کش برای استفاده‌های بعدی (2 ساعت)
        $snapshot = [
            'grouped_products' => $grouped,
            'price_types_by_id' => $priceTypes->where('is_active', true)->mapWithKeys(static fn ($priceType): array => [
                (int) $priceType->id => [
                    'code' => (string) $priceType->code,
                    'name' => (string) $priceType->name,
                    'is_active' => true,
                ],
            ])->all(),
            'generated_at' => now()->toDateTimeString(),
        ];
        $this->applyCatalogSnapshotWarning($snapshot, false);
        Cache::put($cacheKey, $snapshot, now()->addHours(2));
        
        $this->updateProgress(30, 'داده‌ها آماده شدند، در حال تولید PDF...');

        return $snapshot;
            } finally {
                $releaseBuildLock();
            }
    }

    /**
     * @return array{supplier_ids:array<int, int>,cache_key:string}
     */
    private function catalogCacheIdentity(?int $supplierId): array
    {
        $isAdmin = $this->authenticatedUserIsAdmin();
        if (!$isAdmin) {
            $supplierId = $this->authorizedSupplierScope();
        }

        $selectedSupplierIds = [];
        if ($isAdmin && !empty($this->template->config['supplier_ids']) && is_array($this->template->config['supplier_ids'])) {
            $selectedSupplierIds = array_values(array_unique(array_map('intval', $this->template->config['supplier_ids'])));
        } elseif ($supplierId) {
            $selectedSupplierIds = $this->catalogBranchSupplierIds((int) $supplierId);
        }

        $revisionSupplierIds = $selectedSupplierIds;
        if ($selectedSupplierIds !== []) {
            $sourceSupplierIds = \App\Models\Supplier::query()
                ->whereIn('id', $selectedSupplierIds)
                ->whereNotNull('parent_supplier_id')
                ->pluck('parent_supplier_id')
                ->map(static fn ($id): int => (int) $id)
                ->all();
            $revisionSupplierIds = array_values(array_unique([...$selectedSupplierIds, ...$sourceSupplierIds]));
        }

        $sourceRevision = app(\App\Services\CatalogSnapshotRevisionService::class)
            ->signatureFor($revisionSupplierIds);
        $activePriceTypeIds = \App\Models\PriceType::query()
            ->active()
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
        $catalogVersion = Cache::get('catalog_version_' . $this->template->id, 0);
        $cacheKey = 'catalog_' . $this->template->id . '_' . md5(json_encode([
            'snapshot_schema' => 5,
            'supplier_ids' => $selectedSupplierIds,
            'active_price_type_ids' => $activePriceTypeIds,
            'settings' => $this->settings,
            'selected_prices' => $this->selectedPriceTypes,
            'source_revision' => $sourceRevision,
            'catalog_version' => $catalogVersion,
            'display_currency_code' => $this->displayCurrencyCode,
        ]));

        return ['supplier_ids' => $selectedSupplierIds, 'cache_key' => $cacheKey];
    }

    private function refreshCatalogSnapshotIfStale(?int $supplierId): void
    {
        $currentCacheKey = $this->catalogCacheIdentity($supplierId)['cache_key'];
        if ($currentCacheKey !== $this->catalogDataCacheKey) {
            $this->loadCatalogData($supplierId);
        }
    }

    private function acquireCatalogSnapshotBuildLock(string $cacheKey): ?\Closure
    {
        $waitSeconds = max(0, (int) config('catalog.snapshot_lock_wait_seconds', 3));
        $connection = DB::connection();

        if ($connection->getDriverName() === 'mysql') {
            // MySQL releases named locks automatically when the connection dies,
            // so an interrupted snapshot build cannot leave a long cache lease.
            $lockName = 'catalog_snapshot_' . substr(hash('sha256', $cacheKey), 0, 40);
            $result = $connection->selectOne('SELECT GET_LOCK(?, ?) AS acquired', [$lockName, $waitSeconds]);

            if ((int) ($result->acquired ?? 0) !== 1) {
                return null;
            }

            return static function () use ($connection, $lockName): void {
                $connection->selectOne('SELECT RELEASE_LOCK(?) AS released', [$lockName]);
            };
        }

        // SQLite and other local/test drivers keep the portable cache-lock path.
        $leaseSeconds = max(120, (int) config('catalog.snapshot_lock_lease_seconds', 120));
        $lock = Cache::lock('catalog_snapshot_build_' . $cacheKey, $leaseSeconds);

        try {
            $lock->block($waitSeconds);
        } catch (LockTimeoutException) {
            return null;
        }

        return static function () use ($lock): void {
            $lock->release();
        };
    }

    private function catalogPriceCurrencyMetadata(ProductPrice $price, ProductSupplier $offer): array
    {
        // A copied branch's Toman price is derived from its source matrix.
        // Use that source snapshot for freshness checks, while formatting the
        // catalog in the selected supplier's current display currency/rate.
        $snapshotOffer = $price->branchSourcePrice?->productSupplier ?? $offer;
        $displayCurrency = $this->displayCurrencyCode;
        $displayRate = $displayCurrency !== null
            && $displayCurrency !== 'TOM'
            && strtoupper((string) ($this->exchangeRateBasis['currency'] ?? '')) === $displayCurrency
            && is_numeric($this->exchangeRateBasis['rate_value'] ?? null)
            ? (string) $this->exchangeRateBasis['rate_value']
            : null;

        return [
            'foreign_price_rate' => $snapshotOffer->foreign_price_rate,
            'foreign_price_applied_currency' => $snapshotOffer->foreign_price_applied_currency,
            'price_snapshot_supplier_id' => (int) ($snapshotOffer->supplier_id ?: $offer->supplier_id),
            'catalog_display_currency' => $displayCurrency,
            'catalog_display_rate' => $displayRate,
            'catalog_display_valid' => $displayCurrency === 'TOM' || $displayRate !== null,
        ];
    }

    private function refreshCurrencySnapshotWarning(array &$groupedProducts): void
    {
        $this->currencySnapshotWarning = false;

        if (!$this->displayCurrencyCode) {
            return;
        }

        $configuredCurrency = $this->exchangeRateBasis['currency'] ?? null;
        $configuredRate = $this->exchangeRateBasis['rate_value'] ?? null;

        $displayBasisValid = $this->displayCurrencyCode === 'TOM'
            || ($configuredCurrency === $this->displayCurrencyCode
                && is_numeric($configuredRate)
                && (float) $configuredRate > 0);

        if (!$displayBasisValid) {
            $this->currencySnapshotWarning = true;
        }

        $snapshotSupplierIds = [];
        foreach ($groupedProducts as $items) {
            foreach ($items as $item) {
                $priceMaps = isset($item['branch_prices'])
                    ? array_map(static fn (array $branch): array => $branch['prices'] ?? [], $item['branch_prices'])
                    : [($item['prices'] ?? [])];

                foreach ($priceMaps as $priceMap) {
                    foreach ($priceMap as $priceData) {
                        $supplierId = (int) ($priceData['price_snapshot_supplier_id'] ?? 0);
                        if ($supplierId > 0) {
                            $snapshotSupplierIds[$supplierId] = true;
                        }
                    }
                }
            }
        }

        $snapshotSuppliers = $snapshotSupplierIds === []
            ? collect()
            : \App\Models\Supplier::query()
                ->whereIn('id', array_keys($snapshotSupplierIds))
                ->get(['id', 'pricing_currency'])
                ->keyBy('id');
        $snapshotBases = $snapshotSuppliers->isEmpty()
            ? collect()
            : app(\App\Services\CatalogExchangeRateBasisResolver::class)
                ->resolveMany($snapshotSuppliers->values());

        foreach ($groupedProducts as &$items) {
            foreach ($items as &$item) {
                if (isset($item['branch_prices']) && is_array($item['branch_prices'])) {
                    foreach ($item['branch_prices'] as &$branch) {
                        if (!isset($branch['prices']) || !is_array($branch['prices'])) {
                            $branch['prices'] = [];
                        }

                        foreach ($branch['prices'] as &$priceData) {
                            $this->validateCatalogPriceSnapshot($priceData, $snapshotSuppliers, $snapshotBases, $displayBasisValid);
                        }
                        unset($priceData);
                    }
                    unset($branch);
                    continue;
                }

                if (!isset($item['prices']) || !is_array($item['prices'])) {
                    $item['prices'] = [];
                }

                foreach ($item['prices'] as &$priceData) {
                    $this->validateCatalogPriceSnapshot($priceData, $snapshotSuppliers, $snapshotBases, $displayBasisValid);
                }
                unset($priceData);
            }
            unset($item);
        }
        unset($items);
    }

    private function validateCatalogPriceSnapshot(
        mixed &$priceData,
        \Illuminate\Support\Collection $snapshotSuppliers,
        \Illuminate\Support\Collection $snapshotBases,
        bool $displayBasisValid,
    ): void {
        if (!is_array($priceData)) {
            return;
        }

        $supplierId = (int) ($priceData['price_snapshot_supplier_id'] ?? 0);
        $supplierCurrency = strtoupper((string) ($snapshotSuppliers->get($supplierId)?->pricing_currency ?? ''));
        $snapshotCurrency = strtoupper((string) ($priceData['foreign_price_applied_currency'] ?? ''));
        $snapshotRate = $priceData['foreign_price_rate'] ?? null;
        $snapshotIsCurrent = false;

        if ($supplierCurrency === 'TOM') {
            $snapshotIsCurrent = $snapshotCurrency === ''
                && (!is_numeric($snapshotRate) || (float) $snapshotRate <= 0);
        } else {
            $currentBasis = $snapshotBases->get($supplierId);
            if ($supplierId < 1) {
                $currentBasis = $this->exchangeRateBasis;
            }

            $currentCurrency = strtoupper((string) ($currentBasis['currency'] ?? ''));
            $currentRate = $currentBasis['rate_value'] ?? null;
            $snapshotIsCurrent = $currentCurrency !== ''
                && $snapshotCurrency === $currentCurrency
                && is_numeric($snapshotRate)
                && (float) $snapshotRate > 0
                && is_numeric($currentRate)
                && abs((float) $snapshotRate - (float) $currentRate) < 0.00005;
        }

        if (!$snapshotIsCurrent) {
            $this->currencySnapshotWarning = true;
        }

        if ($this->displayCurrencyCode !== 'TOM') {
            $priceData['catalog_display_valid'] = $displayBasisValid && $snapshotIsCurrent;
        }
    }

    /**
     * Reuse the warning stored with a catalog snapshot. The full product walk is
     * only repeated when its currency/rate basis changes.
     *
     * @param array<string, mixed> $snapshot
     */
    private function applyCatalogSnapshotWarning(array &$snapshot, bool $persist = true): void
    {
        $signature = hash('sha256', (string) json_encode([
            'display_currency' => $this->displayCurrencyCode,
            'basis_currency' => $this->exchangeRateBasis['currency'] ?? null,
            'basis_rate' => $this->exchangeRateBasis['rate_value'] ?? null,
        ]));

        if (($snapshot['exchange_rate_basis_signature'] ?? null) === $signature
            && array_key_exists('currency_snapshot_warning', $snapshot)) {
            $this->currencySnapshotWarning = (bool) $snapshot['currency_snapshot_warning'];

            return;
        }

        $groupedProducts = $snapshot['grouped_products'] ?? [];
        $this->refreshCurrencySnapshotWarning($groupedProducts);
        $snapshot['grouped_products'] = $groupedProducts;
        $snapshot['exchange_rate_basis_signature'] = $signature;
        $snapshot['currency_snapshot_warning'] = $this->currencySnapshotWarning;

        if ($persist && $this->catalogDataCacheKey) {
            Cache::put($this->catalogDataCacheKey, $snapshot, now()->addHours(2));
        }
    }

    private static function formatPdfAttribute(?string $label, string $value): string
    {
        $rtlMark = "\u{200F}";
        $text = $label !== null ? self::withPdfLtrMark($label) . ': ' : '';

        return $rtlMark . '(' . $text . self::withPdfLtrMark($value) . ')' . $rtlMark;
    }

    private static function withPdfLtrMark(string $text): string
    {
        $ltrMark = "\u{200E}";

        return preg_replace_callback(
            '~[A-Za-z][A-Za-z0-9._:+/-]*~',
            static fn (array $matches): string => $ltrMark . $matches[0] . $ltrMark,
            $text,
        ) ?? $text;
    }

    private function compareItems($a, $b, $sortKey, $dir)
    {
        if (!$sortKey) return 0;
        $valA = $this->getSortValue($a, $sortKey);
        $valB = $this->getSortValue($b, $sortKey);

        if (is_numeric($valA) && is_numeric($valB)) {
            if ($valA == $valB) return 0;
            $result = ($valA < $valB) ? -1 : 1;
        } else {
            $result = strcmp((string)$valA, (string)$valB);
        }

        return $dir === 'desc' ? -$result : $result;
    }

    private function getSortValue($item, $sortKey)
    {
        if (str_starts_with($sortKey, 'price_')) {
            $type = str_replace('price_', '', $sortKey);
            $priceData = $item['prices'][$type] ?? null;
            
            if (is_array($priceData)) {
                return $priceData['price'] ?? 0;
            }
            
            return $priceData ?? 0;
        }

        $map = [
            'product_name'   => 'title',
            'category'       => 'category_name',
            'category_order' => 'category_order',
            'sku'            => 'sku',
            'brand'          => 'brand',
            'name'           => 'title',            // کلید فرم ادمین
            'created_at'     => 'created_at',
            'price_updated_at' => 'price_updated_at',
        ];



        $key = $map[$sortKey] ?? $sortKey;
        return $item[$key] ?? '';
    }

    public function downloadPdf()
    {
        $this->authorizeCurrentTemplate();

        // استفاده از progressKey که در mount ساخته شده
        return $this->generatePdfResponse('pdf.supplier-catalog', 'list-');
    }

    public function downloadPdf2()
    {
        $this->authorizeCurrentTemplate();

        // استفاده از progressKey که در mount ساخته شده
        return $this->generatePdfResponse('pdf.supplier-catalog2', 'catalog-');
    }

    /**
     * بروزرسانی درصد پیشرفت در Cache
     */
    private function updateProgress($percent, $message = '', string $status = 'processing')
    {
        // اگر progress غیرفعال است یا progressKey وجود ندارد، کاری نکن
        if (!$this->enableProgress || empty($this->progressKey)) {
            return;
        }
        
        $data = [
            'percent' => $percent,
            'message' => $message,
            'status' => $status,
            'user_id' => Auth::id(),
            'timestamp' => time()
        ];
        
        // وضعیت لحظه‌ای progress را روی فایل بنویس تا polling چندمرحله‌ای PDF
        // روی SQLite/کش دیتابیسی قفل نوشتن ایجاد نکند.
        Cache::store('file')->put($this->progressKey, $data, now()->addMinutes(5));
        
        // حذف تاخیر مصنوعی برای کاهش فشار CPU
        // usleep(100000); // حذف شد - در production نیازی به تاخیر نیست
        
        \Log::info("📈 Progress: {$percent}% - {$message}");
    }

    private function generatePdfResponse($view, $prefix)
    {
        $this->authorizeCurrentTemplate();
        $this->resetErrorBag('pdfExport');
        // mPDF can exceed PHP's common 30-second request limit while rendering Persian fonts.
        set_time_limit(180);
        $fileName = $prefix . jdate(now())->format('Y-m-d') . '.pdf';
        $startedAt = microtime(true);
        $format = $prefix === 'list-' ? 'list' : 'catalog';
        $productCount = 0;
        $this->enableProgress = true;

        try {
            $this->refreshCurrencyDisplayState();
            $supplierId = $this->authenticatedUserIsAdmin() ? null : $this->authorizedSupplierScope();
            $this->refreshCatalogSnapshotIfStale($supplierId);

            $this->updateProgress(3, 'در حال آماده‌سازی اطلاعات کاتالوگ...');
            // Release the session before the expensive work so progress polling
            // can read the latest state while the PDF request is running.
            session()->save();

            if (!$this->catalogDataCacheKey) {
                throw new \RuntimeException('Catalog preview data key is unavailable.');
            }

            // Reuse the preview snapshot when it is still available so normal
            // downloads do not query and regroup every product again.
            $catalogSnapshot = Cache::get($this->catalogDataCacheKey);
            if (!is_array($catalogSnapshot) || !isset($catalogSnapshot['grouped_products'])) {
                $this->updateProgress(8, 'پیش‌نمایش قدیمی شده؛ در حال تازه‌سازی اطلاعات...');
                $this->refreshCurrencyDisplayState();
            }

            if (($this->settings['price_display_currency'] ?? 'toman') === 'supplier'
                && $this->displayCurrencyCode === null) {
                $message = 'خروجی ارزی ممکن نیست؛ ارز پایه و نرخ معتبر همهٔ شعبه‌های انتخاب‌شده باید یکسان باشند. یک شعبه را انتخاب کنید، نرخ ارز شعب را همسان کنید یا نمایش تومان را برگزینید.';
                $this->updateProgress(0, $message, 'failed');
                $this->addError('pdfExport', $message);

                return null;
            }

            if (!is_array($catalogSnapshot) || !isset($catalogSnapshot['grouped_products'])) {
                $catalogSnapshot = $this->loadCatalogData($supplierId);
            }

            if ($this->catalogSnapshotBusy) {
                $message = 'پیش‌نمایش کاتالوگ در حال آماده‌سازی است؛ چند ثانیهٔ دیگر دوباره تلاش کنید.';
                $this->updateProgress(0, $message, 'failed');
                $this->addError('pdfExport', $message);

                return null;
            }

            if (!is_array($catalogSnapshot) || !isset($catalogSnapshot['grouped_products'])) {
                throw new \RuntimeException('Catalog preview data could not be refreshed.');
            }

            $this->applyCatalogSnapshotWarning($catalogSnapshot);

            $groupedProducts = $catalogSnapshot['grouped_products'];
            $productCount = array_sum(array_map('count', $groupedProducts));
            $priceTypesById = \App\Models\PriceType::query()
                ->active()
                ->ordered()
                ->get(['id', 'code', 'name'])
                ->mapWithKeys(static fn (\App\Models\PriceType $priceType): array => [
                    (int) $priceType->id => [
                        'code' => (string) $priceType->code,
                        'name' => (string) $priceType->name,
                    ],
                ])
                ->all();

            $data = [
                'supplierName' => $this->supplierName,
                'groupedProducts' => $groupedProducts,
                'selectedPriceTypes' => app(\App\Services\CatalogPriceTypeSelection::class)
                    ->normalizeForPdf($this->selectedPriceTypes, $priceTypesById),
                'priceLabels' => $this->priceLabels,
                'settings' => $this->settings,
                'template' => $this->template,
                'separateCategories' => $this->separateCategories,
                'showCoverPage' => $this->showCoverPage,
                'supplierLogoUrl' => $this->supplierLogoUrl,
                'supplierLogoPath' => $this->supplierLogoPath,
                'forPdf' => true,
                'supplier' => $this->supplier,
                'contactPhones' => $this->settings['contact_phones'] ?? [],
                'globalAttribute' => $this->settings['global_attribute'] ?? null,
                'coverTheme' => $this->settings['cover_theme'] ?? 'default',
                'catalogFont' => $this->settings['catalog_font']
                    ?? $this->settings['custom_cover_table']['settings']['catalog_font']
                    ?? 'vazir',
                'includeWarranty' => $this->includeWarranty ?? false,
                'displayCurrencyCode' => $this->displayCurrencyCode,
                'exchangeRateBasis' => empty($this->displayCurrencyCode) || $this->displayCurrencyCode === 'TOM'
                    ? null
                    : $this->exchangeRateBasis,
                'currencySnapshotWarning' => $this->currencySnapshotWarning,
                'priceFormatter' => app(\App\Services\CatalogPriceFormatter::class),
                'catalogBranchPriceRows' => app(\App\Services\CatalogBranchPriceRows::class),
                'branchAwareCatalog' => !$this->authenticatedUserIsAdmin()
                    && ($this->settings['catalog_branch_scope'] ?? 'single') === 'all',
                'priceTypesById' => $priceTypesById,
            ];

            $this->updateProgress(35, 'در حال آماده‌سازی قالب PDF...');

            $options = [
                'format' => 'A4',
                'margin_left' => 6,
                'margin_right' => 6,
                'margin_top' => 6,
                'margin_bottom' => 6,
                'mode' => 'utf-8',
                'default_font' => $data['catalogFont'],
            ];

            $this->updateProgress(50, 'در حال رندر کردن صفحات PDF...');
            $bladeStartedAt = microtime(true);
            $html = view($view, $data)->render();
            $bladeDurationMs = (int) round((microtime(true) - $bladeStartedAt) * 1000);
            \Log::info('Catalog PDF Blade render completed.', [
                'format' => $format,
                'product_count' => $productCount,
                'duration_ms' => $bladeDurationMs,
                'html_bytes' => strlen($html),
            ]);

            $parseStartedAt = microtime(true);
            $pdf = PDF::loadHTML($html, $options);
            \Log::info('Catalog PDF HTML parse completed.', [
                'format' => $format,
                'product_count' => $productCount,
                'duration_ms' => (int) round((microtime(true) - $parseStartedAt) * 1000),
            ]);

            $this->updateProgress(75, 'در حال ساخت فایل PDF...');
            $outputStartedAt = microtime(true);
            $pdfOutput = $pdf->output();
            \Log::info('Catalog PDF engine output completed.', [
                'format' => $format,
                'product_count' => $productCount,
                'duration_ms' => (int) round((microtime(true) - $outputStartedAt) * 1000),
                'file_bytes' => strlen($pdfOutput),
            ]);

            $disk = \Illuminate\Support\Facades\Storage::disk('local');
            $downloadDirectory = 'catalog-pdf-downloads';
            $disk->makeDirectory($downloadDirectory);

            $this->pdfDownloadToken = (string) Str::uuid();
            $downloadPath = $downloadDirectory . '/' . $this->pdfDownloadToken . '.pdf';
            if (!$disk->put($downloadPath, $pdfOutput)) {
                throw new \RuntimeException('Catalog PDF could not be staged for browser download.');
            }

            \Illuminate\Support\Facades\Cache::store('file')->put(
                'catalog_pdf_download_' . $this->pdfDownloadToken,
                [
                    'user_id' => (int) Auth::id(),
                    'filename' => $fileName,
                ],
                now()->addMinutes(10),
            );

            $this->updateProgress(100, 'کاتالوگ آمادهٔ دانلود است.', 'completed');
            \Log::info('Catalog PDF synchronous export completed.', [
                'format' => $format,
                'product_count' => $productCount,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'file_bytes' => strlen($pdfOutput),
            ]);

            return redirect()->route('catalog.pdf.download', [
                'token' => $this->pdfDownloadToken,
            ]);
        } catch (\Throwable $exception) {
            $this->updateProgress(0, 'ساخت PDF ناموفق بود. دوباره تلاش کنید.', 'failed');
            \Log::error('Catalog PDF synchronous export failed.', [
                'format' => $format,
                'product_count' => $productCount,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'exception' => $exception,
            ]);
            report($exception);
            $this->addError('pdfExport', 'ساخت PDF ناموفق بود. دوباره تلاش کنید.');

            return null;
        } finally {
            $this->enableProgress = false;
        }
    }

    public function render()
    {
        $this->refreshCurrencyDisplayState();
        $supplierId = $this->authenticatedUserIsAdmin() ? null : $this->authorizedSupplierScope();
        $this->refreshCatalogSnapshotIfStale($supplierId);
        $catalogData = $this->catalogDataCacheKey
            ? (Cache::get($this->catalogDataCacheKey) ?? [])
            : [];
        if (isset($catalogData['grouped_products'])) {
            $this->applyCatalogSnapshotWarning($catalogData);
        }

        return view('livewire.supplier.catalog.preview', [
            'priceFormatter' => app(\App\Services\CatalogPriceFormatter::class),
            'catalogBranchPriceRows' => app(\App\Services\CatalogBranchPriceRows::class),
            'groupedProducts' => $catalogData['grouped_products'] ?? [],
            'priceTypesById' => $catalogData['price_types_by_id'] ?? [],
            'branchAwareCatalog' => !$this->authenticatedUserIsAdmin()
                && ($this->settings['catalog_branch_scope'] ?? 'single') === 'all',
            'previewDataExpired' => !isset($catalogData['grouped_products']),
            'catalogSnapshotBusy' => $this->catalogSnapshotBusy,
        ])
            ->layout('components.layouts.blank');
    }

    public function retryCatalogSnapshot(): void
    {
        $this->authorizeCurrentTemplate();
        $this->refreshCurrencyDisplayState();
        $this->catalogSnapshotBusy = false;
        $this->loadCatalogData($this->authorizedSupplierScope());
    }
    
    /**
     * پاکسازی کش کاتالوگ
     */
    public function clearCatalogCache()
    {
        $this->authorizeCurrentTemplate();

        if ($this->catalogSnapshotBusy) {
            $this->dispatch('notify', [
                'type' => 'warning',
                'message' => 'پیش‌نمایش در حال آماده‌سازی است؛ پس از پایان، دوباره بروزرسانی کنید.',
            ]);

            return;
        }

        $supplierId = $this->authorizedSupplierScope();

        $this->refreshCurrencyDisplayState();
        
        // پاک کردن کش با استفاده از версینینگ
        // یک کلید نسخه جدید می‌سازیم که کلید کش را تغییر دهد
        $versionKey = 'catalog_version_' . $this->template->id;
        $newVersion = (string) Str::uuid();
        Cache::put($versionKey, $newVersion, now()->addDays(30));
        
        // رفرش داده‌ها - چون کلید کش تغییر کرده، داده‌های جدید بارگذاری می‌شوند
        $this->loadCatalogData($supplierId);
        
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'داده‌های کاتالوگ با موفقیت بروزرسانی شد.'
        ]);
    }
    
    /**
     * دریافت مقدار attribute برای یک محصول
     */
    private function getProductAttributeValue($product, $attributeId)
    {
        if (!$attributeId) {
            return null;
        }
        
        // دریافت attribute از محصول
        $attributeValue = $product->attributeValues()
            ->where('attribute_id', $attributeId)
            ->first();
            
        return $attributeValue ? $attributeValue->value : null;
    }
}
