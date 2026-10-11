<?php
// app/Livewire/Admin/Categories/Create.php

namespace App\Livewire\Admin\Categories;

use App\Models\Category;
use App\Models\Attribute;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Validation\ValidationException;








#[Layout('components.layouts.admin')]
class Create extends Component
{
    use WithFileUploads;

    public ?Category $category = null;
    public bool $isEditMode = false;

    public $name;
    public $slug;
    public $parent_id;
    public $description;
    public $icon;
    public $image;
    public $sort_order = 0;
    public $is_active = true;
    public $seo_title;
    public $seo_description;
    public $seo_keywords;
    public $selectedAttributes = [];

    /** @var array<int, array{display_order: int, is_required: bool}> */
    public array $attributeSettings = [];

    public bool $isLeafNode = true;
    public bool $autoGenerateSeo = false;
    public string $seoTemplate = 'commercial';
    public ?string $existingIcon = null;
    public ?string $existingImagePath = null;

    public const ICONS = [
        'squares-2x2',
        'cube',
        'cube-transparent',
        'tag',
        'shopping-bag',
        'shopping-cart',
        'gift',
        'archive-box',
        'building-storefront',
        'building-office',
        'truck',
        'wrench',
        'wrench-screwdriver',
        'cog-6-tooth',
        'cpu-chip',
        'device-phone-mobile',
        'computer-desktop',
        'tv',
        'camera',
        'printer',
        'bolt',
        'light-bulb',
        'fire',
        'sparkles',
        'beaker',
        'paint-brush',
        'scissors',
        'home',
        'home-modern',
        'key',
        'lock-closed',
        'heart',
        'star',
        'sun',
        'moon',
        'cloud',
        'globe-alt',
        'map-pin',
        'book-open',
        'academic-cap',
        'briefcase',
        'banknotes',
        'credit-card',
        'chart-bar',
        'clipboard-document-list',
        'folder',
        'document-text',
        'musical-note',
        'film',
        'puzzle-piece',
        'rocket-launch',
        'trophy',
        'wallet',
        'phone',
        'envelope',
        'bell',
        'calendar',
        'clock',
        'user-group',
        'swatch',
        'rectangle-stack',
        'circle-stack',
        'wifi',
        'funnel',
        'cake',
        'photo',
        'newspaper',
        'megaphone',
        'shield-check',
        'ticket',
        'eye',
        'link',
        'server',
        'identification',
        'arrow-path',
        'bookmark',
        'calendar-days',
        'check-badge',
        'flag',
        'map',
        'presentation-chart-bar',
        'video-camera',
    ];

    public function mount(?Category $category = null): void
    {
        $this->isLeafNode = true;

        if ($category && $category->exists) {
            $this->category = $category;
            $this->isEditMode = true;

            $this->name = $category->name;
            $this->slug = $category->slug;
            $this->parent_id = $category->parent_id;
            $this->description = $category->description;
            $this->icon = $category->icon;
            $this->existingIcon = $category->icon;
            $this->existingImagePath = $category->image_path;
            $this->sort_order = $category->sort_order;
            $this->is_active = (bool) $category->is_active;
            $this->seo_title = $category->seo_title;
            $this->seo_description = $category->seo_description;
            $this->seo_keywords = $category->seo_keywords;
            $categoryAttributes = $category->attributes()->get();
            $this->selectedAttributes = $categoryAttributes->pluck('id')->map(static fn ($id): int => (int) $id)->all();
            $this->attributeSettings = $categoryAttributes
                ->mapWithKeys(static fn (Attribute $attribute): array => [
                    $attribute->id => [
                        'display_order' => (int) $attribute->pivot->display_order,
                        'is_required' => (bool) $attribute->pivot->is_required,
                    ],
                ])
                ->all();

            $this->isLeafNode = $category->children()->doesntExist();
        }
    }

    public function updatedName($value): void
    {
        if (! $this->isEditMode || blank($this->slug)) {
            $this->slug = Str::slug($value);
        }
        if ($this->autoGenerateSeo) {
            $this->generateSeo();
        }

    }

    public function updatedDescription(): void
    {
        if ($this->autoGenerateSeo) {
            $this->generateSeo();
        }
    }


    public function updatedParentId($value): void
    {
        // در صورتی که دسته دارای والد شود یا فاقد آن، وضعیت برگ بودن را بر اساس منطق دیتابیس مجدد ارزیابی می‌کنیم.
        if (blank($value)) {
            $this->isLeafNode = true;
            return;
        }

        $this->isLeafNode = true;
    }

    protected function rules(): array
    {
        $categoryId = $this->category?->id;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('categories', 'name')->ignore($categoryId),
            ],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('categories', 'slug')->ignore($categoryId),
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
            ],
            'parent_id' => [
                'nullable',
                'integer',
                'exists:categories,id',
                function ($attribute, $value, $fail) {
                    if (! $value || ! $this->category) {
                        return;
                    }

                    if ((int) $value === (int) $this->category->id) {
                        $fail('دسته‌بندی نمی‌تواند والد خودش باشد.');
                    }

                    $descendantIds = $this->category->allChildren()->pluck('id')->toArray();
                    if (in_array((int) $value, array_map('intval', $descendantIds), true)) {
                        $fail('نمی‌توانید یکی از زیرمجموعه‌های همین دسته‌بندی را به عنوان والد انتخاب کنید.');
                    }
                },
            ],
            'description' => ['nullable', 'string'],
            'icon' => ['nullable', 'string', 'max:255'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:1000'],
            'seo_keywords' => ['nullable', 'string', 'max:255'],
            'selectedAttributes' => ['nullable', 'array'],
            'selectedAttributes.*' => ['integer', 'exists:attributes,id'],
            'attributeSettings' => ['nullable', 'array'],
            'attributeSettings.*.display_order' => ['required', 'integer', 'min:0', 'max:99999'],
            'attributeSettings.*.is_required' => ['required', 'boolean'],
            'autoGenerateSeo' => ['boolean'],
            'seoTemplate' => ['required', 'string', Rule::in(['standard', 'commercial', 'technical'])],

        ];
    }

    public function messages(): array
    {
        return [
            'slug.regex' => 'نامک فقط می‌تواند شامل حروف کوچک انگلیسی، عدد و خط تیره باشد.',
            'image.max' => 'حجم تصویر نباید بیشتر از ۲ مگابایت باشد.',
        ];
    }

    public function selectIcon(string $name): void
    {
        // سد امنیتی برای جلوگیری از تزریق رشته‌های مخرب در آیکون
        if (in_array($name, self::ICONS, true)) {
            $this->icon = 'heroicon-o-' . $name;
        }
    }

    public function clearIcon(): void
    {
        $this->icon = null;
    }

    public function removeImage(): void
    {
        // حذف فیزیکی فایل تصویر در صورت اقدام در فرم جهت بهینگی مصرف هاست اشتراکی
        if ($this->existingImagePath) {
            Storage::disk('public')->delete($this->existingImagePath);
            $this->existingImagePath = null;
        }
        $this->image = null;
    }

    public function addAttribute($attributeId): void
    {
        $attributeId = (int) $attributeId;

        if (! in_array($attributeId, $this->selectedAttributes, true)) {
            $this->selectedAttributes[] = $attributeId;
            $lastDisplayOrder = collect($this->attributeSettings)
                ->pluck('display_order')
                ->map(static fn (mixed $order): int => (int) $order)
                ->max() ?? 0;
            $this->attributeSettings[$attributeId] = [
                'display_order' => $lastDisplayOrder + 1,
                'is_required' => false,
            ];
        }

        $this->resetErrorBag('attribute_remove');
    }

// ۲. اصلاح متد حذف ویژگی از آرایه کلاینت
    public function removeAttribute($attributeId): void
    {
        $attributeId = (int) $attributeId;
        // بررسی تعداد محصولات متصل در این دسته‌بندی
        $countInThisCategory = $this->attributeProductCounts[$attributeId] ?? 0;


        if ($countInThisCategory > 0) {
            $this->addError('attribute_error', 'این ویژگی به دلیل استفاده در محصولات این دسته‌بندی، قابل حذف نیست.');
            return;
        }

        $this->selectedAttributes = array_values(array_filter(
            $this->selectedAttributes,
            fn ($id) => (int) $id !== $attributeId
        ));
        unset($this->attributeSettings[$attributeId]);

        $this->resetErrorBag('attribute_error');
    }

// ۳. متد save بازنگری شده و کاملاً ایمن
    public function save()
    {
        $validated = $this->validate();

        try {
            DB::beginTransaction();

            $categoryData = [
                'name' => $validated['name'],
                'slug' => $validated['slug'] ?: Str::slug($validated['name']),
                'parent_id' => $validated['parent_id'] ?? null,
                'description' => $validated['description'] ?? null,
                'icon' => $validated['icon'] ?? null,
                'sort_order' => $validated['sort_order'],
                'is_active' => (bool) ($validated['is_active'] ?? true),
                'seo_title' => $validated['seo_title'] ?? null,
                'seo_description' => $validated['seo_description'] ?? null,
                'seo_keywords' => $validated['seo_keywords'] ?? null,
            ];

            if ($this->image) {
                if ($this->isEditMode && $this->category && $this->category->image_path) {
                    Storage::disk('public')->delete($this->category->image_path);
                }
                $categoryData['image_path'] = $this->image->store('categories', 'public');
            } else {
                if ($this->isEditMode && !$this->existingImagePath && $this->category && $this->category->image_path) {
                    Storage::disk('public')->delete($this->category->image_path);
                    $categoryData['image_path'] = null;
                }
            }

            if ($this->isEditMode) {
                $this->category->update($categoryData);
                $category = $this->category->fresh();
            } else {
                $category = Category::create($categoryData);
            }

            // بررسی و همگام‌سازی ویژگی‌ها
            if ($category->children()->doesntExist()) {
                $selectedAttributeIds = collect($this->selectedAttributes)
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values();

                $currentAttributeIds = $category->attributes()
                    ->pluck('attributes.id')
                    ->map(fn ($id) => (int) $id);

                $removedAttributeIds = $currentAttributeIds
                    ->diff($selectedAttributeIds)
                    ->values();

                if ($removedAttributeIds->isNotEmpty()) {
                    // بررسی نهایی سمت سرور برای ویژگی‌های حذف شده بر اساس محصولات همین دسته
                    $categoryProductIds = DB::table('products')
                        ->where('category_id', $category->id)
                        ->whereNull('deleted_at')
                        ->pluck('id')
                        ->toArray();

                    if (!empty($categoryProductIds)) {
                        $lockedAttributeCounts = DB::table('attribute_product')
                            ->join('attribute_values', 'attribute_product.attribute_value_id', '=', 'attribute_values.id')
                            ->whereIn('attribute_product.product_id', $categoryProductIds)
                            ->whereIn('attribute_values.attribute_id', $removedAttributeIds)
                            ->selectRaw('attribute_values.attribute_id, COUNT(DISTINCT attribute_product.product_id) as product_count')
                            ->groupBy('attribute_values.attribute_id')
                            ->pluck('product_count', 'attribute_values.attribute_id');

                        if ($lockedAttributeCounts->isNotEmpty()) {
                            $lockedAttributeNames = Attribute::whereIn('id', $lockedAttributeCounts->keys())
                                ->orderBy('name')
                                ->pluck('name')
                                ->implode('، ');

                            throw ValidationException::withMessages([
                                'selectedAttributes' => "حذف ویژگی‌های زیر به دلیل اختصاص به محصولات این دسته امکان‌پذیر نیست: {$lockedAttributeNames}",
                            ]);
                        }
                    }
                }

                $syncData = [];

                foreach ($selectedAttributeIds as $attributeId) {
                    $settings = $validated['attributeSettings'][$attributeId] ?? $this->attributeSettings[$attributeId] ?? [];
                    $syncData[$attributeId] = [
                        'display_order' => (int) ($settings['display_order'] ?? 0),
                        'is_required' => (bool) ($settings['is_required'] ?? false),
                    ];
                }

                $category->attributes()->sync($syncData);
            } else {
                $category->attributes()->sync([]);
            }

            DB::commit();

            session()->flash(
                'success',
                $this->isEditMode ? 'دسته‌بندی با موفقیت بروزرسانی شد.' : 'دسته‌بندی با موفقیت ایجاد شد.'
            );

            return $this->redirectRoute('admin.categories.index', navigate: true);
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);
            $this->addError('attribute_error', 'خطا در ذخیره اطلاعات رخ داد. لطفاً دوباره تلاش کنید.');
        }
    }
    // در متد render یا یک متد جداگانه برای دریافت تعداد محصولاتِ یک ویژگی در این دسته خاص
    public function getAttributeProductCountsProperty(): array
    {
        if (!$this->category?->id) {
            return [];
        }

        // کوئری بهینه برای دریافت تعداد محصولاتِ متصل به هر ویژگی در دسته فعلی
        return \DB::table('attribute_product')
            ->join('attribute_values', 'attribute_product.attribute_value_id', '=', 'attribute_values.id')
            ->join('products', 'attribute_product.product_id', '=', 'products.id')
            ->where('products.category_id', $this->category->id)
            ->whereNull('products.deleted_at')
            ->groupBy('attribute_values.attribute_id')
            ->selectRaw('attribute_values.attribute_id, COUNT(DISTINCT attribute_product.product_id) as product_count')
            ->pluck('product_count', 'attribute_values.attribute_id')
            ->toArray();
    }

    public function updatedAutoGenerateSeo($value): void
    {
        if ($value) {
            $this->generateSeo();
        }
    }

    public function updatedSeoTemplate(): void
    {
        if ($this->autoGenerateSeo) {
            $this->generateSeo();
        }
    }

    public function generateSeo(): void
    {
        $name = trim((string) $this->name);

        if ($name === '') {
            $this->addError('seo_title', 'برای تولید خودکار سئو، ابتدا نام دسته‌بندی را وارد کنید.');
            return;
        }

        $parentName = null;

        if ($this->parent_id) {
            $parentName = Category::query()
                ->whereKey($this->parent_id)
                ->value('name');
        }

        $context = $parentName ? "{$name} در {$parentName}" : $name;

        $this->seo_title = match ($this->seoTemplate) {
            'standard' => "خرید و بررسی {$context}",
            'technical' => "مشخصات، ویژگی‌ها و راهنمای انتخاب {$context}",
            default => "خرید {$context} با بهترین کیفیت و قیمت",
        };

        $baseDescription = filled($this->description)
            ? strip_tags((string) $this->description)
            : "در این بخش می‌توانید محصولات مرتبط با {$context} را بررسی، مقایسه و انتخاب کنید.";

        $this->seo_description = Str::limit($baseDescription, 155, '');

        $keywords = collect([
            $name,
            $parentName,
            "خرید {$name}",
            "قیمت {$name}",
            "بهترین {$name}",
        ])
            ->filter()
            ->unique()
            ->implode(', ');

        $this->seo_keywords = Str::limit($keywords, 255, '');
    }

    public function render()
    {
        $parentCategories = Category::query()
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->get();

        if ($this->isEditMode && $this->category) {
            $parentCategories = Category::query()
                ->whereNull('parent_id')
                ->where('id', '!=', $this->category->id)
                ->orderBy('sort_order')
                ->get();
        }

        $allAttributes = Attribute::orderBy('name')->get();

        $attributeProductCounts = DB::table('attribute_product')
            ->join('attribute_values', 'attribute_product.attribute_value_id', '=', 'attribute_values.id')
            ->join('products', 'attribute_product.product_id', '=', 'products.id')
            ->whereNull('products.deleted_at')
            ->select(
                'attribute_values.attribute_id',
                DB::raw('COUNT(DISTINCT attribute_product.product_id) as product_count')
            )
            ->groupBy('attribute_values.attribute_id')
            ->pluck('product_count', 'attribute_values.attribute_id');


        $selectedAttrModels = Attribute::whereIn('id', $this->selectedAttributes)->get();
        $unselectedAttrModels = Attribute::whereNotIn('id', $this->selectedAttributes)->get();

        return view('livewire.admin.categories.create', [
            'selectedAttrModels' => $selectedAttrModels,
            'unselectedAttrModels' => $unselectedAttrModels,
            'parentCategories' => $parentCategories,
            'allAttributes' => $allAttributes,
            'attributeProductCounts' => $this->attributeProductCounts,
            // شمارش عمومی برای نمایش در بخش افزودنی
            'globalAttributeProductCounts' => $this->globalAttributeProductCounts,
            'icons' => self::ICONS,
        ]);
    }

    public function getGlobalAttributeProductCountsProperty()
    {
        return DB::table('attribute_product')
            ->join('attribute_values', 'attribute_product.attribute_value_id', '=', 'attribute_values.id')
            ->join('products', 'attribute_product.product_id', '=', 'products.id')
            ->whereNull('products.deleted_at')
            ->select('attribute_values.attribute_id', DB::raw('count(DISTINCT products.id) as total'))
            ->groupBy('attribute_values.attribute_id')
            ->pluck('total', 'attribute_id')
            ->toArray();
    }



    // ۱. تعریف پروپرتی‌ها یا متدهای محاسبه شمارشگرها
    public function getGlobalAttributeCountsProperty(): array
    {
        // تعداد استفاده از هر ویژگی در کل محصولات سایت
        return DB::table('attribute_product')
            ->join('attribute_values', 'attribute_product.attribute_value_id', '=', 'attribute_values.id')
            ->join('products', 'attribute_product.product_id', '=', 'products.id')
            ->whereNull('products.deleted_at')
            ->groupBy('attribute_values.attribute_id')
            ->pluck(DB::raw('COUNT(DISTINCT attribute_product.product_id)'), 'attribute_values.attribute_id')
            ->toArray();
    }

    public function getCategoryAttributeCountsProperty(): array
    {
        if (!$this->category?->id) {
            return [];
        }

        // ۱. دریافت شناسه محصولات متعلق به این دسته‌بندی
        $categoryProductIds = DB::table('products')
            ->where('category_id', $this->category->id)
            ->whereNull('deleted_at')
            ->pluck('id')
            ->toArray();

        if (empty($categoryProductIds)) {
            return [];
        }
        // ۲. محاسبه تعداد مقادیر تخصیص داده شده با فیلتر محصولات همین دسته
        return DB::table('attribute_product')
            ->join('attribute_values', 'attribute_product.attribute_value_id', '=', 'attribute_values.id')
            ->whereIn('attribute_product.product_id', $categoryProductIds)
            ->groupBy('attribute_values.attribute_id')
            ->pluck(DB::raw('COUNT(DISTINCT attribute_product.product_id)'), 'attribute_values.attribute_id')
            ->toArray();
    }
}
