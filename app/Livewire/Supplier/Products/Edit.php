<?php

namespace App\Livewire\Supplier\Products;

use App\Models\Category;
use App\Models\Product;
use App\Models\AttributeValue;
use App\Enums\ProductStatus;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

#[Layout('components.layouts.supplier')]
class Edit extends Component
{
    use WithFileUploads;

    public Product $product;
    public bool $isBranchCopy = false;

    public $title;
    public $slug;
    public $category_id;
    public $sku;
    public $is_active = true;
    public $short_description;
    public $full_description;

    public $primary_image;
    public $gallery_images = [];

    public $existing_primary_image;
    public $existing_gallery_images = [];

    // دسته‌بندی و ویژگی‌ها
    public $searchCategory = '';
    public $searchCategories = [];
    public $selectedCategoryName = '';
    public $categoryAttributes = [];
    public $attributeValues = [];
    public $newAttributeValues = [];

    public function mount(Product $product)
    {
        $user = auth()->user();
        $supplier = $user?->currentSupplier();

        if (!$supplier) {
            abort(403, 'پروفایل تأمین‌کننده معتبر نیست.');
        }

        // بررسی‌های امنیتی
        $this->product = $this->editableOwnedProduct($product, $supplier);
        $this->isBranchCopy = $supplier->isBranch()
            && $this->product->family_product_id !== null
            && (int) $this->product->family_product_id !== (int) $this->product->id;

        $this->title = $product->title;
        $this->slug = $product->slug;
        $this->category_id = $product->category_id;
        $this->sku = $product->sku;
        $this->is_active = $product->is_active;
        $this->short_description = $product->short_description;
        $this->full_description = $product->full_description;

        if ($this->category_id) {
            $category = Category::find($this->category_id);
            if ($category) {
                $this->selectedCategoryName = $category->name;
            }
        }

        $this->loadDefaultCategories();
        $this->loadCategoryAttributes();
        $this->loadExistingAttributeValues();

        // بارگذاری تصاویر فعلی
        $this->existing_primary_image = $product->galleries()->where('is_primary', true)->first();
        $this->existing_gallery_images = $product->galleries()->where('is_primary', false)->orderBy('sort_order')->get();
    }

    public function updatedTitle($value)
    {
        $this->slug = Str::slug($value, '-', null);
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
            } else {
                $this->categoryAttributes = [];
            }
        }
    }

    public function loadExistingAttributeValues()
    {
        $this->product = $this->editableOwnedProduct($this->product, auth()->user()->currentSupplier());
        $existingValues = $this->product->attributeValues()->pluck('attribute_values.id', 'attribute_id')->toArray();
        $this->attributeValues = $existingValues;
    }

    public function refreshAttributes()
    {
        $this->product = $this->editableOwnedProduct($this->product, auth()->user()->currentSupplier());
        $this->loadCategoryAttributes();
        $this->dispatch('notify', ['message' => 'ویژگی‌ها بروزرسانی شدند', 'type' => 'success']);
    }

    public function saveNewAttributeValue($attributeId)
    {
        $this->product = $this->editableOwnedProduct($this->product, auth()->user()->currentSupplier());
        $allowedForCategory = Category::query()
            ->whereKey($this->category_id)
            ->whereHas('attributes', fn ($query) => $query->whereKey($attributeId))
            ->exists();
        abort_unless($allowedForCategory, 403, 'این ویژگی برای دسته‌بندی محصول مجاز نیست.');

        $this->validate([
            'newAttributeValues.' . $attributeId => 'required|string|max:255',
        ]);
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

    public function removeExistingGalleryImage($galleryId)
    {
        $supplier = auth()->user()->currentSupplier();
        $product = $this->editableOwnedProduct($this->product, $supplier);

        $gallery = $product->galleries()->whereKey($galleryId)->where('is_primary', false)->first();
        if ($gallery) {
            Storage::disk('public')->delete($gallery->file_path);
            $gallery->delete();
            $this->product->markContentUpdated();
            $this->existing_gallery_images = $this->product->galleries()->where('is_primary', false)->orderBy('sort_order')->get();
        }
    }

    public function save()
    {
        $user = auth()->user();
        $supplierId = $user->currentSupplier()->id;

        if ($supplierId === null) {
            abort(403, 'پروفایل تأمین‌کننده معتبر نیست.');
        }

        // بررسی‌های امنیتی در زمان ذخیره (استفاده از مقایسه صحیح Enum)
        $this->product = $this->editableOwnedProduct($this->product, $user->currentSupplier());

        $this->validate([
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:products,slug,' . $this->product->id,
            'category_id' => 'required|exists:categories,id',
            'sku' => 'nullable|string|max:255|unique:products,sku,' . $this->product->id,
            'is_active' => 'boolean',
            'short_description' => 'nullable|string',
            'full_description' => 'nullable|string',
            'attributeValues.*' => 'nullable|exists:attribute_values,id',
            'primary_image' => 'nullable|image|max:2048',
            'gallery_images.*' => 'nullable|image|max:2048',
        ]);

        $selectedAttributeValueIds = collect($this->attributeValues)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values();
        if ($selectedAttributeValueIds->isNotEmpty()) {
            $validAttributeValueIds = DB::table('attribute_values')
                ->join('attribute_category', 'attribute_category.attribute_id', '=', 'attribute_values.attribute_id')
                ->where('attribute_category.category_id', $this->category_id)
                ->whereIn('attribute_values.id', $selectedAttributeValueIds)
                ->distinct()
                ->pluck('attribute_values.id');

            if ($validAttributeValueIds->count() !== $selectedAttributeValueIds->count()) {
                throw ValidationException::withMessages([
                    'attributeValues' => 'یکی از ویژگی‌ها با دسته‌بندی انتخاب‌شده سازگار نیست.',
                ]);
            }
        }

        if (empty($this->sku)) {
            $this->sku = 'PRD-' . strtoupper(Str::random(8));
        }

        $this->product->update([
            'title' => $this->title,
            'slug' => $this->slug,
            'category_id' => $this->category_id,
            'sku' => $this->sku,
            'is_active' => $this->is_active,
            'short_description' => $this->short_description,
            'full_description' => $this->full_description,
        ]);

        // اتصال ویژگی‌ها
        $validAttributeValues = array_filter($this->attributeValues);
        $this->product->attributeValues()->sync($validAttributeValues);

        // ذخیره تصویر اصلی در صورت تغییر
        if ($this->primary_image) {
            if ($this->existing_primary_image) {
                Storage::disk('public')->delete($this->existing_primary_image->file_path);
                $this->existing_primary_image->delete();
            }

            $path = $this->primary_image->store('products', 'public');
            $this->product->galleries()->create([
                'file_path' => $path,
                'is_primary' => true,
            ]);
        }

        // افزودن تصاویر جدید به گالری
        if (!empty($this->gallery_images)) {
            $maxSortOrder = $this->product->galleries()->where('is_primary', false)->max('sort_order') ?? 0;
            foreach ($this->gallery_images as $index => $galImage) {
                $path = $galImage->store('products/gallery', 'public');
                $this->product->galleries()->create([
                    'file_path' => $path,
                    'is_primary' => false,
                    'sort_order' => $maxSortOrder + $index + 1,
                ]);
            }
        }

        $this->product->markContentUpdated();

        session()->flash('success', 'اطلاعات محصول با موفقیت بروزرسانی شد.');

        return redirect()->route('supplier.products.index');
    }

    public function render()
    {
        return view('livewire.supplier.products.edit');
    }

    private function editableOwnedProduct(Product $product, $supplier): Product
    {
        abort_unless($supplier, 403, 'پروفایل تأمین‌کننده معتبر نیست.');

        $ownedProduct = Product::query()
            ->whereKey($product->id)
            ->where('supplier_id', $supplier->id)
            ->whereDoesntHave('productSuppliers', fn ($offers) => $offers->where('supplier_id', '!=', $supplier->id))
            ->firstOrFail();

        $isBranchCopy = $supplier->isBranch()
            && $ownedProduct->family_product_id !== null
            && (int) $ownedProduct->family_product_id !== (int) $ownedProduct->id;

        if (!$isBranchCopy && $ownedProduct->status !== ProductStatus::DRAFT && $ownedProduct->status !== 'draft') {
            abort(403, 'فقط محصولات پیش‌نویس و نسخه‌های مستقل شعبه قابل ویرایش هستند.');
        }

        return $ownedProduct;
    }
}

