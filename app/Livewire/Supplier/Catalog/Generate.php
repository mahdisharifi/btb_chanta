<?php

namespace App\Livewire\Supplier\Catalog;

use Livewire\Component;
use App\Models\Category;
use App\Models\CatalogTemplate;
use App\Enums\PriceType;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;

#[Layout('components.layouts.supplier')]
class Generate extends Component
{
    public $existingCatalogs = [];
    public $selectedCatalogId = '';

    public $name = '';
    public $selectedCategories = [];
    public $selectAllCategories = false;
    public $selectedPriceTypes = [];
    public $showCoverPage = true;

    // سطوح مرتب‌سازی (فیلد و جهت مجزا)
    public $sortField1 = '';
    public $sortDir1 = 'desc';
    public $sortField2 = '';
    public $sortDir2 = 'desc';
    public $sortField3 = '';
    public $sortDir3 = 'desc';

    public function mount()
    {
        $this->loadExistingCatalogs();
    }

    public function loadExistingCatalogs()
    {
        $supplierId = Auth::user()->supplier->id;
        $this->existingCatalogs = CatalogTemplate::where('supplier_id', $supplierId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    // انتخاب/لغو انتخاب همه دسته‌بندی‌ها
    public function updatedSelectAllCategories($value)
    {
        if ($value) {
            $this->selectedCategories = Category::where('is_active', true)->pluck('id')->map(fn($id) => (string)$id)->toArray();
        } else {
            $this->selectedCategories = [];
        }
    }

    // بررسی تیک «انتخاب همه» در صورت تغییر دستی
    public function updatedSelectedCategories($value)
    {
        $total = Category::where('is_active', true)->count();
        $this->selectAllCategories = (count($this->selectedCategories) === $total && $total > 0);
    }

    // ریست شدن خودکار اولویت‌های بعدی در صورت خالی کردن اولویت فعلی
    public function updatedSortField1($value)
    {
        if (empty($value)) {
            $this->sortField2 = '';
            $this->sortField3 = '';
        }
    }

    public function updatedSortField2($value)
    {
        if (empty($value)) {
            $this->sortField3 = '';
        }
    }

    public function updatedSelectedCatalogId($value)
    {
        if ($value) {
            $template = CatalogTemplate::where('supplier_id', Auth::user()->supplier->id)
                ->find($value);

            if ($template) {
                $this->name = $template->name;
                $this->selectedCategories = $template->selected_categories ?? [];
                $this->selectedPriceTypes = $template->selected_price_types ?? [];
                $this->showCoverPage = $template->settings['show_cover'] ?? true;

                $this->updatedSelectedCategories($this->selectedCategories);

                $sorts = $template->settings['sort'] ?? [];
                $this->sortField1 = $sorts[0]['field'] ?? '';
                $this->sortDir1   = $sorts[0]['dir'] ?? 'desc';
                $this->sortField2 = $sorts[1]['field'] ?? '';
                $this->sortDir2   = $sorts[1]['dir'] ?? 'desc';
                $this->sortField3 = $sorts[2]['field'] ?? '';
                $this->sortDir3   = $sorts[2]['dir'] ?? 'desc';
            }
        } else {
            $this->resetForm();
        }
    }

    public function resetForm()
    {
        $this->reset([
            'name', 'selectedCategories', 'selectAllCategories', 'selectedPriceTypes',
            'showCoverPage', 'sortField1', 'sortDir1', 'sortField2', 'sortDir2', 'sortField3', 'sortDir3'
        ]);
    }

    // تولید فیلدهای مرتب‌سازی طبق درخواست
    public function getSortFieldsProperty()
    {
        $fields = [
            'category'         => 'دسته‌بندی',
            'name'             => 'نام محصول',
            'created_at'       => 'تاریخ ایجاد',
            'price_updated_at' => 'تاریخ تغییر قیمت',
        ];

        foreach (PriceType::cases() as $type) {
            $fields['price_' . $type->value] = 'قیمت: ' . $type->label();
        }

        return $fields;
    }

    public function generate()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'selectedCategories' => 'array',
            'selectedPriceTypes' => 'array',
        ]);

        $supplierId = Auth::user()->supplier->id;

        // ذخیره سه‌سطح سورت بصورت آرایه‌ای از آبجکت‌ها
        $sorts = [];
        if (!empty($this->sortField1)) $sorts[] = ['field' => $this->sortField1, 'dir' => $this->sortDir1];
        if (!empty($this->sortField2)) $sorts[] = ['field' => $this->sortField2, 'dir' => $this->sortDir2];
        if (!empty($this->sortField3)) $sorts[] = ['field' => $this->sortField3, 'dir' => $this->sortDir3];

        $settings = [
            'show_cover' => $this->showCoverPage,
            'sort' => $sorts
        ];

        $template = CatalogTemplate::updateOrCreate(
            [
                'id' => $this->selectedCatalogId ?: null,
                'supplier_id' => $supplierId,
            ],
            [
                'name' => $this->name,
                'selected_categories' => $this->selectedCategories,
                'selected_price_types' => $this->selectedPriceTypes,
                'settings' => $settings,
                'supplier_id' => $supplierId,
            ]
        );

        return redirect()->route('supplier.catalog.preview', ['catalog' => $template->id]);
    }

    public function render()
    {
        return view('livewire.supplier.catalog.generate', [
            'categories' => Category::where('is_active', true)->get(),
            'priceTypes' => PriceType::cases(),
        ]);
    }

}
