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
    use \App\Livewire\Concerns\ManagesProductQuantityTiers;
    use \App\Livewire\Concerns\HasProductFormSteps;

    #[\Livewire\Attributes\Locked]
    public bool $isEdit = true;
    #[\Livewire\Attributes\Locked]
    public $productId;
    #[\Livewire\Attributes\Locked]
    public $supplier_id;
    public bool $supplierDraftMode = false;
    public array $quantity_tiers = [];
    public int $quantity_per_packaging = 1;
    public string $packaging_unit_type = 'carton';
    public bool $tiersApplied = false;
    public bool $showQuantityTiers = false;
    public $product_video;
    public $video_poster;

    public Product $product;
    public bool $isBranchCopy = false;

    public $title;
    public $slug;
    public $category_id;
    #[\Livewire\Attributes\Locked]
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
        $this->productId = $this->product->id;
        $this->firstFormStepSaved = true;
        $this->supplier_id = $supplier->id;
        $this->quantity_per_packaging = $product->quantity_per_packaging ?? 1;
        $this->packaging_unit_type = $product->packaging_unit_type ?? 'carton';
        $this->quantity_tiers = $product->quantity_tiers ?: $this->generateAutoTiers();
        $this->tiersApplied = true;
        $this->showQuantityTiers = true;
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
        $this->existing_gallery_images = $product->galleries()->where('is_primary', false)->where('file_type', 'image')->orderBy('sort_order')->get();
        if ((int) request()->query('step') === 3) {
            $this->goToPricingStep();
        }
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
            $this->existing_gallery_images = $this->product->galleries()->where('is_primary', false)->where('file_type', 'image')->orderBy('sort_order')->get();
        }
    }

    protected function authorizeProductFormStep(): void
    {
        $supplier = auth()->user()?->currentSupplier();
        $this->product = $this->editableOwnedProduct($this->product, $supplier);
        $this->supplier_id = $supplier->id;
    }

    public function save()
    {
        return $this->persistProductForm(false);
    }

    protected function persistProductForm(bool $firstStepOnly)
    {
        $user = auth()->user();
        $supplierId = $user->currentSupplier()->id;

        if ($supplierId === null) {
            abort(403, 'پروفایل تأمین‌کننده معتبر نیست.');
        }

        // بررسی‌های امنیتی در زمان ذخیره (استفاده از مقایسه صحیح Enum)
        $this->product = $this->editableOwnedProduct($this->product, $user->currentSupplier());

        $this->validateFirstFormStep();

        $rules = [
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
        ];
        if ($firstStepOnly) {
            unset($rules['short_description'], $rules['full_description'], $rules['attributeValues.*']);
        }
        $this->validate($rules);

        $selectedAttributeValueIds = collect($firstStepOnly ? [] : $this->attributeValues)
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
            $this->sku = Product::generateUniqueSku();
        }

        $payload = [
            'title' => $this->title,
            'slug' => $this->slug,
            'category_id' => $this->category_id,
            'sku' => $this->sku,
            'is_active' => $this->is_active,
            'short_description' => $this->short_description,
            'full_description' => $this->full_description,
            'quantity_per_packaging' => $this->quantity_per_packaging,
            'packaging_unit_type' => $this->packaging_unit_type,
            'quantity_tiers' => $this->quantity_tiers,
        ];
        if ($firstStepOnly) {
            unset($payload['short_description'], $payload['full_description']);
        }
        $this->product->update($payload);

        // اتصال ویژگی‌ها
        $validAttributeValues = array_filter($this->attributeValues);
        if (!$firstStepOnly) {
            $this->product->attributeValues()->sync($validAttributeValues);
        }
        app(\App\Services\CatalogSnapshotRevisionService::class)->invalidateGlobal();

        // ذخیره تصویر اصلی در صورت تغییر
        if ($this->primary_image) {
            if ($this->existing_primary_image) {
                Storage::disk('public')->delete($this->existing_primary_image->file_path);
                $this->existing_primary_image->delete();
            }

            $path = $this->primary_image->store('products', 'public');
            $this->product->galleries()->create([
                'file_path' => $path,
                'file_type' => 'image',
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
                    'file_type' => 'image',
                    'is_primary' => false,
                    'sort_order' => $maxSortOrder + $index + 1,
                ]);
            }
        }

        if ($this->product_video) {
            $videoPath = $this->product_video->store('products/videos', 'public');
            $posterPath = $this->video_poster?->store('products/videos/posters', 'public');
            $this->product->galleries()->create([
                'file_path' => $videoPath,
                'file_type' => 'video',
                'mime_type' => $this->product_video->getMimeType(),
                'poster_path' => $posterPath,
                'is_primary' => false,
                'sort_order' => ($this->product->galleries()->max('sort_order') ?? 0) + 1,
            ]);
        }

        $this->product->markContentUpdated();

        session()->flash('success', 'اطلاعات محصول با موفقیت بروزرسانی شد.');

        $this->primary_image = null;
        $this->gallery_images = [];
        $this->product_video = null;
        $this->video_poster = null;
        $this->existing_primary_image = $this->product->galleries()->where('is_primary', true)->first();
        $this->existing_gallery_images = $this->product->galleries()->where('is_primary', false)->where('file_type', 'image')->orderBy('sort_order')->get();

        return ($firstStepOnly || $this->continueToPricing) ? null : redirect()->route('supplier.products.index');
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

        if ($ownedProduct->status !== ProductStatus::DRAFT && $ownedProduct->status !== 'draft') {
            abort(403, 'فقط محصولات پیش‌نویس و نسخه‌های مستقل شعبه قابل ویرایش هستند.');
        }

        return $ownedProduct;
    }
}

