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

    public $editingTemplateId = null;
    public $name = '';
    public $description = '';
    public $categorySearch = '';
    public $selectedCategories = [];
    public $selectAllCategories = true;
    public $selectedPrices = [];
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
        $types = [];
        foreach (PriceType::cases() as $case) {
            $types[$case->value] = $case->label();
        }
        return $types;
    }

    #[Computed]
    public function availableSortFields()
    {
        $fields = [
            'product_name' => 'نام محصول',
            'category' => 'دسته‌بندی',
        ];

        $priceTypes = $this->availablePriceTypes;
        $activePriceKeys = $this->selectAllPrices ? array_keys($priceTypes) : $this->selectedPrices;

        foreach ($activePriceKeys as $key) {
            if (isset($priceTypes[$key])) {
                $fields['price_' . $key] = $priceTypes[$key];
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
            $this->selectedPrices = array_keys($this->availablePriceTypes);
        } else {
            $this->selectedPrices = [];
        }
        $this->ensureValidSortFields();
    }

    public function updatedSelectedPrices()
    {
        $this->selectAllPrices = count($this->selectedPrices) === count($this->availablePriceTypes);
        $this->ensureValidSortFields();
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

        // تنظیم قیمت‌ها
        if (($settings['prices'] ?? 'all') === 'all') {
            $this->selectAllPrices = true;
            $this->selectedPrices = array_keys($this->availablePriceTypes);
        } else {
            $this->selectAllPrices = false;
            $this->selectedPrices = $settings['prices'] ?? [];
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
            'selectedSuppliers' => $this->isAdmin ? 'array' : 'nullable',
        ]);

        $settings = [
            'description' => $this->description,
            'categories' => $this->selectAllCategories ? 'all' : $this->selectedCategories,
            'prices' => $this->selectAllPrices ? 'all' : $this->selectedPrices,
            'sort_level1' => $this->sortLevel1,
            'sort_level1_dir' => $this->sortLevel1Dir,
            'sort_level2' => $this->sortLevel2,
            'sort_level2_dir' => $this->sortLevel2Dir,
            'separate_categories' => $this->separateCategories,
            'show_cover_page' => $this->show_cover_page,
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

            $template->update([
                'name' => $this->name,
                'settings' => $settings,
                'config' => $config,
            ]);

            $this->successMessage = 'کاتالوگ با موفقیت بروزرسانی شد.';
        } else {
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
            $this->resetForm();
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
        $this->selectedPrices = array_keys($this->availablePriceTypes);

        $this->sortLevel1 = 'category';
        $this->sortLevel1Dir = 'asc';

        $this->sortLevel2 = 'product_name';
        $this->sortLevel2Dir = 'asc';

        $this->separateCategories = false;
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
        ])->layout($layout);
    }
}
