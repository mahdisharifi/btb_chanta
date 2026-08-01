<?php

namespace App\Livewire\Admin\Categories;
use Illuminate\Support\Facades\DB;

use App\Models\Category;
use App\Models\Attribute;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
#[Layout('components.layouts.admin')]

class Edit extends Component
{
    public Category $category;

    public $name;
    public $parent_id;
    public $description;
    public $icon;
    public $sort_order = 0;
    public $is_active = true;
    public $seo_title;
    public $seo_description;
    public $seo_keywords;

    // متغیرهای مربوط به ویژگی‌ها
    public $selectedAttributes = [];
    public $isLeafNode = false;

    public const ICONS = [
        'squares-2x2', 'cube', 'cube-transparent', 'tag', 'shopping-bag',
        'shopping-cart', 'gift', 'archive-box', 'building-storefront',
        'building-office', 'truck', 'wrench', 'wrench-screwdriver',
        'cog-6-tooth', 'cpu-chip', 'device-phone-mobile', 'computer-desktop',
        'tv', 'camera', 'printer', 'bolt', 'light-bulb', 'fire', 'sparkles',
        'beaker', 'paint-brush', 'scissors', 'home', 'home-modern', 'key',
        'lock-closed', 'heart', 'star', 'sun', 'moon', 'cloud', 'globe-alt',
        'map-pin', 'book-open', 'academic-cap', 'briefcase', 'banknotes',
        'credit-card', 'chart-bar', 'clipboard-document-list', 'folder',
        'document-text', 'musical-note', 'film', 'puzzle-piece',
        'rocket-launch', 'trophy', 'wallet', 'phone', 'envelope', 'bell',
        'calendar', 'clock', 'user-group', 'swatch', 'rectangle-stack',
        'circle-stack', 'wifi', 'funnel', 'cake', 'photo', 'newspaper',
        'megaphone', 'shield-check', 'ticket', 'eye', 'link', 'server',
    ];


    public function mount(Category $category)
    {
        $this->category = $category;
        $this->name = $category->name;
        $this->parent_id = $category->parent_id;
        $this->description = $category->description;
        $this->icon = $category->icon;
        $this->sort_order = $category->sort_order;
        $this->is_active = $category->is_active;
        $this->seo_title = $category->seo_title;
        $this->seo_description = $category->seo_description;
        $this->seo_keywords = $category->seo_keywords;

        // بررسی اینکه آیا این دسته‌بندی نهایی است (فرزندی ندارد)
        $this->isLeafNode = !$category->children()->exists();

        // اگر دسته‌بندی نهایی است، ویژگی‌های متصل به آن را لود کن
        if ($this->isLeafNode) {
            $this->selectedAttributes = $category->attributes()->pluck('attributes.id')->toArray();
        }
    }

    protected function rules()
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('categories', 'name')->ignore($this->category->id),
            ],
            'parent_id' => [
                'nullable',
                'integer',
                'exists:categories,id',
                // جلوگیری از انتخاب خود دسته‌بندی به عنوان والد
                function ($attribute, $value, $fail) {
                    if ($value == $this->category->id) {
                        $fail('یک دسته‌بندی نمی‌تواند والد خودش باشد.');
                    }
                },
            ],
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:255',
            'sort_order' => 'integer|min:0',
            'is_active' => 'boolean',
            'seo_title' => 'nullable|string|max:255',
            'seo_description' => 'nullable|string',
            'seo_keywords' => 'nullable|string|max:255',

            // اعتبارسنجی آرایه ویژگی‌های انتخاب شده
            'selectedAttributes' => 'nullable|array',
            'selectedAttributes.*' => 'exists:attributes,id',
        ];
    }
    // افزودن این متدها به کلاس Edit.php

    public function addAttribute($attributeId)
    {
        if (!in_array($attributeId, $this->selectedAttributes)) {
            $this->selectedAttributes[] = $attributeId;
            $this->resetErrorBag('attribute_remove');
        }
    }

    public function removeAttribute($attributeId)
    {
        // استفاده از DB facade و اتصال به جدول attribute_values برای رسیدن به attribute_id
        $isUsedByProducts = \Illuminate\Support\Facades\DB::table('attribute_product')
            ->join('products', 'products.id', '=', 'attribute_product.product_id')
            // فرض بر این است که جدول attribute_values کلید attribute_id را دارد
            ->join('attribute_values', 'attribute_values.id', '=', 'attribute_product.attribute_value_id')
            ->where('products.category_id', $this->category->id)
            ->where('attribute_values.attribute_id', $attributeId) // شرط روی جدول values اعمال می‌شود
            ->whereNotNull('attribute_product.value')
            ->where('attribute_product.value', '!=', '')
            ->whereNull('products.deleted_at') // در صورت استفاده از SoftDeletes
            ->exists();

        if ($isUsedByProducts) {
            $this->addError('attribute_remove', 'این ویژگی توسط حداقل یک محصول در این دسته‌بندی مقداردهی شده است و قابل حذف نیست.');
            return;
        }

        $this->selectedAttributes = array_values(array_diff($this->selectedAttributes, [$attributeId]));
        $this->resetErrorBag('attribute_remove');
    }

    public function update()
    {
        $this->validate();

        $this->category->update([
            'name' => $this->name,
            'parent_id' => $this->parent_id ?: null,
            'description' => $this->description,
            'icon' => $this->icon,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'seo_title' => $this->seo_title,
            'seo_description' => $this->seo_description,
            'seo_keywords' => $this->seo_keywords,
        ]);

        // بررسی مجدد اینکه آیا دسته‌بندی در لحظه ذخیره‌سازی فرزندی دارد یا خیر
        // (ممکن است در همین حین دسته‌بندی دیگری زیرمجموعه آن شده باشد)
        $hasChildren = Category::where('parent_id', $this->category->id)->exists();

        if (!$hasChildren) {
            // اگر فرزند ندارد (نهایی است)، ویژگی‌ها را سینک کن
            $this->category->attributes()->sync($this->selectedAttributes);
        } else {
            // اگر از حالت نهایی خارج شده (فرزندی به آن اضافه شده)، تمام ویژگی‌های متصل را جدا کن
            $this->category->attributes()->detach();
        }

        session()->flash('success', 'دسته‌بندی با موفقیت ویرایش شد.');
        return redirect()->route('admin.categories.index');
    }

    public function render()
    {
        $parentCategories = \App\Models\Category::whereNull('parent_id')
            ->where('id', '!=', $this->category->id)
            ->get();

        $allAttributes = \App\Models\Attribute::orderBy('name')->get();

        // محاسبه تعداد محصولاتی که هر ویژگی را در این دسته‌بندی پر کرده‌اند
        $attributeProductCounts = \DB::table('attribute_product')
            ->join('products', 'products.id', '=', 'attribute_product.product_id')
            ->join('attribute_values', 'attribute_values.id', '=', 'attribute_product.attribute_value_id')
            ->whereNull('products.deleted_at') // در صورت استفاده از SoftDeletes برای محصولات
            ->select('attribute_values.attribute_id', DB::raw('count(distinct attribute_product.product_id) as count'))
            ->groupBy('attribute_values.attribute_id')
            ->pluck('count', 'attribute_values.attribute_id')
            ->toArray();



        return view('livewire.admin.categories.edit', [
            'parentCategories' => $parentCategories,
            'allAttributes' => $allAttributes,
            'attributeProductCounts' => $attributeProductCounts,
        ]);
    }
}
