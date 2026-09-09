<?php

namespace App\Livewire\Admin\Products;
use App\Services\ProductImageService;
use App\Traits\GeneratesPersianSlug;
use App\Models\ProductGallery;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\AttributeValue;
use App\Enums\ProductStatus;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use App\Helpers\QuantityTierValidator;

#[Layout('components.layouts.admin')]
class Create extends Component
{
    use WithFileUploads;
    use GeneratesPersianSlug;

    public bool $is_featured = false;
    public $brand = null;

    // شناسه محصول در حالت ویرایش (null یعنی حالت ایجاد)
    public $productId = null;
    public bool $isEdit = false;


    public $existingGalleries = [];
    public $galleriesToDelete = [];
    public $galleryOrder = [];


    public $title;
    public $slug;
    public bool $slugManuallyEdited = false;    public $category_id;
    public $supplier_id;
    public $sku;
    public $status;
    public $is_active = true;
    public $short_description;
    public $full_description;

    public $primary_image;
    public $gallery_images = [];
    public $product_video;
    public $video_poster;  // ✅ اضافه شد - thumbnail ویدئو

    // تصاویر/رسانه‌های موجود (فقط در حالت ویرایش)

    // دسته‌بندی و ویژگی‌ها
    public $searchCategory = '';
    public $searchCategories = [];
    public $selectedCategoryName = '';
    public $categoryAttributes = [];
    public $attributeValues = [];
    public $newAttributeValues = [];
    public $selectedAttributeValues = []; // ساختار: [attribute_id => [value_id1, ...]] یا [attribute_id => value_id]

    public $suppliers = [];



    public $seo_title;
    public $seo_description;
    public $seo_keywords;
    public $seo_canonical_url;
    public bool $canonicalManuallyEdited = false;

    // تیراژهای محصول
    public $quantity_tiers = [];
    public $showQuantityTiers = false;
    public bool $hasExistingTiers = false; // ✅ محصول تیراژ دارد؟
    public bool $tiersApplied = false; // ✅ آیا تیراژها اعمال شده‌اند؟
    
    // ✅ واحد بسته‌بندی و تیراژبندی خودکار
    public string $packaging_unit_type = 'carton';
    public int $quantity_per_packaging = 1;





    public function mount(?Product $product = null)
    {
        $this->suppliers = Supplier::select('id', 'company_name')->get();
        $this->loadDefaultCategories();

        // اگر مدل محصول از طریق Route Model Binding تزریق شده باشد → حالت ویرایش
        if ($product && $product->exists) {
            $this->isEdit = true;
            $this->productId = $product->id;
            $this->fillFromProduct($product);
        } else {
            // حالت ایجاد
            $this->status = ProductStatus::DRAFT->value;
            $this->sku = 'محصول-' . strtoupper(Str::random(8));
        }
    }

    // پر کردن فرم از روی محصول موجود (حالت ویرایش)
    protected function fillFromProduct(Product $product): void
    {


        $this->title       = $product->title;
        $this->slug        = $product->slug;
        $this->category_id = $product->category_id;
        $this->supplier_id = $product->supplier_id;
        $this->sku         = $product->sku;

        $this->status = $product->status instanceof \BackedEnum
            ? $product->status->value
            : $product->status;

        $this->is_active         = (bool) $product->is_active;
        $this->is_featured       = (bool) $product->is_featured;
        $this->brand             = $product->brand;
        $this->short_description = $product->short_description;
        $this->full_description  = $product->full_description;
        $this->seo_title         = $product->seo_title;
        $this->seo_description   = $product->seo_description;
        $this->seo_keywords      = $product->seo_keywords;
        $this->seo_canonical_url = $product->seo_canonical_url;

        $this->slugManuallyEdited     = true;
        $this->canonicalManuallyEdited = (bool) $product->seo_canonical_url;

        $this->selectedCategoryName = $product->category?->name ?? '';

        // تیراژهای محصول
        $this->quantity_tiers = $product->quantity_tiers ?? [];
        $this->showQuantityTiers = !empty($this->quantity_tiers);
        
        // ✅ بارگذاری واحد بسته‌بندی
        $this->packaging_unit_type = $product->packaging_unit_type ?? 'carton';
        $this->quantity_per_packaging = $product->quantity_per_packaging ?? 1;
        
        // ✅ اگر محصول تیراژ دارد، به عنوان "اعمال شده" علامت‌گذاری کن
        $this->hasExistingTiers = !empty($this->quantity_tiers);
        $this->tiersApplied = !empty($this->quantity_tiers); // ✅ برای نمایش تیراژها و مخفی کردن پیشنهاد خودکار

        $this->existingGalleries = $product->galleries
            ->sortBy(function ($g) {
                if (($g->file_type ?? 'image') === 'video') {
                    return -1;
                }

                return $g->sort_order ?? 999999;
            })


            ->values()
            ->map(fn ($g) => [
                'id'         => $g->id,
                'file_path'  => $g->file_path,
                'file_type'  => $g->file_type ?? 'image',
                'is_primary' => (bool) $g->is_primary,
                'mime_type'  => $g->mime_type,
                'duration'   => $g->duration,
                'full_url'   => $g->full_url,
                'sort_order' => $g->sort_order,
                'alt_text'   => $g->alt_text,
            ])
            ->toArray();

        $this->galleryOrder = collect($this->existingGalleries)
            ->where('file_type', 'image')
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->toArray();
        $this->loadCategoryAttributes();
        $this->populateSelectedFromProduct($product);
    }

    // نگاشت مقادیر ذخیره‌شده محصول به ساختار selectedAttributeValues
    protected function populateSelectedFromProduct(Product $product): void
    {
        // نگاشت attribute_id => is_multiple برای تشخیص آرایه/تکی
        $attributesById = collect($this->categoryAttributes)
            ->keyBy('id');

        foreach ($product->attributeValues as $value) {
            $attributeId = $value->attribute_id;
            $attribute   = $attributesById->get($attributeId);

            if (!$attribute) {
                continue; // مقداری متعلق به ویژگی خارج از دسته فعلی
            }

            if ($attribute->is_multiple) {
                if (!is_array($this->selectedAttributeValues[$attributeId] ?? null)) {
                    $this->selectedAttributeValues[$attributeId] = [];
                }
                $this->selectedAttributeValues[$attributeId][] = (string) $value->id;
            } else {
                $this->selectedAttributeValues[$attributeId] = (string) $value->id;
            }
        }
    }

    // تولید خودکار Slug فقط در حالت ایجاد (در ویرایش نامک را دست
    //کاری نمی‌کنیم)

    private function generateUniqueSlug(string $title): string
    {
        $base = mb_substr($this->makePersianSlug($title), 0, 220);
        $slug = $base;
        $i = 2;

        while (
        Product::where('slug', $slug)
            ->when($this->productId, fn($q) => $q->where('id', '!=', $this->productId))
            ->exists()
        ) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }


    public function updatedTitle($value)
    {
        if (!$this->isEdit && !$this->slugManuallyEdited) {
            $this->slug = $this->generateUniqueSlug($value);
        }
    }
    public function updatedSlug($value)
    {
        $this->slugManuallyEdited = true;
    }
    public function resetSlugToAuto(): void
    {
        $this->slugManuallyEdited = false;
        $this->slug = $this->title ? $this->generateUniqueSlug($this->title) : null;
    }

    public function updatedSeoCanonicalUrl($value)
    {
        $this->canonicalManuallyEdited = (bool) $value;
    }

    public function resetCanonicalToAuto(): void
    {
        $this->canonicalManuallyEdited = false;
        $this->seo_canonical_url = 'https://chanta.ir/products/' . $this->slug;

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

        // تغییر دسته → مقادیر انتخاب‌شده قبلی بی‌اعتبار می‌شوند و باید ریست شوند
        $this->attributeValues = [];
        $this->selectedAttributeValues = [];
        $this->loadCategoryAttributes();

        $this->searchCategory = '';
        $this->loadDefaultCategories();
    }

    public function loadCategoryAttributes()
    {
        $this->categoryAttributes = [];

        if ($this->category_id) {
            $category = Category::with('attributes.values')->find($this->category_id);
            $this->categoryAttributes = $category?->attributes ?? [];

            // مقداردهی اولیه فقط برای کلیدهایی که هنوز وجود ندارند
            // (حفظ انتخاب‌های فعلی هنگام افزودن مقدار جدید یا رفرش)
            foreach ($this->categoryAttributes as $attribute) {
                if (!array_key_exists($attribute->id, $this->selectedAttributeValues)) {
                    $this->selectedAttributeValues[$attribute->id] = $attribute->is_multiple
                        ? []      // چک‌باکس → آرایه
                        : null;   // رادیو → مقدار تکی
                }
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

        if (empty($value)) {
            $this->dispatch('notify', [
                'message' => 'لطفاً مقداری وارد کنید',
                'type' => 'error'
            ]);
            return;
        }

        $existing = AttributeValue::where('attribute_id', $attributeId)
            ->where('value', $value)
            ->first();

        if ($existing) {
            $newValueId = $existing->id;
            $isNew = false;
        } else {
            $newValue = AttributeValue::create([
                'attribute_id' => $attributeId,
                'value' => $value
            ]);
            $newValueId = $newValue->id;
            $isNew = true;
        }

        // بروزرسانی لیست مقادیر
        $this->loadCategoryAttributes();
        
        // اضافه کردن مقدار جدید به لیست انتخاب‌شده
        $attribute = $this->categoryAttributes->firstWhere('id', $attributeId);
        if ($attribute) {
            if ($attribute->is_multiple) {
                // چند انتخابی: اضافه کردن به آرایه
                if (!in_array($newValueId, $this->selectedAttributeValues[$attributeId] ?? [])) {
                    $this->selectedAttributeValues[$attributeId][] = (string)$newValueId;
                }
            } else {
                // تک انتخابی: جایگزینی
                $this->selectedAttributeValues[$attributeId] = (string)$newValueId;
            }
        }

        $this->newAttributeValues[$attributeId] = '';
        
        $message = $isNew 
            ? "مقدار «{$value}» با موفقیت اضافه و انتخاب شد"
            : "مقدار «{$value}» از قبل وجود داشت و انتخاب شد";
        
        $this->dispatch('notify', [
            'message' => $message,
            'type' => 'success'
        ]);
    }
    
    /**
     * حذف یک مقدار از لیست انتخاب‌شده
     */
    public function removeAttributeValue($attributeId, $valueId)
    {
        $attribute = collect($this->categoryAttributes)->firstWhere('id', $attributeId);
        
        if (!$attribute) return;
        
        if ($attribute->is_multiple) {
            // چند انتخابی: حذف از آرایه
            $values = $this->selectedAttributeValues[$attributeId] ?? [];
            $this->selectedAttributeValues[$attributeId] = array_values(
                array_filter($values, fn($v) => (string)$v !== (string)$valueId)
            );
        } else {
            // تک انتخابی: null کردن
            $this->selectedAttributeValues[$attributeId] = null;
        }
    }
    
    /**
     * اضافه/حذف یک مقدار به/از لیست انتخاب‌شده (toggle)
     */
    public function toggleAttributeValue($attributeId, $valueId)
    {
        $attribute = collect($this->categoryAttributes)->firstWhere('id', $attributeId);
        
        if (!$attribute) return;
        
        if ($attribute->is_multiple) {
            // چند انتخابی
            $values = $this->selectedAttributeValues[$attributeId] ?? [];
            $valueIdStr = (string)$valueId;
            
            if (in_array($valueIdStr, array_map('strval', $values))) {
                // حذف
                $this->selectedAttributeValues[$attributeId] = array_values(
                    array_filter($values, fn($v) => (string)$v !== $valueIdStr)
                );
            } else {
                // اضافه
                $this->selectedAttributeValues[$attributeId][] = $valueIdStr;
            }
        } else {
            // تک انتخابی: جایگزینی
            $this->selectedAttributeValues[$attributeId] = (string)$valueId;
        }
    }

    // علامت‌گذاری یک رسانه موجود برای حذف (حذف واقعی هنگام ذخیره انجام می‌شود)
    public function markGalleryForDeletion($galleryId)
    {
        $this->galleriesToDelete[] = $galleryId;
        $this->existingGalleries = collect($this->existingGalleries)
            ->reject(fn ($g) => $g['id'] == $galleryId)
            ->values()
            ->toArray();
    }
    private function getUploadedImageDimensions($image): array
    {
        $dimensions = @getimagesize($image->getRealPath());

        return [
            'width' => $dimensions[0] ?? null,
            'height' => $dimensions[1] ?? null,
        ];
    }
    public function moveGalleryImage(int $galleryId, string $direction): void
    {
        $images = collect($this->existingGalleries)
            ->where('file_type', 'image')
            ->values()
            ->toArray();

        $currentIndex = array_search($galleryId, array_column($images, 'id'));
        if ($currentIndex === false) return;

        $targetIndex = $direction === 'up' ? $currentIndex - 1 : $currentIndex + 1;
        if ($targetIndex < 0 || $targetIndex >= count($images)) return;

        [$images[$currentIndex], $images[$targetIndex]] = [$images[$targetIndex], $images[$currentIndex]];

        // اگر تصویری به جایگاه اول رسید، تصویر اصلی بشه
        $images = array_map(function ($g, $i) {
            $g['is_primary'] = ($i === 0);
            return $g;
        }, $images, array_keys($images));

        $videos = collect($this->existingGalleries)->where('file_type', 'video')->values();
        $this->existingGalleries = $videos->concat(collect($images))->values()->toArray();

        $this->galleryOrder = collect($images)->pluck('id')->map(fn($id) => (string)$id)->toArray();
    }

    public function generateSeo(): void
    {
        $siteName  = 'عمده‌فروشی چن تا؟'; // یا config('app.name')
        $separator = ' | ';
        $maxLen    = 60;

        $suffixLen      = mb_strlen($separator . $siteName);
        $titlePart      = mb_substr($this->title ?? '', 0, $maxLen - $suffixLen);
        $this->seo_title = $siteName . $separator . $titlePart;

        $desc = strip_tags($this->short_description ?: $this->full_description ?: '');
        $this->seo_description = mb_substr($desc, 0, 160);
        if (!$this->canonicalManuallyEdited && $this->slug) {
            $this->seo_canonical_url = 'https://chanta.ir/products/' . $this->slug;
        }
        // تولید seo_keywords
        $valueIds = collect($this->selectedAttributeValues)->flatten()->filter()->all();
        $attrTexts = $valueIds
            ? \App\Models\AttributeValue::whereIn('id', $valueIds)->pluck('value')->all()
            : [];

        $sources = array_filter([
            $this->title,
            $this->selectedCategoryName,
            implode(' ', $attrTexts),
        ]);

        $words = collect($sources)
            ->flatMap(fn($s) => preg_split('/[\s،,;\|]+/u', $s, -1, PREG_SPLIT_NO_EMPTY))
            ->filter(fn($w) => mb_strlen($w) > 1)
            ->unique()
            ->values()
            ->all();

        $keywords = '';
        foreach ($words as $word) {
            $next = $keywords ? $keywords . '، ' . $word : $word;
            if (mb_strlen($next) > 255) break;
            $keywords = $next;
        }
        $this->seo_keywords = $keywords;
    }

// 2. متد کمکی جدید — حذف فایل اصلی + تمام variantها
    private function deleteGalleryWithVariants(ProductGallery $gallery): void
    {
        $filename = pathinfo($gallery->file_path, PATHINFO_FILENAME);
        Storage::disk('public')->delete([
            $gallery->file_path,
            "products/gallery/md/{$filename}.webp",
            "products/gallery/sm/{$filename}.webp",
            "products/gallery/pdf/{$filename}.jpg",
        ]);
        $gallery->delete();
    }

    public function setPrimaryGallery(int $galleryId): void
    {
        $images = collect($this->existingGalleries)
            ->where('file_type', 'image')
            ->values()
            ->toArray();

        // پیدا کردن ایندکس تصویر انتخابی
        $targetIndex = array_search($galleryId, array_column($images, 'id'));
        if ($targetIndex === false) return;

        // جابجایی به ابتدا
        $target = $images[$targetIndex];
        array_splice($images, $targetIndex, 1);
        array_unshift($images, $target);

        // فقط اولی is_primary = true
        $images = array_map(function ($g, $i) {
            $g['is_primary'] = ($i === 0);
            return $g;
        }, $images, array_keys($images));

        $videos = collect($this->existingGalleries)->where('file_type', 'video')->values();
        $this->existingGalleries = $videos->concat(collect($images))->values()->toArray();

        $this->galleryOrder = collect($images)->pluck('id')->map(fn($id) => (string)$id)->toArray();
    }

    public function save()
    {
        $rules = [
            'seo_title'         => 'nullable|string|max:60',
            'seo_description'   => 'nullable|string|max:160',
            'seo_keywords'      => 'nullable|string|max:255',
            'seo_canonical_url' => 'nullable|url|max:255',

            'title' => 'required|string|max:255',
            'slug' => [
                'required', 'string', 'max:255',
                Rule::unique('products', 'slug')->ignore($this->productId),
            ],
            'category_id' => 'required|exists:categories,id',
            'supplier_id' => 'required|exists:suppliers,id',
            'sku' => [
                'nullable', 'string', 'max:255',
                Rule::unique('products', 'sku')->ignore($this->productId),
            ],
            'status' => ['required', Rule::enum(ProductStatus::class)],
            'is_active' => 'boolean',
            'short_description' => 'nullable|string',
            'full_description' => 'nullable|string',
            'primary_image' => 'nullable|image|max:2048',
            'gallery_images.*' => 'nullable|image|max:2048',
            'product_video' => 'nullable|file|mimes:mp4,webm,ogg,mov|max:51200',
            'selectedAttributeValues' => 'array',
            
            // ✅ واحد بسته‌بندی
            'packaging_unit_type' => 'required|string|in:carton,jean,jowab,package,bundle,roll,piece',
            'quantity_per_packaging' => 'required|integer|min:1|max:999999',
            
            // ✅ تیراژ (حداقل 3، حداکثر 5 تیراژ الزامی)
            'quantity_tiers' => 'required|array|min:3|max:5',
            'quantity_tiers.*.name' => 'required|string|max:100',
            'quantity_tiers.*.min_quantity' => 'required|integer|min:0',
            'quantity_tiers.*.max_quantity' => 'nullable|integer',
        ];

        $this->validate($rules);

        // ✅ Validation پیشرفته تیراژها
        if (!empty($this->quantity_tiers)) {
            try {
                QuantityTierValidator::validate($this->quantity_tiers);
            } catch (\Illuminate\Validation\ValidationException $e) {
                // بازنشانی خطاها برای نمایش در UI
                $this->dispatch('notify', [
                    'message' => $e->getMessage() ?: 'تیراژها نامعتبر هستند.',
                    'type' => 'error',
                ]);
                throw $e;
            }
        }

        if (empty($this->sku)) {
            $this->sku = 'PRD-' . strtoupper(Str::random(8));
        }

        // flatten و تمیز کردن مقادیر ویژگی
        $validAttributeValues = collect($this->selectedAttributeValues)
            ->flatMap(fn ($v) => is_array($v) ? $v : [$v])
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        // اعتبارسنجی نهایی مقادیر روی دیتابیس
        Validator::make(
            ['attribute_values' => $validAttributeValues],
            ['attribute_values.*' => 'exists:attribute_values,id']
        )->validate();

        $payload = [
            'full_description'   => $this->full_description,
            'seo_title'          => $this->seo_title,
            'seo_description'    => $this->seo_description,
            'seo_keywords'       => $this->seo_keywords,
            'seo_canonical_url'  => $this->seo_canonical_url,

            'title' => $this->title,
            'slug' => $this->slug,
            'category_id' => $this->category_id,
            'supplier_id' => $this->supplier_id,
            'sku' => $this->sku,
            'status' => $this->status,
            'is_active' => $this->is_active,
            'short_description' => $this->short_description,
            'description' => $this->full_description,
            'quantity_tiers' => !empty($this->quantity_tiers) ? $this->quantity_tiers : null,
            
            // ✅ واحد بسته‌بندی
            'packaging_unit_type' => $this->packaging_unit_type,
            'quantity_per_packaging' => $this->quantity_per_packaging,
        ];

        if ($this->isEdit) {
            $product = Product::findOrFail($this->productId);
            $product->update($payload);
        } else {
            $product = Product::create($payload);
        }

        // ساخت داده pivot با ستون value (Denormalization)
        $valueTexts = AttributeValue::whereIn('id', $validAttributeValues)
            ->pluck('value', 'id');

        $syncData = [];
        foreach ($validAttributeValues as $valueId) {
            $syncData[$valueId] = ['value' => $valueTexts[$valueId] ?? null];
        }

        // sync در هر دو حالت درست کار می‌کند: در ویرایش، مقادیر حذف‌شده کنار می‌روند
        $product->attributeValues()->sync($syncData);

        // حذف رسانه‌های علامت‌گذاری‌شده (فقط در ویرایش)
// 3. جایگزینی بلوک حذف (خطوط 522-532)
        if (!empty($this->galleriesToDelete)) {
            $product->galleries()
                ->whereIn('id', $this->galleriesToDelete)
                ->get()
                ->each(fn($g) => $this->deleteGalleryWithVariants($g));
            $this->galleriesToDelete = [];
        }
        if ($this->isEdit && !empty($this->existingGalleries)) {
            $images = collect($this->existingGalleries)->where('file_type', 'image')->values();

            foreach ($images as $index => $item) {
                ProductGallery::where('id', $item['id'])
                    ->where('product_id', $product->id)
                    ->update([
                        'sort_order' => $index + 1,
                        'is_primary'  => $item['is_primary'],
                    ]);
            }
        }

        // تصویر اصلی جدید → جایگزین تصویر اصلی قبلی
// 4. جایگزینی بلوک primary_image (خطوط 549-572)
        if ($this->primary_image) {
            $product->galleries()->where('is_primary', true)->get()
                ->each(fn($g) => $this->deleteGalleryWithVariants($g));

            $meta = app(ProductImageService::class)
                ->process($this->primary_image->getRealPath(), $product->slug, 1, withPdf: true);

            $product->galleries()->create([
                'base_name' => $meta['base_name'],
                'file_name' => $meta['file_name'],
                'file_path' => $meta['file_path'],
                'mime_type' => $meta['mime_type'] ?? 'image/webp',
                'width' => $meta['width'] ?? null,
                'height' => $meta['height'] ?? null,
                'versions' => $meta['versions'] ?? [],
                'created_at' => now(),
                'updated_at' => now(),



                'file_type'  => 'image',
                'is_primary' => true,
                'file_size'  => $meta['file_size'],
                'title'      => $this->title,
                'alt_text'   => $this->title,
                'sort_order' => 1,









            ]);
        }

        // افزودن تصاویر گالری جدید
// 5. جایگزینی بلوک gallery_images (خطوط 575-599)
        if (!empty($this->gallery_images)) {
            $imageService = app(ProductImageService::class);
            $lastSortOrder = $product->galleries()->where('file_type', 'image')->max('sort_order') ?? 0;

            foreach ($this->gallery_images as $galImage) {
                $lastSortOrder++;
                $meta = $imageService->process($galImage->getRealPath(), $product->slug, $lastSortOrder);

                $product->galleries()->create([
                    'base_name' => $meta['base_name'],
                    'file_name' => $meta['file_name'],
                    'file_path' => $meta['file_path'],
                    'mime_type' => $meta['mime_type'] ?? 'image/webp',
                    'width' => $meta['width'] ?? null,
                    'height' => $meta['height'] ?? null,
                    'versions' => $meta['versions'] ?? [],

                    'created_at' => now(),
                    'updated_at' => now(),



                    'file_type'  => 'image',
                    'is_primary' => false,
                    'file_size'  => $meta['file_size'],
                    'title'      => $this->title . ' - تصویر ' . $lastSortOrder,
                    'alt_text'   => $this->title . ' - تصویر ' . $lastSortOrder,
                    'sort_order' => $lastSortOrder,
                ]);
            }
        }

        // ویدئوی محصول → در هر محصول فقط یک ویدئو نگهداری می‌شود
        if ($this->product_video) {
            $oldVideos = $product->galleries()->where('file_type', 'video')->get();

            foreach ($oldVideos as $oldVideo) {
                Storage::disk('public')->delete($oldVideo->file_path);
                // حذف poster قبلی اگر وجود دارد
                if ($oldVideo->poster_path) {
                    Storage::disk('public')->delete($oldVideo->poster_path);
                }
                $oldVideo->delete();
            }

            $path = $this->product_video->store('products/videos', 'public');
            
            // آپلود poster اگر آپلود شده باشد
            $posterPath = null;
            if ($this->video_poster) {
                $posterPath = $this->video_poster->store('products/video-posters', 'public');
            }

            $product->galleries()->create([
                'file_path'  => $path,
                'file_type'  => 'video',
                'poster_path' => $posterPath,  // ✅ اضافه شد
                'is_primary' => false,
                'file_size'  => $this->product_video->getSize(),
                'mime_type'  => $this->product_video->getMimeType(),
                'title'      => 'ویدئو معرفی ' . $this->title,
                'alt_text'   => 'ویدئو محصول ' . $this->title,
                'sort_order' => 0, // ویدئو معمولاً در ابتدای گالری نمایش داده می‌شود
            ]);
        }

        $this->dispatch('notify', [
            'message' => $this->isEdit ? 'محصول با موفقیت بروزرسانی شد.' : 'محصول با موفقیت ایجاد شد.',
            'type' => 'success',
        ]);

        return redirect()->route('admin.products.index');
    }

    // ══════════════════════════════════════════════════════════
    //  مدیریت تیراژها
    // ══════════════════════════════════════════════════════════

    /**
     * اضافه کردن تیراژ جدید
     */
    public function addQuantityTier(): void
    {
        if (count($this->quantity_tiers) >= 5) {
            $this->dispatch('notify', [
                'message' => 'حداکثر 5 تیراژ می‌توانید تعریف کنید.',
                'type' => 'error',
            ]);
            return;
        }

        $this->quantity_tiers[] = [
            'name' => 'تیراژ ' . (count($this->quantity_tiers) + 1),
            'min' => 1,
            'max' => null,
        ];
    }

    /**
     * حذف تیراژ
     */
    public function removeQuantityTier(int $index): void
    {
        if (!isset($this->quantity_tiers[$index])) {
            return;
        }

        // ✅ بررسی حداقل 3 تیراژ
        if (count($this->quantity_tiers) <= 3) {
            $this->dispatch('notify', [
                'message' => 'حداقل 3 تیراژ برای محصول الزامی است و نمی‌توان کمتر از این تعداد داشت.',
                'type' => 'error',
            ]);
            return;
        }

        // بررسی امنیت: اگر محصول در حال ویرایش است و تیراژ قیمت‌گذاری شده، نباید حذف شود
        if ($this->isEdit && $this->productId) {
            $product = Product::find($this->productId);
            
            if ($product) {
                // بررسی وجود قیمت برای این تیراژ (فقط قیمت‌های سیستم جدید با price_type_id)
                $hasPrices = $product->prices()
                    ->where('tier_index', $index)
                    ->whereNotNull('price_type_id') // فقط قیمت‌های سیستم جدید
                    ->exists();
                
                if ($hasPrices) {
                    $tierName = $this->quantity_tiers[$index]['name'] ?? "تیراژ " . ($index + 1);
                    $this->dispatch('notify', [
                        'message' => "تیراژ «{$tierName}» دارای قیمت‌گذاری است و نمی‌تواند حذف شود. ابتدا قیمت‌های مربوطه را حذف کنید.",
                        'type' => 'error',
                    ]);
                    return;
                }
            }
        }

        unset($this->quantity_tiers[$index]);
        $this->quantity_tiers = array_values($this->quantity_tiers); // Re-index
        
        $this->dispatch('notify', [
            'message' => 'تیراژ با موفقیت حذف شد.',
            'type' => 'success',
        ]);
    }

    /**
     * ✅ اعمال تیراژبندی خودکار
     */
    public function applyAutoTiers(): void
    {
        // بررسی اینکه واحد بسته‌بندی تعیین شده باشد
        if ($this->quantity_per_packaging <= 0) {
            $this->dispatch('notify', [
                'message' => 'ابتدا تعداد در هر واحد بسته‌بندی را مشخص کنید.',
                'type' => 'error',
            ]);
            return;
        }

        $this->quantity_tiers = $this->generateAutoTiers();
        $this->showQuantityTiers = true;
        $this->tiersApplied = true; // ✅ علامت‌گذاری که تیراژها اعمال شدند
        
        $this->dispatch('notify', [
            'message' => 'تیراژبندی خودکار اعمال شد. اکنون می‌توانید ویرایش کنید یا تیراژ جدید اضافه کنید.',
            'type' => 'success',
        ]);
    }
    
    /**
     * ✅ پاک کردن تیراژها (بازگشت به حالت اولیه)
     */
    public function clearAutoTiers(): void
    {
        $this->quantity_tiers = [];
        $this->showQuantityTiers = false;
        $this->tiersApplied = false;
        
        $this->dispatch('notify', [
            'message' => 'تیراژها پاک شدند. می‌توانید دوباره تیراژبندی خودکار را اعمال کنید.',
            'type' => 'info',
        ]);
    }

    public function render()
    {
        return view('livewire.admin.products.create');
    }
    
    /**
     * ✅ تیراژبندی خودکار بر اساس تعداد در واحد بسته‌بندی
     * 
     * @return array
     */
    protected function generateAutoTiers(): array
    {
        $qtyPerPack = $this->quantity_per_packaging;
        
        if ($qtyPerPack <= 0) {
            return [];
        }
        
        $unitLabel = $this->getPackagingUnitLabel();
        
        // ✅ استثنا: اگر بسته‌بندی 1 عددی باشد
        if ($qtyPerPack === 1) {
            return [
                0 => [
                    'name' => 'خرده‌فروشی',
                    'min_quantity' => 1,
                    'max_quantity' => 1,
                    'description' => "یک عدد ({$unitLabel} 1 عددی)",
                ],
                1 => [
                    'name' => 'عمده‌فروشی کوچک',
                    'min_quantity' => 2,
                    'max_quantity' => 5,
                    'description' => "2 تا 5 {$unitLabel}",
                ],
                2 => [
                    'name' => 'عمده‌فروشی بزرگ',
                    'min_quantity' => 6,
                    'max_quantity' => null,
                    'description' => "بیش از 5 {$unitLabel} (6 به بالا)",
                ],
            ];
        }
        
        // ✅ حالت عادی (quantity_per_packaging >= 2)
        return [
            0 => [
                'name' => 'خرده‌فروشی',
                'min_quantity' => 1,
                'max_quantity' => $qtyPerPack - 1,
                'description' => "کمتر از یک {$unitLabel} (1 تا " . ($qtyPerPack - 1) . " عدد)",
            ],
            1 => [
                'name' => 'عمده‌فروشی کوچک',
                'min_quantity' => $qtyPerPack,
                'max_quantity' => $qtyPerPack * 5,
                'description' => "1 تا 5 {$unitLabel} ({$qtyPerPack} تا " . ($qtyPerPack * 5) . " عدد)",
            ],
            2 => [
                'name' => 'عمده‌فروشی بزرگ',
                'min_quantity' => ($qtyPerPack * 5) + 1,
                'max_quantity' => null,
                'description' => "بیش از 5 {$unitLabel} (" . (($qtyPerPack * 5) + 1) . " عدد به بالا)",
            ],
        ];
    }
    
    /**
     * ✅ دریافت برچسب فارسی واحد بسته‌بندی
     * 
     * @return string
     */
    protected function getPackagingUnitLabel(): string
    {
        $labels = [
            'carton'  => 'کارتن',
            'jean'    => 'جین',
            'jowab'   => 'جورب',
            'package' => 'بسته',
            'bundle'  => 'شیرینگ',
            'roll'    => 'طاقه',
            'piece'   => 'عدد',
        ];

        return $labels[$this->packaging_unit_type] ?? $this->packaging_unit_type;
    }
}
