<?php

namespace App\Livewire\Admin\Catalogs;

use App\Enums\PriceType;
use App\Models\CatalogTemplate;
use App\Models\Category;
use App\Models\Supplier;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
#[Layout('components.layouts.admin')]

class Form extends Component
{
    public ?CatalogTemplate $catalogTemplate = null;

    public string $name = '';
    public array $supplier_ids = [];

    public array $selectedCategories = [];
    public bool $selectAllCategories = false;

    public array $selectedPriceTypes = [];

    public bool $showCoverPage = true;

    public string $sortField1 = '';
    public string $sortDir1 = 'desc';
    public string $sortField2 = '';
    public string $sortDir2 = 'desc';
    public string $sortField3 = '';
    public string $sortDir3 = 'desc';

    public bool $isEdit = false;

    public function mount(?CatalogTemplate $catalogTemplate = null): void
    {
        $this->catalogTemplate = $catalogTemplate;
        $this->isEdit = $catalogTemplate && $catalogTemplate->exists;

        if (! $this->isEdit) {
            return;
        }

        $this->name = (string) $this->catalogTemplate->name;

        $configSupplierIds = $this->catalogTemplate->config['supplier_ids'] ?? null;

        if (is_array($configSupplierIds)) {
            $this->supplier_ids = array_values(array_filter($configSupplierIds));
        } elseif ($this->catalogTemplate->supplier_id) {
            $this->supplier_ids = [$this->catalogTemplate->supplier_id];
        }

        // خواندن مقادیر تنظیمات از آرایه settings به جای ستون‌های دیتابیس
        $settings = $this->catalogTemplate->settings ?? [];

        $this->selectedCategories = $settings['categories'] ?? [];
        $this->selectedPriceTypes = $settings['price_types'] ?? [];

        $this->showCoverPage = (bool) ($settings['show_cover'] ?? true);

        $sorts = $settings['sort'] ?? [];

        $this->sortField1 = $sorts[0]['field'] ?? '';
        $this->sortDir1 = $sorts[0]['dir'] ?? 'desc';

        $this->sortField2 = $sorts[1]['field'] ?? '';
        $this->sortDir2 = $sorts[1]['dir'] ?? 'desc';

        $this->sortField3 = $sorts[2]['field'] ?? '';
        $this->sortDir3 = $sorts[2]['dir'] ?? 'desc';

        $activeCategoryIds = Category::query()
            ->where('is_active', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $selectedCategoryIds = array_map('intval', $this->selectedCategories);

        $this->selectAllCategories = count($activeCategoryIds) > 0
            && empty(array_diff($activeCategoryIds, $selectedCategoryIds));
    }

    protected function rules(): array
    {
        $sortFieldKeys = array_keys($this->sortFields);
        $priceTypeValues = array_map(fn (PriceType $type) => $type->value, PriceType::cases());

        return [
            'name' => ['required', 'string', 'max:255'],

            'supplier_ids' => ['required', 'array', 'min:1'],
            'supplier_ids.*' => ['integer', 'exists:suppliers,id'],

            'selectedCategories' => ['nullable', 'array'],
            'selectedCategories.*' => ['integer', 'exists:categories,id'],

            'selectedPriceTypes' => ['nullable', 'array'],
            'selectedPriceTypes.*' => ['string', Rule::in($priceTypeValues)],

            'showCoverPage' => ['boolean'],

            'sortField1' => ['nullable', 'string', Rule::in($sortFieldKeys)],
            'sortDir1' => ['required_with:sortField1', Rule::in(['asc', 'desc'])],

            'sortField2' => ['nullable', 'string', 'different:sortField1', Rule::in($sortFieldKeys)],
            'sortDir2' => ['required_with:sortField2', Rule::in(['asc', 'desc'])],

            'sortField3' => ['nullable', 'string', 'different:sortField1', 'different:sortField2', Rule::in($sortFieldKeys)],
            'sortDir3' => ['required_with:sortField3', Rule::in(['asc', 'desc'])],
        ];
    }

    public function updatedSelectAllCategories(bool $value): void
    {
        $this->selectedCategories = $value
            ? Category::query()->where('is_active', true)->pluck('id')->map(fn ($id) => (int) $id)->all()
            : [];
    }

    public function updatedSelectedCategories(): void
    {
        $activeCategoryCount = Category::query()->where('is_active', true)->count();

        $this->selectAllCategories = $activeCategoryCount > 0
            && count($this->selectedCategories) === $activeCategoryCount;
    }

    public function updatedSortField1(): void
    {
        if ($this->sortField1 === '') {
            $this->sortField2 = '';
            $this->sortField3 = '';
        }
    }

    public function updatedSortField2(): void
    {
        if ($this->sortField2 === '') {
            $this->sortField3 = '';
        }
    }

    public function getSortFieldsProperty(): array
    {
        $fields = [
            'category' => 'دسته بندی',
            'name' => 'نام محصول',
            'created_at' => 'تاریخ ایجاد',
            'price_updated_at' => 'تاریخ تغییر قیمت',
        ];

        foreach (PriceType::cases() as $type) {
            $fields['price_' . $type->value] = 'قیمت: ' . $type->label();
        }

        return $fields;
    }

    public function save()
    {
        $validated = $this->validate();

        $supplierIds = array_values(array_unique(array_map('intval', $validated['supplier_ids'])));
        $selectedCategories = array_values(array_unique(array_map('intval', $validated['selectedCategories'] ?? [])));
        $selectedPriceTypes = array_values(array_unique($validated['selectedPriceTypes'] ?? []));

        $sorts = [];
        foreach ([1, 2, 3] as $level) {
            $field = $this->{'sortField' . $level};
            $dir = $this->{'sortDir' . $level};
            if ($field !== '') {
                $sorts[] = ['field' => $field, 'dir' => $dir === 'asc' ? 'asc' : 'desc'];
            }
        }

        // بازسازی ساختار settings
        $settings = $this->catalogTemplate ? ($this->catalogTemplate->settings ?? []) : [];
        $settings['show_cover'] = $this->showCoverPage;
        $settings['sort'] = $sorts;
        $settings['categories'] = $selectedCategories;
        $settings['price_types'] = $selectedPriceTypes;

        $data = [
            'name' => trim($validated['name']),
            'supplier_id' => count($supplierIds) === 1 ? $supplierIds[0] : null,
            'config' => ['supplier_ids' => $supplierIds],
            'settings' => $settings,
        ];

        if ($this->isEdit) {
            $this->catalogTemplate->update($data);
        } else {
            CatalogTemplate::create($data);
        }

        session()->flash('success', 'کاتالوگ با موفقیت ذخیره شد.');
        return redirect()->route('admin.catalogs.index');
    }

    public function render()
    {
        return view('livewire.admin.catalogs.form', [
            'suppliers' => Supplier::query()->orderBy('company_name')->get(),
            'categories' => Category::query()->where('is_active', true)->orderBy('name')->get(),
            'priceTypes' => PriceType::cases(),
            'sortFields' => $this->sortFields,
        ]);
    }
}
