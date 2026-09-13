<?php

namespace App\Livewire\Supplier\Catalog;

use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
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
    public bool $isAdmin = false;
    
    // پراپرتی انتخاب چند supplier (فقط برای ادمین)
    public array $selectedSuppliers = [];
    
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
        $this->isAdmin = Auth::user()->hasRole('super_admin');
        
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
            $supplierId = Auth::user()->supplier->id;
            
            $query = Category::whereHas('products.suppliers', function ($q) use ($supplierId) {
                $q->where('supplier_id', $supplierId);
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
                $query->whereHas('products.suppliers', function ($q) use ($supplierId) {
                    $q->where('product_supplier.supplier_id', $supplierId)
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
        return \App\Models\PriceType::active()
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
        // ✅ دریافت دوره‌های چک فعال برای supplier
        if ($this->isAdmin) {
            // ادمین: دوره‌های global
            return \App\Models\CheckPeriod::active()
                ->whereNull('supplier_id')
                ->orderBy('days')
                ->get();
        } else {
            // تامین‌کننده: دوره‌های global + دوره‌های خودش
            $supplierId = Auth::user()->supplier->id;
            return \App\Models\CheckPeriod::active()
                ->where(function($q) use ($supplierId) {
                    $q->whereNull('supplier_id')
                      ->orWhere('supplier_id', $supplierId);
                })
                ->orderBy('days')
                ->get();
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
        $supplierId = Auth::user()->supplier->id ?? null;
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
        // تعیین supplier_id (همیشه supplier صاحب کاتالوگ)
        $supplierId = Auth::user()->supplier->id;
        
        $template = CatalogTemplate::where('id', $id)
            ->where('supplier_id', $supplierId)
            ->firstOrFail();

        $this->editingTemplateId = $template->id;
        $this->name = $template->name;
        
        // اگر ادمین است و supplier_ids در config وجود دارد
        if ($this->isAdmin && isset($template->config['supplier_ids'])) {
            $this->selectedSuppliers = $template->config['supplier_ids'];
        }

        $settings = $template->settings ?? [];
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
            'show_cover_page' => 'boolean',
            'cover_page_break' => 'boolean',
            'imageQuality' => 'required|string|in:sm,md,lg,original',
            'coverTheme' => 'required|string|in:default,modern,creative,corporate,custom',
            'selectedSuppliers' => $this->isAdmin ? 'array' : 'nullable',
            'globalAttribute' => 'nullable|string',
            'showAttributeLabels' => 'boolean',
            'categoryAttributes' => 'nullable|array',
            'contactPhones' => 'nullable|array|max:3',
            'contactPhones.*.label' => 'nullable|string|max:50',
            'contactPhones.*.number' => 'nullable|string|max:20',
        ]);
        
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
            'prices' => $this->selectAllPrices ? 'all' : $this->selectedPrices,
            'check_periods' => $this->selectedCheckPeriods, // ✅ ذخیره دوره‌های چک
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
        $supplierId = $this->isAdmin 
            ? Auth::user()->supplier->id  // ادمین از supplier خودش استفاده می‌کند
            : Auth::user()->supplier->id;
        
        // اگر ادمین است، supplier_ids را در config ذخیره کن
        $config = [];
        if ($this->isAdmin && !empty($this->selectedSuppliers)) {
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
        $supplierId = Auth::user()->supplier->id;
        
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
        $this->editingTemplateId = null;
        $this->name = '';
        $this->description = '';
        $this->categorySearch = '';

        $this->selectAllCategories = true;
        $availableCategories = $this->availableCategories;
        $this->selectedCategories = array_keys($availableCategories);

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
        $supplierId = Auth::user()->supplier->id ?? null;
        
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
