<?php

namespace App\Livewire\Admin\Attributes;

use App\Enums\AttributeType;
use App\Models\Attribute;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.admin')]
class Create extends Component
{
    public ?Attribute $attribute = null;
    public bool $isEditMode = false;

    public string $name = '';
    public string $type = 'text';
    public ?string $unit = null;
    public bool $is_filterable = false;
    public bool $is_multiple = false;
    public bool $is_visible = true;

    /**
     * @var array<int, array{id:?int,value:string,is_confirmed:bool,in_use:bool}>
     */
    public array $options = [];

    public array $typesList = [];

    public function mount(?Attribute $attribute = null): void
    {
        if ($attribute && $attribute->exists) {
            $this->attribute = $attribute;
            $this->isEditMode = true;
            $this->name = $attribute->name;
            $this->type = $attribute->type->value;
            $this->unit = $attribute->unit;
            $this->is_filterable = $attribute->is_filterable;
            $this->is_multiple = $attribute->is_multiple;
            $this->is_visible = $attribute->is_visible;

            if ($this->type !== 'boolean') {
                $this->options = $attribute->values
                    ->map(function ($val) {
                        return [
                            'id' => $val->id,
                            'value' => $val->value,
                            'is_confirmed' => true,
                            'in_use' => DB::table('attribute_product')
                                ->where('attribute_value_id', $val->id)
                                ->exists(),
                        ];
                    })
                    ->values()
                    ->all();

                if (empty($this->options)) {
                    $this->options = [$this->emptyOption()];
                }
            } else {
                $this->options = [];
            }
        }

        foreach (AttributeType::cases() as $typeEnum) {
            $this->typesList[$typeEnum->value] = $typeEnum->label();
        }

        if (! array_key_exists($this->type, $this->typesList)) {
            $this->type = 'text';
        }

        if ($this->type !== 'boolean' && empty($this->options)) {
            $this->options = [$this->emptyOption()];
        }
    }

    protected function emptyOption(): array
    {
        return [
            'id' => null,
            'value' => '',
            'is_confirmed' => false,
            'in_use' => false,
        ];
    }

    public function updatedType(): void
    {
        $this->resetValidation(['options', 'options.*.value']);

        if ($this->type === 'boolean') {
            $this->options = [];
            return;
        }

        if (empty($this->options)) {
            $this->options = [$this->emptyOption()];
        }
    }

    #[Computed]
    public function similarAttributes()
    {
        $name = trim($this->name);

        if (mb_strlen($name) < 2) {
            return collect();
        }

        $query = Attribute::query()
            ->where('name', 'like', '%' . $name . '%');

        if ($this->isEditMode) {
            $query->where('id', '!=', $this->attribute->id);
        }

        return $query->select(['id', 'name', 'type'])
            ->latest('id')
            ->limit(5)
            ->get();
    }

    public function addOption(): void
    {
        if ($this->type === 'boolean') {
            return;
        }

        foreach ($this->options as $index => $option) {
            if (trim((string) ($option['value'] ?? '')) === '') {
                $this->dispatch('focus-option-input', index: $index);
                return;
            }
        }

        array_unshift($this->options, $this->emptyOption());
    }

    public function confirmOption(int $index): void
    {
        if (! isset($this->options[$index])) {
            return;
        }

        $value = trim((string) ($this->options[$index]['value'] ?? ''));

        if ($value === '') {
            $this->addError('options.' . $index . '.value', 'مقدار نمی‌تواند خالی باشد.');
            $this->dispatch('focus-option-input', index: $index);
            return;
        }

        foreach ($this->options as $optionIndex => $option) {
            if ($optionIndex === $index) {
                continue;
            }

            if (trim((string) ($option['value'] ?? '')) === $value) {
                $this->addError('options.' . $index . '.value', 'مقادیر تکراری مجاز نیستند.');
                $this->dispatch('focus-option-input', index: $index);
                return;
            }
        }

        $this->resetErrorBag('options.' . $index . '.value');
        $this->options[$index]['value'] = $value;
        $this->options[$index]['is_confirmed'] = true;
    }

    public function editOption(int $index): void
    {
        if (! isset($this->options[$index])) {
            return;
        }

        if ($this->options[$index]['in_use']) {
            return;
        }

        $this->options[$index]['is_confirmed'] = false;
        $this->dispatch('focus-option-input', index: $index);
    }

    public function removeOption(int $index): void
    {
        if (! isset($this->options[$index])) {
            return;
        }

        if ($this->options[$index]['in_use']) {
            return;
        }

        unset($this->options[$index]);
        $this->options = array_values($this->options);

        if ($this->type !== 'boolean' && empty($this->options)) {
            $this->options = [$this->emptyOption()];
        }
    }

    protected function rules(): array
    {
        $rules = [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('attributes', 'name')->ignore($this->attribute?->id),
            ],
            'type' => [
                'required',
                new Enum(AttributeType::class),
            ],
            'unit' => ['nullable', 'string', 'max:50'],
            'is_filterable' => ['boolean'],
            'is_multiple' => ['boolean'],
            'is_visible' => ['boolean'],
        ];

        if ($this->type !== 'boolean') {
            $rules['options'] = ['nullable', 'array', 'max:500'];
            $rules['options.*.value'] = ['nullable', 'string', 'max:255', 'distinct'];
        }

        return $rules;
    }

    protected function messages(): array
    {
        return [
            'name.required' => 'نام ویژگی الزامی است.',
            'name.unique' => 'این ویژگی قبلاً ثبت شده است. لطفاً از لیست ویژگی‌های موجود استفاده کنید یا نام دیگری انتخاب کنید.',
            'type.required' => 'نوع ویژگی الزامی است.',
            'unit.max' => 'واحد نمی‌تواند بیشتر از ۵۰ کاراکتر باشد.',
            'options.max' => 'حداکثر ۱۰۰ مقدار مجاز است.',
            'options.*.value.max' => 'هر مقدار حداکثر ۲۵۵ کاراکتر می‌تواند باشد.',
            'options.*.value.distinct' => 'مقادیر تکراری مجاز نیستند.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'name' => 'نام ویژگی',
            'type' => 'نوع ویژگی',
            'unit' => 'واحد اندازه‌گیری',
            'is_filterable' => 'قابلیت فیلتر',
            'is_multiple' => 'چند مقداری بودن',
            'is_visible' => 'نمایش',
            'options' => 'مقادیر از پیش‌تعریف‌شده',
            'options.*.value' => 'مقدار',
        ];
    }

    public function save()
    {
        $this->name = trim($this->name);
        $this->unit = filled($this->unit) ? trim($this->unit) : null;

        if ($this->type === 'boolean') {
            $this->options = [];
        } else {
            $preparedOptions = collect($this->options)
                ->map(function (array $option) {
                    return [
                        'id' => $option['id'] ?? null,
                        'value' => trim((string) ($option['value'] ?? '')),
                        'is_confirmed' => (bool) ($option['is_confirmed'] ?? false),
                        'in_use' => (bool) ($option['in_use'] ?? false),
                    ];
                })
                ->filter(fn (array $option) => $option['value'] !== '')
                ->values();

            foreach ($preparedOptions as $index => $option) {
                if (! $option['is_confirmed']) {
                    $this->addError('options.' . $index . '.value', 'ابتدا مقدار را تایید کنید.');
                    return;
                }
            }

            $this->options = $preparedOptions
                ->values()
                ->all();
        }

        $validated = $this->validate();

        $skippedInUse = [];

        DB::transaction(function () use ($validated, &$skippedInUse) {
            $data = [
                'name' => $validated['name'],
                'type' => $validated['type'],
                'unit' => $validated['unit'] ?? null,
                'is_filterable' => (bool) ($validated['is_filterable'] ?? false),
                'is_multiple' => (bool) ($validated['is_multiple'] ?? false),
                'is_visible' => (bool) ($validated['is_visible'] ?? true),
            ];

            if ($this->isEditMode) {
                $this->attribute->update($data);
                $attribute = $this->attribute;
            } else {
                $attribute = Attribute::create($data);
            }

            $submittedValues = collect($validated['options'] ?? [])
                ->pluck('value')
                ->map(fn ($v) => trim((string) $v))
                ->filter()
                ->unique()
                ->values();

            $existingValuesModels = $attribute->values()->get();
            $existingValuesMap = $existingValuesModels->mapWithKeys(function ($item) {
                return [trim((string) $item->value) => $item];
            });

            foreach ($submittedValues as $value) {
                if (! $existingValuesMap->has($value)) {
                    $attribute->values()->create(['value' => $value]);
                }
            }

            $removedValues = $existingValuesMap->keys()->diff($submittedValues);

            foreach ($removedValues as $value) {
                $model = $existingValuesMap->get($value);

                $inUse = DB::table('attribute_product')
                    ->where('attribute_value_id', $model->id)
                    ->exists();

                if ($inUse) {
                    $skippedInUse[] = $value;
                    continue;
                }

                $model->delete();
            }
        });

        $message = $this->isEditMode ? 'ویژگی با موفقیت ویرایش شد.' : 'ویژگی جدید با موفقیت ایجاد شد.';

        if (! empty($skippedInUse)) {
            $message .= ' توجه: مقادیر «' . implode('، ', $skippedInUse) . '» به‌دلیل استفاده در محصولات حذف نشدند.';
        }

        session()->flash('success', $message);

        return $this->redirectRoute('admin.attributes.index', navigate: true);
    }

    public function render()
    {
        return view('livewire.admin.attributes.create');
    }
}
