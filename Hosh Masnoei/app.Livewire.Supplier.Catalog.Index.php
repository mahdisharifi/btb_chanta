<?php

namespace App\Livewire\Supplier\Catalog;

use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use App\Models\Category;
use App\Models\CatalogTemplate;
use App\Enums\PriceType;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class Index extends Component
{
    use WithPagination;
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

    public function mount()
    {
        $this->resetForm();
    }

    #[Computed]
    public function availableCategories()
    {
        $supplierId = Auth::user()->supplier->id;

        $query = Category::whereHas('products.suppliers', function ($q) use ($supplierId) {
            $q->where('supplier_id', $supplierId);
        });

        if (!$this->selectAllCategories) {
            $query->whereHas('products.suppliers', function ($q) use ($supplierId) {
                $q->where('product_supplier.supplier_id', $supplierId)
                    ->whereExists(function ($subQuery) {
                        $subQuery->select(DB::raw(1))
                            ->from('product_prices')
                            ->whereColumn('product_prices.product_supplier_id', 'product_supplier.id');
                    });
            });
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
        $template = CatalogTemplate::where('id', $id)
            ->where('supplier_id', Auth::user()->supplier->id)
            ->firstOrFail();

        $this->editingTemplateId = $template->id;
        $this->name = $template->name;

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

        if ($this->editingTemplateId) {
            $template = CatalogTemplate::where('id', $this->editingTemplateId)
                ->where('supplier_id', Auth::user()->supplier->id)
                ->firstOrFail();

            $template->update([
                'name' => $this->name,
                'settings' => $settings,
            ]);

            $this->successMessage = 'کاتالوگ با موفقیت بروزرسانی شد.';
        } else {
            CatalogTemplate::create([
                'supplier_id' => Auth::user()->supplier->id,
                'name' => $this->name,
                'settings' => $settings,
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
        if ($template->supplier_id === Auth::user()->supplier->id) {
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
        $templates = CatalogTemplate::where('supplier_id', Auth::user()->supplier->id)
            ->latest()
            ->paginate(10);

        return view('livewire.supplier.catalog.index', [
            'templates' => $templates
        ]);
    }
}
