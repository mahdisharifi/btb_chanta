<?php

namespace App\Livewire\Admin\Attributes;

use App\Enums\AttributeType;
use App\Models\Attribute;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
#[Layout('components.layouts.admin')]

class Edit extends Component
{
    public Attribute $attribute;

    public string $name = '';
    public string $type = '';
    public ?string $unit = null;
    public bool $is_filterable = false;

    public function mount(Attribute $attribute)
    {
        $this->attribute = $attribute;

        $this->name = $attribute->name;
        // استخراج صحیح مقدار از Enum
        $this->type = $attribute->type instanceof AttributeType
            ? $attribute->type->value
            : (string) $attribute->type;

        $this->unit = $attribute->unit;
        $this->is_filterable = $attribute->is_filterable;
    }

    public function rules()
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string'],
            'unit' => ['nullable', 'string', 'max:50'],
            'is_filterable' => ['boolean'],
        ];
    }

    #[Computed]
    public function typesList()
    {
        return AttributeType::cases();
    }

    #[Computed]
    public function similarAttributes()
    {
        // فقط اگر نام تغییر کرده باشد و بیش از ۲ کاراکتر باشد جستجو می‌کنیم
        if (strlen($this->name) > 2 && $this->name !== $this->attribute->name) {
            return Attribute::where('name', 'like', '%' . $this->name . '%')
                ->where('id', '!=', $this->attribute->id)
                ->take(5)
                ->get();
        }

        return [];
    }

    public function update()
    {
        $this->validate();

        $this->attribute->update([
            'name' => $this->name,
            'type' => $this->type,
            'unit' => $this->unit,
            'is_filterable' => $this->is_filterable,
        ]);

        session()->flash('success', 'ویژگی با موفقیت ویرایش شد.');

        return $this->redirectRoute('admin.attributes.index', navigate: true);
    }

    #[Layout('layouts.admin')]
    public function render()
    {
        return view('livewire.admin.attributes.edit');
    }
}
