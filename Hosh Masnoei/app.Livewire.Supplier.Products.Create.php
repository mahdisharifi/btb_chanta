<?php

namespace App\Livewire\Supplier\Products;

use App\Models\Category;
use App\Models\Product;
use App\Models\AttributeValue;
use App\Enums\ProductStatus;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Str;

#[Layout('components.layouts.supplier')]
class Create extends Component
{
    use WithFileUploads;

    public $title;
    public $category_id;
    public $is_active = true;
    public $short_description;
    public $full_description;

    public $primary_image;
    public $gallery_images = [];

    // دسته‌بندی و ویژگی‌ها
    public $searchCategory = '';
    public $searchCategories = [];
    public $selectedCategoryName = '';
    public $categoryAttributes = [];
    public $attributeValues = [];
    public $newAttributeValues = [];

    public function mount()
    {
        $this->loadDefaultCategories();
    }

    /**
     * تولید اسلاگ یکتا (بررسی شامل رکوردهای حذف شده با SoftDeletes)
     */
    protected function generateUniqueSlug($titleText)
    {
        $baseSlug = Str::slug($titleText, '-', null) ?: 'product-' . time();
        $slug = $baseSlug;
        $counter = 1;

        // استفاده از withTrashed برای جلوگیری از تداخل با اسلاگ محصولات حذف شده
        while (Product::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * تولید کد SKU یکتا (بررسی شامل رکوردهای حذف شده)
     */
    protected function generateUniqueSku()
    {
        $sku = 'PRD-' . strtoupper(Str::random(8));

        // استفاده از withTrashed برای اطمینان از یکتایی کامل
        while (Product::withTrashed()->where('sku', $sku)->exists()) {
            $sku = 'PRD-' . strtoupper(Str::random(8));
        }

        return $sku;
    }

    public function loadDefaultCategories()
    {
        $this->searchCategories = Category::whereDoesntHave('children')
            ->select('id', 'name')
            ->take(10)
            ->get();
    }

    public function updatedSearchCategory($value)
    {
        if (strlen($value) > 1) {
            $this->searchCategories = Category::where('name', 'like', '%' . $value . '%')
                ->whereDoesntHave('children')
                ->select('id', 'name')
                ->take(10)
                ->get();
        } else {
            $this->loadDefaultCategories();
        }
    }

    public function selectCategory($categoryId, $categoryName)
    {
        $this->category_id = $categoryId;
        $this->selectedCategoryName = $categoryName;

        $this->attributeValues = [];
        $this->loadCategoryAttributes();

        $this->searchCategory = '';
        $this->loadDefaultCategories();
    }

    public function loadCategoryAttributes()
    {
        if ($this->category_id) {
            $category = Category::with('attributes.values')->find($this->category_id);
            if ($category) {
                $this->categoryAttributes = $category->attributes;
            }
        }
    }

    public function refreshAttributes()
    {
        $this->loadCategoryAttributes();
        $this->dispatch('notify', ['message' => 'ویژگی‌ها بروزرسانی شدند', 'type' => 'success']);
    }

    public function saveNewAttributeValue($attributeId)
    {
        $value = trim($this->newAttributeValues[$attributeId] ?? '');

        if (empty($value)) return;

        $existing = AttributeValue::where('attribute_id', $attributeId)
            ->where('value', $value)
            ->first();

        if ($existing) {
            $this->attributeValues[$attributeId] = $existing->id;
        } else {
            $newValue = AttributeValue::create([
                'attribute_id' => $attributeId,
                'value' => $value
            ]);
            $this->attributeValues[$attributeId] = $newValue->id;
        }

        $this->newAttributeValues[$attributeId] = '';
        $this->loadCategoryAttributes();
    }

    public function save()
    {
        // اعتبارسنجی فقط برای فیلدهایی که از سمت کاربر می‌آیند
        $this->validate([
            // اضافه کردن یکتایی نام محصول با در نظر گرفتن رکوردهای حذف شده
            'title' => [
                'required',
                'string',
                'max:255',
                // یا اگر می‌خواهید فقط با محصولات فعال مقایسه شود:
                Rule::unique('products', 'title')->withoutTrashed(),
            ],
            'category_id' => 'required|exists:categories,id',
            'is_active' => 'boolean',
            'short_description' => 'nullable|string',
            'full_description' => 'nullable|string',
            'attributeValues.*' => 'nullable|exists:attribute_values,id',
            'primary_image' => 'nullable|image|max:2048',
            'gallery_images.*' => 'nullable|image|max:2048',
        ], [
            'title.unique' => 'این نام محصول قبلا در سیستم ثبت شده است.',
        ]);

        // تولید مقادیر یکتا در بک‌اند بدون دخالت کاربر
        $finalSlug = $this->generateUniqueSlug($this->title);
        $finalSku = $this->generateUniqueSku();

        // دریافت شناسه تامین‌کننده لاگین شده
        $supplierId = auth()->user()->supplier->id;

        // ایجاد محصول
        $product = Product::create([
            'supplier_id' => $supplierId,
            'title' => $this->title,
            'slug' => $finalSlug,
            'category_id' => $this->category_id,
            'sku' => $finalSku,
            'status' => ProductStatus::DRAFT->value ?? 'draft',
            'is_active' => false,
            'short_description' => $this->short_description,
            'description' => $this->full_description,
        ]);

        // اتصال ویژگی‌ها
        $validAttributeValues = array_filter($this->attributeValues);
        if (!empty($validAttributeValues)) {
            $product->attributeValues()->sync($validAttributeValues);
        }

        // ذخیره تصویر اصلی
        if ($this->primary_image) {
            $path = $this->primary_image->store('products', 'public');
            $product->galleries()->create([
                'file_path' => $path,
                'is_primary' => true,
            ]);
        }

        // ذخیره گالری تصاویر
        if (!empty($this->gallery_images)) {
            foreach ($this->gallery_images as $galImage) {
                $path = $galImage->store('products/gallery', 'public');
                $product->galleries()->create([
                    'file_path' => $path,
                    'is_primary' => false,
                ]);
            }
        }

        session()->flash('success', 'محصول با موفقیت ایجاد شد و در وضعیت پیش‌نویس قرار گرفت.');

        return redirect()->route('supplier.products.index');
    }

    public function render()
    {
        return view('livewire.supplier.products.create');
    }
}
