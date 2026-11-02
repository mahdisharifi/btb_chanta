<?php

namespace App\Livewire\Supplier\Catalog;

use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use App\Models\Category;
use App\Models\CatalogTemplate;
use App\Models\Supplier;
use App\Enums\PriceType;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class Index extends Component
{
    use WithPagination;
    
    // پراپرتی برای تشخیص ادمین
    #[Locked]
    public bool $isAdmin = false;
    
    // پراپرتی انتخاب چند supplier (فقط برای ادمین)
    public array $selectedSuppliers = [];

    public string $priceDisplayCurrency = 'toman';
    public string $catalogBranchScope = 'single';
    public ?int $catalogBranchSupplierId = null;
    public array $catalogBranchSupplierIds = [];
    
    public $show_cover_page = true;
    
    public $cover_page_break = true; // صفحه جداگانه برای کاور
    
    public $imageQuality = 'sm'; // sm, md, lg, original
    
    public $coverTheme = 'default'; // default, modern, creative, corporate, custom

    public $editingTemplateId = null;
    public $name = '';
    public $description = '';
    public $categorySearch = '';
    public $selectedCategories = [];
    public $selectAllCategories = true;
    public $selectedPrices = [];  // فرمت جدید: ['tier_index' => [price_type_ids]]
    public $selectedTiers = [];   // تیراژهای انتخاب شده: [0, 1, 2, 3]
    public $selectedCheckPeriods = []; // ✅ جدید: دوره‌های چک انتخاب شده برای هر تیراژ: ['tier_index' => [check_period_ids]]
    public $includeWarranty = false; // ✅ نمایش قیمت‌های با گارانتی
    public $selectAllPrices = true;
    public $separateCategories = false;
    public $sortLevel1 = 'category';
    public $sortLevel1Dir = 'asc';
    public $sortLevel2 = 'product_name';
    public $sortLevel2Dir = 'asc';

    // متغیر جدید برای نمایش پیام موفقیت
    public $showSuccessMessage = false;
    public $successMessage = '';
    
    // متغیر برای بررسی وجود supplier ادمین
    public $adminHasSupplier = false;
    
    // متغیر برای نمایش/مخفی کردن فرم
    public $showForm = false;
    
    // مشخصات محصول در نام دسته‌بندی
    public $globalAttribute = '';
    public $categoryAttributes = [];  // ['category_id' => ['attr1', 'attr2']]
    public $categoryAttributesUI = [];  // برای UI (نمایش در فرم)
    public $showAttributeLabels = true;  // نمایش عنوان مشخصات (مثل "رنگ:", "سایز:")
    
    // شماره‌های تماس سفارشی
    public $contactPhones = [
        ['label' => '', 'number' => ''],
    ];

    public function mount()
    {
        // تشخیص ادمین
        $this->isAdmin = $this->authenticatedUserIsAdmin();
        
        if ($this->isAdmin) {
            // بررسی وجود supplier برای ادمین
            $adminSupplier = Supplier::where('user_id', Auth::id())
                ->where('type', 'admin')
                ->first();
            
            $this->adminHasSupplier = $adminSupplier !== null;
            
            // اگر supplier ندارد، متوقف می‌شویم
            if (!$this->adminHasSupplier) {
                return;
            }
        }
        
        $this->resetForm();
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

    private function eligibleSupplierCurrencyCode(): ?string
    {
        if ($this->authenticatedUserIsAdmin()) {
            $supplierIds = array_values(array_unique(array_map('intval', $this->selectedSuppliers)));
            if (count($supplierIds) !== 1 || $supplierIds[0] < 1) {
                return null;
            }

            $supplier = Supplier::query()
                ->where('verification_status', 'approved')
                ->find($supplierIds[0]);

            $suppliers = $supplier ? collect([$supplier]) : collect();
        } else {
            $suppliers = $this->selectedCatalogSuppliers();
        }

        if ($suppliers->isEmpty()) {
            return null;
        }

        $currencies = $suppliers
            ->map(static fn (Supplier $supplier): ?string => strtoupper((string) $supplier->pricing_currency))
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
        $rates = $resolver->resolveMany($suppliers)->map(static fn (?array $basis): ?float =>
            $basis !== null && is_numeric($basis['rate_value'] ?? null)
                ? (float) $basis['rate_value']
                : null
        );

        if ($rates->contains(null) || $rates->filter(static fn (?float $rate): bool => $rate !== null)->count() !== $suppliers->count()) {
            return null;
        }

        $firstRate = (float) $rates->first();
        if ($rates->contains(static fn (?float $rate): bool => $rate === null || abs($rate - $firstRate) >= 0.00005)) {
            return null;
        }

        return app(\App\Services\CatalogPriceFormatter::class)
            ->isSupportedForeignCurrency($currency) ? $currency : null;
    }

    #[Computed]
    public function availableCatalogBranches()
    {
        if ($this->authenticatedUserIsAdmin()) {
            return collect();
        }

        $current = Auth::user()?->currentSupplier();
        if (!$current) {
            return collect();
        }

        $primary = $current->parent_supplier_id
            ? $current->parentSupplier
            : $current;

        if (!$primary || !$primary->is_active) {
            return collect();
        }

        $supplierIds = collect([(int) $primary->id])->merge(
            $primary->branches()
                ->where('is_active', true)
                ->where('is_verified', true)
                ->where('user_id', $primary->user_id)
                ->orderBy('id')
                ->pluck('id')
                ->map(static fn ($id): int => (int) $id)
        )->unique()->values();

        return Supplier::query()
            ->leftJoin('cities as branch_location_city', function ($join): void {
                $join->on('suppliers.city_id', '=', 'branch_location_city.id')
                    ->on('suppliers.province_id', '=', 'branch_location_city.province_id')
                    ->where('branch_location_city.is_active', true)
                    ->whereNull('branch_location_city.deleted_at');
            })
            ->whereIn('suppliers.id', $supplierIds)
            ->where('suppliers.is_active', true)
            ->orderByRaw('CASE WHEN suppliers.id = ? THEN 0 ELSE 1 END', [(int) $primary->id])
            ->orderBy('suppliers.id')
            ->get([
                'suppliers.id',
                'suppliers.user_id',
                'suppliers.parent_supplier_id',
                'suppliers.branch_name',
                'suppliers.company_name',
                'suppliers.city',
                'suppliers.pricing_currency',
                'branch_location_city.native_name as selected_city_native_name',
                'branch_location_city.name as selected_city_name',
            ]);
    }

    private function selectedCatalogSuppliers()
    {
        $branches = $this->availableCatalogBranches;
        if ($this->catalogBranchScope === 'all') {
            return $branches;
        }

        if ($this->catalogBranchScope === 'selected') {
            $selectedIds = collect($this->catalogBranchSupplierIds)
                ->map(static fn ($id): int => (int) $id)
                ->unique()
                ->values();

            return $branches
                ->filter(static fn (Supplier $branch): bool => $selectedIds->contains((int) $branch->id))
                ->values();
        }

        $selectedId = (int) ($this->catalogBranchSupplierId ?? 0);
        if ($selectedId < 1) {
            $selectedId = (int) (Auth::user()?->currentSupplier()?->id ?? 0);
        }

        return $branches->where('id', $selectedId)->values();
    }

    public function updatedCatalogBranchScope(): void
    {
        $availableIds = $this->availableCatalogBranches
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        if ($this->catalogBranchScope === 'all') {
            // Keep the all-branch set ready in case the user switches to a custom mix.
            $this->catalogBranchSupplierIds = $availableIds;
        } elseif ($this->catalogBranchScope === 'single') {
            $currentId = (int) (Auth::user()?->currentSupplier()?->id ?? 0);
            if (!in_array((int) $this->catalogBranchSupplierId, $availableIds, true)) {
                $this->catalogBranchSupplierId = in_array($currentId, $availableIds, true)
                    ? $currentId
                    : ($availableIds[0] ?? null);
            }

            $this->catalogBranchSupplierIds = $this->catalogBranchSupplierId
                ? [(int) $this->catalogBranchSupplierId]
                : [];
        } elseif ($this->catalogBranchScope === 'selected') {
            $this->catalogBranchSupplierIds = array_values(array_intersect(
                array_values(array_unique(array_map('intval', $this->catalogBranchSupplierIds))),
                $availableIds,
            ));

            if ($this->catalogBranchSupplierIds === []) {
                $fallbackId = (int) ($this->catalogBranchSupplierId ?? Auth::user()?->currentSupplier()?->id ?? 0);
                if (in_array($fallbackId, $availableIds, true)) {
                    $this->catalogBranchSupplierIds = [$fallbackId];
                }
            }
        }

        if ($this->priceDisplayCurrency === 'supplier' && $this->eligibleSupplierCurrencyCode() === null) {
            $this->priceDisplayCurrency = 'toman';
        }

        unset($this->availableCategories);
    }

    public function updatedCatalogBranchSupplierId(): void
    {
        if ($this->catalogBranchScope === 'single' && $this->catalogBranchSupplierId) {
            $this->catalogBranchSupplierIds = [(int) $this->catalogBranchSupplierId];
        }

        if ($this->catalogBranchScope === 'all') {
            $this->catalogBranchSupplierIds = $this->availableCatalogBranches
                ->pluck('id')
                ->map(static fn ($id): int => (int) $id)
                ->all();
        }

        if ($this->priceDisplayCurrency === 'supplier' && $this->eligibleSupplierCurrencyCode() === null) {
            $this->priceDisplayCurrency = 'toman';
        }

        unset($this->availableCategories);
    }

    public function updatedCatalogBranchSupplierIds(): void
    {
        $allowedIds = $this->availableCatalogBranches
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $this->catalogBranchSupplierIds = array_values(array_intersect(
            array_values(array_unique(array_map('intval', $this->catalogBranchSupplierIds))),
            $allowedIds,
        ));

        if ($this->priceDisplayCurrency === 'supplier' && $this->eligibleSupplierCurrencyCode() === null) {
            $this->priceDisplayCurrency = 'toman';
        }

        unset($this->availableCategories);
    }

    #[Computed]
    public function supplierCurrencyLabel(): ?string
    {
        return app(\App\Services\CatalogPriceFormatter::class)
            ->label($this->eligibleSupplierCurrencyCode());
    }

    #[Computed]
    public function supplierCurrencyUnavailableMessage(): string
    {
        if ($this->authenticatedUserIsAdmin() && count(array_unique($this->selectedSuppliers)) !== 1) {
            return 'برای نمایش ارزی، دقیقاً یک تأمین‌کننده انتخاب کنید. کاتالوگ چندتأمین‌کننده فقط با تومان نمایش داده می‌شود.';
        }

        if (!$this->authenticatedUserIsAdmin() && in_array($this->catalogBranchScope, ['all', 'selected'], true)) {
            return 'نمایش ارزی برای شعب انتخاب‌شده فقط زمانی ممکن است که همهٔ شعب ارز پایه و نرخ تبدیل یکسان داشته باشند؛ در غیر این صورت تومان را انتخاب کنید.';
        }

        return 'ابتدا ارز پایهٔ دلار، یوان یا درهم را در تنظیمات قیمت‌گذاری تأمین‌کننده فعال کنید.';
    }

    public function updatedSelectedSuppliers(): void
    {
        if ($this->priceDisplayCurrency === 'supplier' && $this->eligibleSupplierCurrencyCode() === null) {
            $this->priceDisplayCurrency = 'toman';
        }

        $this->resetValidation('priceDisplayCurrency');
    }

    #[Computed]
    public function availableCategories()
    {
        // تعیین supplier_id بر اساس نقش کاربر
        if ($this->isAdmin) {
            // اگر ادمین است و supplier انتخاب کرده
            if (!empty($this->selectedSuppliers)) {
                $supplierIds = $this->selectedSuppliers;
            } else {
                // اگر هیچ supplier انتخاب نشده، از همه suppliers استفاده کن
                $supplierIds = Supplier::where('verification_status', 'approved')
                    ->pluck('id')
                    ->toArray();
            }
            
            $query = Category::whereHas('products.suppliers', function ($q) use ($supplierIds) {
                $q->whereIn('supplier_id', $supplierIds);
            });
        } else {
            $supplierIds = $this->selectedCatalogSuppliers()->pluck('id')->all();

            $query = Category::whereHas('products.productSuppliers', function ($q) use ($supplierIds) {
                $q->whereIn('supplier_id', $supplierIds)
                    ->where('is_active', true);
            });
        }

        if (!$this->selectAllCategories) {
            if ($this->isAdmin) {
                $query->whereHas('products.suppliers', function ($q) use ($supplierIds) {
                    $q->whereIn('product_supplier.supplier_id', $supplierIds)
                        ->whereExists(function ($subQuery) {
                            $subQuery->select(DB::raw(1))
                                ->from('product_prices')
                                ->whereColumn('product_prices.product_supplier_id', 'product_supplier.id');
                        });
                });
            } else {
                $query->whereHas('products.productSuppliers', function ($q) use ($supplierIds) {
                    $q->whereIn('product_supplier.supplier_id', $supplierIds)
                        ->whereExists(function ($subQuery) {
                            $subQuery->select(DB::raw(1))
                                ->from('product_prices')
                                ->whereColumn('product_prices.product_supplier_id', 'product_supplier.id');
                        });
                });
            }
        }

        if (!empty($this->categorySearch)) {
            $query->where('name', 'like', '%' . $this->categorySearch . '%');
        }

        return $query->pluck('name', 'id')->toArray();
    }

    #[Computed]
    public function availablePriceTypes()
    {
        // ✅ استفاده از جدول price_types به جای enum
        // ✅ فیلتر کردن نوع گارانتی (چون جداگانه نمایش می‌دهیم)
        return \App\Models\PriceType::active()
            ->where('code', '!=', 'warranty') // ✅ حذف گارانتی از لیست
            ->orderBy('sort_order')
            ->pluck('name', 'id')
            ->toArray();
    }
    
    #[Computed]
    public function availableTiers()
    {
        // دریافت تیراژهای پیشفرض
        $defaultTiers = [
            0 => 'زیرکارتن',
            1 => 'کارتن',
            2 => 'عمده',
            3 => 'بار',
        ];
        
        // فعلاً فقط از تیراژهای پیشفرض استفاده می‌کنیم
        // TODO: در آینده می‌توان تیراژهای سفارشی supplier را از جدول بخوانیم
        
        return $defaultTiers;
    }
    
    #[Computed]
    public function availableCheckPeriods()
    {
        // ✅ دریافت فقط دوره‌های چک فعال شده توسط تامین‌کننده
        if ($this->isAdmin) {
            // ادمین: دوره‌های global
            return \App\Models\CheckPeriod::active()
                ->whereNull('supplier_id')
                ->orderBy('days')
                ->get();
        } else {
            // For a combined catalog, expose the union of periods enabled by every
            // selected branch. Preview still resolves each price against its own
            // supplier's enabled periods, so a branch-only period cannot leak to others.
            $primarySupplierId = (int) ($this->availableCatalogBranches->first()?->id ?? 0);
            $supplierIds = $this->selectedCatalogSuppliers()
                ->pluck('id')
                ->map(static fn ($id): int => (int) $id)
                ->filter(static fn (int $id): bool => $id > 0)
                ->when($primarySupplierId > 0, static fn ($ids) => $ids->push($primarySupplierId))
                ->unique()
                ->values()
                ->all();

            return \App\Models\CheckPeriod::getActiveForSuppliers($supplierIds)
                ->unique('id')
                ->values();
        }
    }
    
    #[Computed]
    public function availableAttributes()
    {
        // دریافت تمام attributes از دیتابیس
        return \App\Models\Attribute::orderBy('name')->pluck('name', 'id')->toArray();
    }
    
    #[Computed]
    public function categoriesToConfigureAttributes()
    {
        // اگر "همه دسته‌بندی‌ها" انتخاب شده، لیست تمام دسته‌بندی‌های موجود
        if ($this->selectAllCategories) {
            return $this->availableCategories;
        }
        
        // در غیر این صورت، فقط دسته‌بندی‌های انتخاب شده
        $availableCategories = $this->availableCategories;
        $filtered = [];
        foreach ($this->selectedCategories as $catId) {
            if (isset($availableCategories[$catId])) {
                $filtered[$catId] = $availableCategories[$catId];
            }
        }
        return $filtered;
    }

    #[Computed]
    public function availableSortFields()
    {
        $fields = [
            'product_name' => 'نام محصول',
            'category' => 'دسته‌بندی',
        ];

        // اضافه کردن فیلدهای مرتب‌سازی قیمت بر اساس تیراژ و نوع قیمت انتخاب شده
        $tiers = $this->availableTiers;
        $priceTypes = $this->availablePriceTypes;
        
        // اطمینان از اینکه selectedTiers یک آرایه است
        $selectedTiers = is_array($this->selectedTiers) ? $this->selectedTiers : [];
        
        foreach ($selectedTiers as $tierIndex) {
            if (isset($this->selectedPrices[$tierIndex]) && is_array($this->selectedPrices[$tierIndex])) {
                foreach ($this->selectedPrices[$tierIndex] as $priceTypeId) {
                    $tierName = $tiers[$tierIndex] ?? "تیراژ {$tierIndex}";
                    $priceTypeName = $priceTypes[$priceTypeId] ?? "قیمت {$priceTypeId}";
                    
                    $key = "price_{$tierIndex}_{$priceTypeId}";
                    $fields[$key] = "{$tierName} - {$priceTypeName}";
                }
            }
        }

        return $fields;
    }

    public function updatedSelectAllCategories($value)
    {
        // استفاده از computed property بدون unset
        $availableCategories = $this->availableCategories;

        if ($value) {
            $this->selectedCategories = array_keys($availableCategories);
        } else {
            $this->selectedCategories = [];
        }
    }

    public function updatedSelectedCategories()
    {
        $availableCategories = $this->availableCategories;
        $this->selectAllCategories = count($this->selectedCategories) === count($availableCategories);
    }

    public function updatedSelectAllPrices($value)
    {
        if ($value) {
            // انتخاب همه: تمام تیراژها با تمام انواع قیمت
            $this->selectedTiers = array_keys($this->availableTiers);
            $priceTypeIds = array_keys($this->availablePriceTypes);
            
            $this->selectedPrices = [];
            foreach ($this->selectedTiers as $tierIndex) {
                $this->selectedPrices[$tierIndex] = $priceTypeIds;
            }
        } else {
            // لغو انتخاب همه
            $this->selectedTiers = [];
            $this->selectedPrices = [];
        }
        $this->ensureValidSortFields();
    }

    public function updatedSelectedPrices()
    {
        // بررسی آیا همه تیراژها و قیمت‌ها انتخاب شده‌اند
        $allTiers = array_keys($this->availableTiers);
        $allPriceTypes = array_keys($this->availablePriceTypes);
        
        $allSelected = true;
        foreach ($allTiers as $tierIndex) {
            if (!isset($this->selectedPrices[$tierIndex]) || 
                count($this->selectedPrices[$tierIndex]) !== count($allPriceTypes)) {
                $allSelected = false;
                break;
            }
        }
        
        $this->selectAllPrices = $allSelected;
        $this->ensureValidSortFields();
    }
    
    /**
     * تغییر وضعیت انتخاب یک تیراژ
     */
    public function toggleTier($tierIndex)
    {
        if (in_array($tierIndex, $this->selectedTiers)) {
            // حذف تیراژ
            $this->selectedTiers = array_values(array_diff($this->selectedTiers, [$tierIndex]));
            unset($this->selectedPrices[$tierIndex]);
        } else {
            // افزودن تیراژ با قیمت نقدی به صورت پیشفرض
            $this->selectedTiers[] = $tierIndex;
            $this->selectedPrices[$tierIndex] = [1]; // cash
        }
        
        $this->updatedSelectedPrices();
    }
    
    /**
     * تغییر وضعیت انتخاب یک نوع قیمت برای یک تیراژ
     */
    public function toggleTierPrice($tierIndex, $priceTypeId)
    {
        if (!isset($this->selectedPrices[$tierIndex])) {
            $this->selectedPrices[$tierIndex] = [];
        }
        
        $key = array_search($priceTypeId, $this->selectedPrices[$tierIndex]);
        if ($key !== false) {
            // حذف نوع قیمت
            unset($this->selectedPrices[$tierIndex][$key]);
            $this->selectedPrices[$tierIndex] = array_values($this->selectedPrices[$tierIndex]);
            
            // ✅ اگر نوع قیمت "چک" بود، دوره‌های چک این تیراژ را هم پاک کن
            $priceType = \App\Models\PriceType::find($priceTypeId);
            if ($priceType && $priceType->code === 'check') {
                unset($this->selectedCheckPeriods[$tierIndex]);
            }
            
            // اگر تمام قیمت‌ها حذف شدند، تیراژ را هم حذف کن
            if (empty($this->selectedPrices[$tierIndex])) {
                unset($this->selectedPrices[$tierIndex]);
                $this->selectedTiers = array_values(array_diff($this->selectedTiers, [$tierIndex]));
            }
        } else {
            // افزودن نوع قیمت
            $this->selectedPrices[$tierIndex][] = $priceTypeId;
            
            // ✅ اگر نوع قیمت "چک" است، همه دوره‌های چک را فعال کن (پیشفرض)
            $priceType = \App\Models\PriceType::find($priceTypeId);
            if ($priceType && $priceType->code === 'check') {
                $this->selectedCheckPeriods[$tierIndex] = $this->availableCheckPeriods->pluck('id')->toArray();
            }
            
            // اطمینان از اینکه تیراژ در selectedTiers هست
            if (!in_array($tierIndex, $this->selectedTiers)) {
                $this->selectedTiers[] = $tierIndex;
            }
        }
        
        $this->updatedSelectedPrices();
    }
    
    /**
     * ✅ جدید: Toggle کردن یک دوره چک برای یک تیراژ خاص
     */
    public function toggleCheckPeriod($tierIndex, $checkPeriodId)
    {
        if (!isset($this->selectedCheckPeriods[$tierIndex])) {
            $this->selectedCheckPeriods[$tierIndex] = [];
        }
        
        $key = array_search($checkPeriodId, $this->selectedCheckPeriods[$tierIndex]);
        
        if ($key !== false) {
            // حذف
            unset($this->selectedCheckPeriods[$tierIndex][$key]);
            $this->selectedCheckPeriods[$tierIndex] = array_values($this->selectedCheckPeriods[$tierIndex]);
        } else {
            // اضافه
            $this->selectedCheckPeriods[$tierIndex][] = $checkPeriodId;
        }
        
        // اگر همه دوره‌ها حذف شدند، آرایه را پاک کن
        if (empty($this->selectedCheckPeriods[$tierIndex])) {
            unset($this->selectedCheckPeriods[$tierIndex]);
        }
    }

    public function updatedSortLevel1($value)
    {
        if ($this->sortLevel2 === $value) {
            foreach ($this->availableSortFields as $key => $label) {
                if ($key !== $value) {
                    $this->sortLevel2 = $key;
                    break;
                }
            }
        }
    }

    private function ensureValidSortFields()
    {
        $validKeys = array_keys($this->availableSortFields);

        if (!in_array($this->sortLevel1, $validKeys)) {
            $this->sortLevel1 = 'category';
        }

        if (!in_array($this->sortLevel2, $validKeys) || $this->sortLevel2 === $this->sortLevel1) {
            foreach ($validKeys as $key) {
                if ($key !== $this->sortLevel1) {
                    $this->sortLevel2 = $key;
                    break;
                }
            }
        }
    }
    
    /**
     * افزودن شماره تماس جدید
     */
    public function addContactPhone()
    {
        if (count($this->contactPhones) < 3) {
            $this->contactPhones[] = ['label' => '', 'number' => ''];
        }
    }
    
    /**
     * حذف شماره تماس
     */
    public function removeContactPhone($index)
    {
        if (count($this->contactPhones) > 1) {
            unset($this->contactPhones[$index]);
            $this->contactPhones = array_values($this->contactPhones);
        }
    }
    
    /**
     * پیدا کردن کاتالوگ پیش‌فرض
     */
    protected function findDefaultTemplateCatalog()
    {
        $supplierId = Auth::user()->currentSupplier()->id ?? null;
        if (!$supplierId) {
            return null;
        }
        
        // پیدا کردن کاتالوگی که is_default_template = true دارد
        $templates = CatalogTemplate::where('supplier_id', $supplierId)->get();
        
        foreach ($templates as $template) {
            $settings = $template->settings ?? [];
            $isDefault = $settings['custom_cover']['is_default_template'] ?? false;
            
            if ($isDefault) {
                return $template;
            }
        }
        
        return null;
    }
    
    /**
     * نمایش فرم ایجاد کاتالوگ جدید
     */
    public function createNew()
    {
        $this->resetForm();
        $this->showForm = true;
        $this->dispatch('scroll-to-form');
    }
    
    /**
     * مخفی کردن فرم
     */
    public function hideForm()
    {
        $this->showForm = false;
        $this->resetForm();
    }
    
    /**
     * اضافه کردن مشخصه برای یک دسته‌بندی
     */
    public function addCategoryAttribute($categoryId)
    {
        if (!isset($this->categoryAttributes[$categoryId])) {
            $this->categoryAttributes[$categoryId] = [];
        }
        
        if (count($this->categoryAttributes[$categoryId]) < 2) {
            $this->categoryAttributes[$categoryId][] = '';
        }
    }
    
    /**
     * حذف مشخصه از یک دسته‌بندی
     */
    public function removeCategoryAttribute($categoryId, $index)
    {
        if (isset($this->categoryAttributes[$categoryId][$index])) {
            unset($this->categoryAttributes[$categoryId][$index]);
            $this->categoryAttributes[$categoryId] = array_values($this->categoryAttributes[$categoryId]);
            
            // اگر خالی شد، حذف کن
            if (empty($this->categoryAttributes[$categoryId])) {
                unset($this->categoryAttributes[$categoryId]);
            }
        }
    }
    
    /**
     * حذف تمام مشخصات یک دسته‌بندی
     */
    public function removeCategoryAttributes($categoryId)
    {
        unset($this->categoryAttributes[$categoryId]);
    }

    public function editTemplate($id)
    {
        $this->selectedSuppliers = [];

        // تعیین supplier_id (همیشه supplier صاحب کاتالوگ)
        $supplierId = Auth::user()->currentSupplier()->id;
        
        $template = CatalogTemplate::where('id', $id)
            ->where('supplier_id', $supplierId)
            ->firstOrFail();

        $this->editingTemplateId = $template->id;
        $this->name = $template->name;
        
        // اگر ادمین است و supplier_ids در config وجود دارد
        if ($this->authenticatedUserIsAdmin()) {
            $configuredSupplierIds = $template->config['supplier_ids'] ?? [];
            $this->selectedSuppliers = is_array($configuredSupplierIds)
                ? array_values($configuredSupplierIds)
                : [];
        }

        $settings = $template->settings ?? [];
        if (!$this->authenticatedUserIsAdmin()) {
            $scope = $settings['catalog_branch_scope'] ?? 'single';
            $this->catalogBranchScope = in_array($scope, ['single', 'all', 'selected'], true) ? $scope : 'single';
            $ownerSupplierId = (int) $supplierId;
            $configuredBranchId = (int) ($settings['catalog_branch_supplier_id'] ?? $ownerSupplierId);
            $allowedBranchIds = $this->availableCatalogBranches->pluck('id')->map(static fn ($id): int => (int) $id)->all();

            if ($this->catalogBranchScope === 'selected') {
                $configuredBranchIds = $settings['catalog_branch_supplier_ids'] ?? [];
                $configuredBranchIds = is_array($configuredBranchIds)
                    ? array_values(array_unique(array_map('intval', $configuredBranchIds)))
                    : [];
                $this->catalogBranchSupplierIds = array_values(array_intersect($configuredBranchIds, $allowedBranchIds));

                if ($this->catalogBranchSupplierIds === []) {
                    $fallbackId = in_array($ownerSupplierId, $allowedBranchIds, true)
                        ? $ownerSupplierId
                        : ($allowedBranchIds[0] ?? null);
                    $this->catalogBranchSupplierIds = $fallbackId ? [(int) $fallbackId] : [];
                }

                $this->catalogBranchSupplierId = $this->catalogBranchSupplierIds[0] ?? null;
            } elseif ($this->catalogBranchScope === 'all') {
                $this->catalogBranchSupplierIds = $allowedBranchIds;
                $this->catalogBranchSupplierId = in_array($ownerSupplierId, $allowedBranchIds, true)
                    ? $ownerSupplierId
                    : ($allowedBranchIds[0] ?? null);
            } else {
                $this->catalogBranchSupplierId = in_array($configuredBranchId, $allowedBranchIds, true)
                    ? $configuredBranchId
                    : ($allowedBranchIds[0] ?? $ownerSupplierId);
                $this->catalogBranchSupplierIds = $this->catalogBranchSupplierId
                    ? [(int) $this->catalogBranchSupplierId]
                    : [];
            }
        }
        $this->description = $settings['description'] ?? '';

        $this->sortLevel1 = $settings['sort_level1'] ?? 'category';
        $this->sortLevel1Dir = $settings['sort_level1_dir'] ?? 'asc';

        $this->sortLevel2 = $settings['sort_level2'] ?? 'product_name';
        $this->sortLevel2Dir = $settings['sort_level2_dir'] ?? 'asc';

        $this->separateCategories = $settings['separate_categories'] ?? false;
        $this->show_cover_page = $settings['show_cover_page'] ?? true;
        $this->cover_page_break = $settings['cover_page_break'] ?? true;
        $this->imageQuality = $settings['image_quality'] ?? 'sm';
        $this->coverTheme = $settings['cover_theme'] ?? 'default';
        
        // بارگذاری مشخصات محصول
        $this->globalAttribute = $settings['global_attribute'] ?? '';
        $this->showAttributeLabels = $settings['show_attribute_labels'] ?? true;
        $this->includeWarranty = $settings['include_warranty'] ?? false; // ✅ بارگذاری نمایش گارانتی
        $this->priceDisplayCurrency = in_array(
            $settings['price_display_currency'] ?? 'toman',
            ['toman', 'supplier'],
            true
        ) ? ($settings['price_display_currency'] ?? 'toman') : 'toman';
        
        // بارگذاری مشخصات اختصاصی دسته‌بندی‌ها
        if (isset($settings['category_attributes']) && is_array($settings['category_attributes'])) {
            $this->categoryAttributes = $settings['category_attributes'];
        } else {
            $this->categoryAttributes = [];
        }
        
        // بارگذاری شماره‌های تماس
        $this->contactPhones = $settings['contact_phones'] ?? [['label' => '', 'number' => '']];

        // تنظیم قیمت‌ها - با پشتیبانی backward compatibility
        $pricesConfig = $settings['prices'] ?? 'all';
        
        if ($pricesConfig === 'all') {
            $this->selectAllPrices = true;
            // انتخاب همه تیراژها با همه انواع قیمت
            $this->selectedTiers = array_keys($this->availableTiers);
            $priceTypeIds = array_keys($this->availablePriceTypes);
            $this->selectedPrices = [];
            foreach ($this->selectedTiers as $tierIndex) {
                $this->selectedPrices[$tierIndex] = $priceTypeIds;
            }
        } elseif (is_array($pricesConfig)) {
            // بررسی ساختار: آیا ساختار جدید است یا قدیم؟
            $isNewStructure = false;
            foreach ($pricesConfig as $key => $value) {
                // ساختار جدید: کلید = tier_index (عدد), مقدار = آرایه از price_type_ids
                if (is_numeric($key) && is_array($value)) {
                    $isNewStructure = true;
                    break;
                }
            }
            
            if ($isNewStructure) {
                // ساختار جدید: ['tier_index' => [price_type_ids]]
                $this->selectAllPrices = false;
                $this->selectedPrices = $pricesConfig;
                $this->selectedTiers = array_keys($this->selectedPrices);
                
                // ✅ بارگذاری دوره‌های چک (اگر وجود داشته باشد)
                $checkPeriodsConfig = $settings['check_periods'] ?? [];
                if (is_array($checkPeriodsConfig) && !empty($checkPeriodsConfig)) {
                    $this->selectedCheckPeriods = $checkPeriodsConfig;
                } else {
                    // اگر دوره‌های چک ذخیره نشده، همه را فعال کن (برای backward compatibility)
                    $this->selectedCheckPeriods = [];
                    $checkPriceTypeId = \App\Models\PriceType::where('code', 'check')->value('id');
                    if ($checkPriceTypeId) {
                        foreach ($this->selectedPrices as $tierIndex => $priceTypeIds) {
                            if (in_array($checkPriceTypeId, $priceTypeIds)) {
                                $this->selectedCheckPeriods[$tierIndex] = $this->availableCheckPeriods->pluck('id')->toArray();
                            }
                        }
                    }
                }
            } else {
                // ساختار قدیم: [price_type_ids] - تبدیل به ساختار جدید
                // فقط تیراژ کارتن (مرجع) با این قیمت‌ها
                $this->selectAllPrices = false;
                $this->selectedTiers = [1]; // فقط کارتن
                $this->selectedPrices = [
                    1 => $pricesConfig, // کارتن با قیمت‌های انتخاب شده
                ];
            }
        } else {
            // مقدار نامعتبر - استفاده از پیشفرض
            $this->selectAllPrices = true;
            $this->selectedTiers = array_keys($this->availableTiers);
            $priceTypeIds = array_keys($this->availablePriceTypes);
            $this->selectedPrices = [];
            foreach ($this->selectedTiers as $tierIndex) {
                $this->selectedPrices[$tierIndex] = $priceTypeIds;
            }
        }

        // تنظیم دسته‌بندی‌ها
        if (($settings['categories'] ?? 'all') === 'all') {
            $this->selectAllCategories = true;
            $availableCategories = $this->availableCategories;
            $this->selectedCategories = array_keys($availableCategories);
        } else {
            $this->selectAllCategories = false;
            $this->selectedCategories = $settings['categories'] ?? [];
        }

        $this->ensureValidSortFields();
        
        // نمایش فرم
        $this->showForm = true;

        // اسکرول به فرم
        $this->dispatch('scroll-to-form');
    }

    public function saveTemplate()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'selectedCategories' => 'array',
            'selectedPrices' => 'array',
            'sortLevel1' => 'required|string',
            'sortLevel1Dir' => 'required|in:asc,desc',
            'sortLevel2' => 'required|string',
            'sortLevel2Dir' => 'required|in:asc,desc',
            'separateCategories' => 'boolean',
            'includeWarranty' => 'boolean', // ✅ نمایش گارانتی
            'show_cover_page' => 'boolean',
            'cover_page_break' => 'boolean',
            'imageQuality' => 'required|string|in:sm,md,lg,original',
            'coverTheme' => 'required|string|in:default,modern,creative,corporate,custom',
            'priceDisplayCurrency' => 'required|in:toman,supplier',
            'catalogBranchScope' => $this->authenticatedUserIsAdmin() ? 'nullable' : 'required|in:single,all,selected',
            'catalogBranchSupplierId' => $this->authenticatedUserIsAdmin()
                ? 'nullable'
                : 'nullable|required_if:catalogBranchScope,single|integer|min:1',
            'catalogBranchSupplierIds' => $this->authenticatedUserIsAdmin()
                ? 'nullable|array'
                : 'nullable|array|required_if:catalogBranchScope,selected|min:1',
            'catalogBranchSupplierIds.*' => $this->authenticatedUserIsAdmin()
                ? 'nullable|integer|min:1'
                : 'required|integer|min:1',
            'selectedSuppliers' => $this->authenticatedUserIsAdmin() ? 'array' : 'nullable',
            'selectedSuppliers.*' => $this->authenticatedUserIsAdmin() ? 'required|integer|min:1' : 'nullable',
            'globalAttribute' => 'nullable|string',
            'showAttributeLabels' => 'boolean',
            'categoryAttributes' => 'nullable|array',
            'contactPhones' => 'nullable|array|max:3',
            'contactPhones.*.label' => 'nullable|string|max:50',
            'contactPhones.*.number' => 'nullable|string|max:20',
        ]);

        if ($this->authenticatedUserIsAdmin() && !empty($this->selectedSuppliers)) {
            $selectedSupplierIds = array_values(array_unique(array_map('intval', $this->selectedSuppliers)));
            $approvedSupplierCount = Supplier::query()
                ->where('verification_status', 'approved')
                ->whereIn('id', $selectedSupplierIds)
                ->count();

            if ($approvedSupplierCount !== count($selectedSupplierIds)) {
                $this->addError('selectedSuppliers', 'فقط تأمین‌کنندگان تأییدشده قابل انتخاب هستند.');
                return;
            }

            $this->selectedSuppliers = $selectedSupplierIds;
        }

        if ($this->priceDisplayCurrency === 'supplier' && $this->eligibleSupplierCurrencyCode() === null) {
            $this->addError('priceDisplayCurrency', $this->supplierCurrencyUnavailableMessage());
            return;
        }

        if (!$this->authenticatedUserIsAdmin()) {
            $allowedBranchIds = $this->availableCatalogBranches
                ->pluck('id')
                ->map(static fn ($id): int => (int) $id)
                ->all();

            if ($this->catalogBranchScope === 'single'
                && !in_array((int) $this->catalogBranchSupplierId, $allowedBranchIds, true)) {
                $this->addError('catalogBranchSupplierId', 'شعبهٔ انتخاب‌شده متعلق به این فروشگاه نیست یا فعال نیست.');
                return;
            }

            if ($this->catalogBranchScope === 'selected') {
                $selectedBranchIds = array_values(array_unique(array_map('intval', $this->catalogBranchSupplierIds)));
                if ($selectedBranchIds === []) {
                    $this->addError('catalogBranchSupplierIds', 'حداقل یک شعبه را برای کاتالوگ انتخاب کنید.');
                    return;
                }

                if (array_diff($selectedBranchIds, $allowedBranchIds) !== []) {
                    $this->addError('catalogBranchSupplierIds', 'فقط شعبه‌های فعال همین فروشگاه را می‌توانید انتخاب کنید.');
                    return;
                }

                $this->catalogBranchSupplierIds = $selectedBranchIds;
            } elseif ($this->catalogBranchScope === 'all') {
                $this->catalogBranchSupplierIds = $allowedBranchIds;
            } else {
                $this->catalogBranchSupplierIds = [(int) $this->catalogBranchSupplierId];
            }
        }
        
        // فیلتر کردن شماره‌های تماس خالی
        $filteredPhones = array_filter($this->contactPhones, function($phone) {
            return !empty($phone['number']);
        });
        
        // فیلتر کردن مشخصات دسته‌بندی‌ها (حذف خالی‌ها)
        $filteredCategoryAttributes = [];
        foreach ($this->categoryAttributes as $catId => $attrs) {
            $filtered = array_filter($attrs, function($attr) {
                return !empty($attr);
            });
            if (!empty($filtered)) {
                $filteredCategoryAttributes[$catId] = array_values($filtered);
            }
        }

        $settings = [
            'description' => $this->description,
            'categories' => $this->selectAllCategories ? 'all' : $this->selectedCategories,
            'prices' => $this->selectAllPrices ? 'all' : (object)$this->selectedPrices, // ✅ تبدیل به object برای حفظ کلیدها
            'check_periods' => (object)$this->selectedCheckPeriods, // ✅ ذخیره دوره‌های چک (object)
            'include_warranty' => $this->includeWarranty, // ✅ نمایش گارانتی
            'price_display_currency' => $this->priceDisplayCurrency,
            'catalog_branch_scope' => $this->authenticatedUserIsAdmin() ? 'single' : $this->catalogBranchScope,
            'catalog_branch_supplier_id' => $this->authenticatedUserIsAdmin() || $this->catalogBranchScope !== 'single'
                ? null
                : (int) $this->catalogBranchSupplierId,
            'catalog_branch_supplier_ids' => !$this->authenticatedUserIsAdmin() && $this->catalogBranchScope === 'selected'
                ? array_values(array_unique(array_map('intval', $this->catalogBranchSupplierIds)))
                : null,
            'sort_level1' => $this->sortLevel1,
            'sort_level1_dir' => $this->sortLevel1Dir,
            'sort_level2' => $this->sortLevel2,
            'sort_level2_dir' => $this->sortLevel2Dir,
            'separate_categories' => $this->separateCategories,
            'show_cover_page' => $this->show_cover_page,
            'cover_page_break' => $this->cover_page_break,
            'image_quality' => $this->imageQuality,
            'cover_theme' => $this->coverTheme,
            'global_attribute' => $this->globalAttribute,
            'category_attributes' => $filteredCategoryAttributes,
            'show_attribute_labels' => $this->showAttributeLabels,
            'contact_phones' => array_values($filteredPhones),
        ];
        
        // تعیین supplier_id (همیشه supplier صاحب کاتالوگ است)
        $supplierId = (int) Auth::user()->currentSupplier()->id;
        
        // اگر ادمین است، supplier_ids را در config ذخیره کن
        $config = [];
        if ($this->authenticatedUserIsAdmin() && !empty($this->selectedSuppliers)) {
            $config['supplier_ids'] = $this->selectedSuppliers;
        }

        if ($this->editingTemplateId) {
            $template = CatalogTemplate::where('id', $this->editingTemplateId)
                ->where('supplier_id', $supplierId)
                ->firstOrFail();

            // حفظ تنظیمات کاور کاستم قبلی
            $existingSettings = $template->settings ?? [];
            if (isset($existingSettings['custom_cover_table'])) {
                $settings['custom_cover_table'] = $existingSettings['custom_cover_table'];
            }
            if (isset($existingSettings['custom_cover'])) {
                $settings['custom_cover'] = $existingSettings['custom_cover'];
            }

            $template->update([
                'name' => $this->name,
                'settings' => $settings,
                'config' => $config,
            ]);

            $this->successMessage = 'کاتالوگ با موفقیت بروزرسانی شد.';
        } else {
            // کاتالوگ جدید - بررسی کپی از پیش‌فرض
            if ($this->coverTheme === 'custom') {
                $defaultTemplate = $this->findDefaultTemplateCatalog();
                
                if ($defaultTemplate) {
                    // کپی تنظیمات کاور از پیش‌فرض
                    $defaultSettings = $defaultTemplate->settings ?? [];
                    $defaultCustomCoverTable = $defaultSettings['custom_cover_table'] ?? null;
                    
                    if ($defaultCustomCoverTable) {
                        $settings['custom_cover_table'] = $defaultCustomCoverTable;
                        // تنظیم source_catalog_id برای وابستگی
                        $settings['custom_cover_table']['source_catalog_id'] = $defaultTemplate->id;
                        $settings['custom_cover_table']['is_default_template'] = false;
                    }
                }
            }
            
            CatalogTemplate::create([
                'supplier_id' => $supplierId,
                'name' => $this->name,
                'settings' => $settings,
                'config' => $config,
            ]);

            $this->successMessage = 'کاتالوگ جدید با موفقیت ایجاد شد.';
        }

        $this->showSuccessMessage = true;
        $this->resetForm();
        
        // مخفی کردن فرم
        $this->showForm = false;

        // پنهان کردن پیام موفقیت بعد از 3 ثانیه
        $this->dispatch('hide-success-message');
    }

    public function deleteTemplate(CatalogTemplate $template)
    {
        // تعیین supplier_id (همیشه supplier صاحب کاتالوگ)
        $supplierId = Auth::user()->currentSupplier()->id;
        
        if ($template->supplier_id === $supplierId) {
            $template->delete();
            $this->successMessage = 'کاتالوگ با موفقیت حذف شد.';
            $this->showSuccessMessage = true;
            $this->dispatch('hide-success-message');
        }
    }

    /**
     * تأیید لغو ویرایش
     */
    public function confirmCancel()
    {
        // بررسی وجود داده‌های ویرایش نشده
        $hasUnsavedChanges = $this->editingTemplateId ||
            !empty($this->name) ||
            !empty($this->description) ||
            (!empty($this->selectedCategories) && !$this->selectAllCategories) ||
            (!empty($this->selectedPrices) && !$this->selectAllPrices);

        if ($hasUnsavedChanges) {
            $this->dispatch('confirm-cancel');
        } else {
            $this->hideForm();
        }
    }

    /**
     * پاک‌سازی فرم
     */
    public function resetForm()
    {
        $this->selectedSuppliers = [];
        $this->priceDisplayCurrency = 'toman';
        $this->catalogBranchScope = 'single';
        $this->catalogBranchSupplierId = Auth::user()?->currentSupplier()?->id;
        $this->catalogBranchSupplierIds = $this->catalogBranchSupplierId
            ? [(int) $this->catalogBranchSupplierId]
            : [];
        $this->editingTemplateId = null;
        $this->name = '';
        $this->description = '';
        $this->categorySearch = '';

        $this->selectAllCategories = true;
        // Defer category lookup until the user opens the form. This avoids
        // querying the product/category graph on every catalog-list page load.
        $this->selectedCategories = [];

        $this->selectAllPrices = true;
        
        // مقداردهی اولیه selectedTiers: همه تیراژها انتخاب شده
        $this->selectedTiers = array_keys($this->availableTiers);
        
        // مقداردهی اولیه selectedPrices: برای هر تیراژ، فقط قیمت نقدی
        $this->selectedPrices = [];
        foreach ($this->selectedTiers as $tierIndex) {
            $this->selectedPrices[$tierIndex] = [1]; // 1 = cash (نقدی)
        }

        $this->sortLevel1 = 'category';
        $this->sortLevel1Dir = 'asc';

        $this->sortLevel2 = 'product_name';
        $this->sortLevel2Dir = 'asc';

        $this->separateCategories = false;
        $this->show_cover_page = true;
        $this->cover_page_break = true;
        
        // Reset کیفیت تصویر
        $this->imageQuality = 'sm';
        $this->coverTheme = 'default';
        
        // Reset مشخصات و شماره‌های تماس
        $this->globalAttribute = '';
        $this->categoryAttributes = [];
        $this->contactPhones = [['label' => '', 'number' => '']];
    }

    #[Layout('components.layouts.supplier')]
    public function render()
    {
        // تعیین Layout بر اساس نقش
        $layout = $this->isAdmin ? 'components.layouts.admin' : 'components.layouts.supplier';
        
        // تعیین supplier_id (همیشه supplier صاحب کاتالوگ)
        $supplierId = Auth::user()->currentSupplier()->id ?? null;
        
        // لیست تمام suppliers تایید شده (برای ادمین)
        if ($this->isAdmin) {
            $suppliers = Supplier::where('verification_status', 'approved')
                ->orderBy('company_name')
                ->get();
        } else {
            $suppliers = collect();
        }
        
        // اگر supplier_id وجود دارد، کاتالوگ‌ها را بارگذاری کن
        if ($supplierId) {
            $templates = CatalogTemplate::where('supplier_id', $supplierId)
                ->latest()
                ->paginate(10);
        } else {
            // اگر supplier_id نیست، یک LengthAwarePaginator خالی برگردان
            $templates = new \Illuminate\Pagination\LengthAwarePaginator(
                [],
                0,
                10,
                1,
                ['path' => request()->url()]
            );
        }

        return view('livewire.supplier.catalog.index', [
            'templates' => $templates,
            'suppliers' => $suppliers,
            'imageQualities' => [
                'sm' => 'کوچک (Small) - سریع‌تر، حجم کمتر',
                'md' => 'متوسط (Medium) - متعادل',
                'lg' => 'بزرگ (Large) - کیفیت بهتر',
                'original' => 'اصلی (Original) - بهترین کیفیت، حجم بیشتر',
            ],
        ])->layout($layout);
    }
}
