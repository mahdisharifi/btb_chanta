<?php

namespace App\Livewire\Admin\Products;

use App\Models\Category;
use App\Models\Product;
use App\Models\AttributeValue;
use App\Enums\ProductStatus;
use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;

class Edit extends Component
{
    use WithFileUploads;

    public Product $product;

    public $title;
    public $slug;
    public $category_id;
    public $sku;
    public $status;
    public $is_active = true;
    public $short_description;
    public $description;

    public $existingGallery = [];
    public $imagesToDelete = []; // آرایه‌ای برای نگهداری آیدی تصاویری که قرار است حذف شوند

    // Image properties (نام‌های صحیح که در Blade استفاده شده‌اند)
    public $new_primary_image;
    public $new_gallery_images = [];

    // Category and Attributes
    public $searchCategory = '';
    public $searchCategories = [];
    public $selectedCategoryName = '';
    public $categoryAttributes = [];
    public $attributeValues = [];

    public $lostAttributes = [];
    public $newAttributeValues = [];

    public function mount(Product $product)
    {
        $this->product = $product;

        $this->title = $product->title;
        $this->slug = $product->slug;
        $this->category_id = $product->category_id;
        $this->sku = $product->sku;
        $this->status = $product->status->value ?? $product->status;
        $this->is_active = $product->is_active;
        $this->short_description = $product->short_description;
        $this->description = $product->description;

        $this->loadDefaultCategories();

        if ($product->category) {
            $this->selectedCategoryName = $product->category->name;
            $this->loadCategoryAttributes();
        }

        // Initialize attribute values from product
        foreach ($this->product->attributeValues as $value) {
            $this->attributeValues[$value->attribute_id] = $value->id;
        }

        $this->loadGallery();
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

        $oldAttributeValues = $this->attributeValues;

        $this->loadCategoryAttributes();

        $newCategoryAttributeIds = collect($this->categoryAttributes)->pluck('id')->toArray();

        $this->attributeValues = [];
        $this->lostAttributes = [];

        $originalAttributes = $this->product->attributeValues->load('attribute');

        foreach ($originalAttributes as $oldValue) {
            $attrId = $oldValue->attribute_id;
            if (in_array($attrId, $newCategoryAttributeIds)) {
                $this->attributeValues[$attrId] = $oldAttributeValues[$attrId] ?? $oldValue->id;
            } else {
                $this->lostAttributes[] = [
                    'name' => $oldValue->attribute->name,
                    'value' => $oldValue->value
                ];
            }
        }

        foreach ($oldAttributeValues as $attrId => $valId) {
            if (in_array($attrId, $newCategoryAttributeIds)) {
                $this->attributeValues[$attrId] = $valId;
            }
        }

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

    public function loadGallery()
    {
        $this->existingGallery = $this->product->galleries()
            ->when(!empty($this->imagesToDelete), function ($query) {
                $query->whereNotIn('id', $this->imagesToDelete);
            })->get();
    }

    public function removeGalleryImage($imageId)
    {
        if (!in_array($imageId, $this->imagesToDelete)) {
            $this->imagesToDelete[] = $imageId;
        }

        $this->loadGallery();
    }

    public function save()
    {
        $this->validate([
            'title' => 'required|string|max:255',
            'slug' => ['required', 'string', 'max:255', Rule::unique('products')->ignore($this->product->id)],
            'category_id' => 'required|exists:categories,id',
            'sku' => ['required', 'string', 'max:255', Rule::unique('products')->ignore($this->product->id)],
            'status' => ['required', Rule::enum(ProductStatus::class)],
            'is_active' => 'boolean',
            'short_description' => 'nullable|string',
            'description' => 'nullable|string',
            'attributeValues.*' => 'nullable|exists:attribute_values,id',
            'new_primary_image' => 'nullable|image|max:2048',
            'new_gallery_images.*' => 'nullable|image|max:2048',
        ]);

        $this->product->update([
            'title' => $this->title,
            'slug' => $this->slug,
            'category_id' => $this->category_id,
            'sku' => $this->sku,
            'status' => $this->status,
            'is_active' => $this->is_active,
            'short_description' => $this->short_description,
            'description' => $this->description,
        ]);

        $validAttributeValues = array_filter($this->attributeValues);
        $this->product->attributeValues()->sync($validAttributeValues);

        // حذف فیزیکی تصاویر گالری علامت‌گذاری شده
        if (!empty($this->imagesToDelete)) {
            $imagesToRemove = $this->product->galleries()->whereIn('id', $this->imagesToDelete)->get();
            foreach ($imagesToRemove as $img) {
                if (!empty($img->file_path)) {
                    Storage::disk('public')->delete($img->file_path);
                }
                $img->delete();
            }
            $this->imagesToDelete = [];
        }

        // پردازش تصویر اصلی جدید
        if ($this->new_primary_image) {
            $oldPrimaryImage = $this->product->galleries()->where('is_primary', true)->first();

            if ($oldPrimaryImage && !empty($oldPrimaryImage->file_path)) {
                Storage::disk('public')->delete($oldPrimaryImage->file_path);
                $oldPrimaryImage->delete();
            }

            $path = $this->new_primary_image->store('products/gallery', 'public');
            $this->product->galleries()->create([
                'file_path' => $path,
                'is_primary' => true,
            ]);

            // پاک کردن متغیر پس از ذخیره موفق
            $this->new_primary_image = null;
        }

        // آپلود و اضافه کردن تصاویر گالری جدید
        if (!empty($this->new_gallery_images)) {
            foreach ($this->new_gallery_images as $galImage) {
                $path = $galImage->store('products/gallery', 'public');
                $this->product->galleries()->create([
                    'file_path' => $path,
                    'is_primary' => false,
                ]);
            }

            // پاک کردن متغیر پس از ذخیره موفق
            $this->new_gallery_images = [];
        }

        $this->loadGallery();

        $this->dispatch('notify', ['message' => 'محصول با موفقیت بروزرسانی شد.', 'type' => 'success']);
    }

    public function render()
    {
        return view('livewire.admin.products.edit')->layout('components.layouts.admin');
    }
}
