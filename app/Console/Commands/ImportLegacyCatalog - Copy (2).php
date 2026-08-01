<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Attribute;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ImportLegacyCatalog3 extends Command
{
    protected $signature = 'import:legacy-catalog';
    protected $description = 'Import categories, attributes, and products from legacy database to the new structure';

    public function handle()
    {
        $this->info('Starting legacy data import...');

        try {
            DB::beginTransaction();

            // 1. Import Categories
            $this->info('Importing Categories...');
            $legacyCategories = DB::connection('legacy')->table('categories')->orderBy('id')->get();
            $categoryMap = [];

            if ($legacyCategories->count() > 0) {
                $bar = $this->output->createProgressBar(count($legacyCategories));
                foreach ($legacyCategories as $legacyCat) {
                    $newCat = Category::create([
                        'name' => $legacyCat->name,
                        'slug' => Str::slug($legacyCat->name) . '-' . uniqid(),
                        'parent_id' => $legacyCat->category_id ? ($categoryMap[$legacyCat->category_id] ?? null) : null,
                        'is_active' => true,
                    ]);
                    $categoryMap[$legacyCat->id] = $newCat->id;
                    $bar->advance();
                }
                $bar->finish();
                $this->newLine();
            }

            // 2. Import Attributes
            $this->info('Importing Attributes...');
            $attributeMap = [];
            if (DB::connection('legacy')->getSchemaBuilder()->hasTable('properties')) {
                $legacyProperties = DB::connection('legacy')->table('properties')->get();
                foreach ($legacyProperties as $legacyProp) {
                    $newAttr = Attribute::updateOrCreate(
                        ['name' => $legacyProp->name],
                        [
                            'type' => 'text',
                            'is_filterable' => true
                        ]
                    );
                    $attributeMap[$legacyProp->id] = $newAttr->id;
                }
            }

            // 3. Setup Default Supplier
            $this->info('Setting up default supplier...');
            $defaultSupplier = Supplier::first();

            if (!$defaultSupplier) {
                $defaultSupplier = Supplier::create([
                    'user_id' => 1,
                    'store_name' => 'تامین‌کننده سیستم (ایمپورت)',
                    'manager_name' => 'مدیر سیستم',
                    'phone' => '02100000000',
                    'status' => 'approved',
                ]);
            }

            // 4. Import Products
            $this->info('Importing Products...');
            $legacyProducts = DB::connection('legacy')->table('products')->get();

            if ($legacyProducts->count() > 0) {
                $bar = $this->output->createProgressBar(count($legacyProducts));

                foreach ($legacyProducts as $legacyProduct) {
                    $newCategoryId = $categoryMap[$legacyProduct->category_id] ?? null;
                    $productSlug = Str::slug($legacyProduct->name) . '-' . uniqid();

                    $specifications = [];
                    $syncAttributesData = [];

                    // پردازش ویژگی‌ها
                    if (!empty($legacyProduct->properties)) {
                        $props = $legacyProduct->properties;
                        $decoded = json_decode($props, true);
                        if (json_last_error() !== JSON_ERROR_NONE) {
                            $decoded = json_decode(stripslashes($props), true);
                        }

                        if (is_array($decoded)) {
                            foreach ($decoded as $propId => $propValue) {
                                $attrId = null;
                                $attrName = '';

                                if (isset($attributeMap[$propId])) {
                                    $attrId = $attributeMap[$propId];
                                    $attrName = Attribute::find($attrId)->name ?? $propId;
                                } else {
                                    $attrName = 'ویژگی_' . $propId;
                                    $newAttr = Attribute::firstOrCreate(
                                        ['name' => $attrName],
                                        ['type' => 'text', 'is_filterable' => true]
                                    );
                                    $attrId = $newAttr->id;
                                    $attributeMap[$propId] = $attrId;
                                }

                                if ($newCategoryId) {
                                    DB::table('attribute_category')->updateOrInsert(
                                        [
                                            'attribute_id' => $attrId,
                                            'category_id'  => $newCategoryId
                                        ],
                                        []
                                    );
                                }

                                $fullValue = (string)$propValue;
                                $specifications[$attrName] = $fullValue;

                                $truncatedValue = Str::limit($fullValue, 250, '');

                                $attrValueRecord = DB::table('attribute_values')
                                    ->where('attribute_id', $attrId)
                                    ->where('value', $truncatedValue)
                                    ->first();

                                if ($attrValueRecord) {
                                    $attrValueId = $attrValueRecord->id;
                                } else {
                                    $attrValueId = DB::table('attribute_values')->insertGetId([
                                        'attribute_id' => $attrId,
                                        'value'        => $truncatedValue,
                                        'created_at'   => Carbon::now(),
                                        'updated_at'   => Carbon::now(),
                                    ]);
                                }

                                $syncAttributesData[] = [
                                    'attribute_value_id' => $attrValueId,
                                    'value'              => $fullValue
                                ];
                            }
                        }
                    }

                    $isActive = (bool)$legacyProduct->active;

                    $newProduct = Product::create([
                        'supplier_id'    => $defaultSupplier->id,
                        'category_id'    => $newCategoryId,
                        'title'          => $legacyProduct->name,
                        'slug'           => $productSlug,
                        'sku'            => 'LEGACY-' . $legacyProduct->id . '-' . rand(1000, 9999),
                        'specifications' => empty($specifications) ? null : $specifications,
                        'is_active'      => $isActive,
                        'status'         => $isActive ? 'published' : 'active',
                        'view_count'     => 0,
                    ]);

                    // درج مقادیر ویژگی‌ها در جدول واسط
                    foreach ($syncAttributesData as $attrData) {
                        DB::table('attribute_product')->insert([
                            'product_id'         => $newProduct->id,
                            'attribute_value_id' => $attrData['attribute_value_id'],
                            'value'              => $attrData['value'],
                            'created_at'         => Carbon::now(),
                            'updated_at'         => Carbon::now(),
                        ]);
                    }

                    // پردازش تصاویر (نسخه بهبودیافته و انعطاف‌پذیر)
                    if (!empty($legacyProduct->images)) {
                        $rawImages = trim($legacyProduct->images);
                        $imagesList = [];

                        // تلاش برای پارس به عنوان JSON
                        $decoded = json_decode($rawImages, true);
                        if (json_last_error() !== JSON_ERROR_NONE) {
                            $decoded = json_decode(stripslashes($rawImages), true);
                        }

                        if (is_array($decoded)) {
                            $imagesList = $decoded;
                        } else {
                            // در صورتی که JSON نباشد (مثلا مقادیر جدا شده با کاما باشند)
                            $imagesList = explode(',', $rawImages);
                        }

                        // حذف آیتم‌های خالی
                        $imagesList = array_values(array_filter($imagesList));

                        foreach ($imagesList as $index => $imgItem) {
                            // اگر جیسون به صورت آرایه‌ای از آبجکت‌ها باشد: [{"url":"..."}]
                            $imagePath = is_array($imgItem) ? ($imgItem['url'] ?? $imgItem['path'] ?? $imgItem['file'] ?? '') : (string) $imgItem;

                            $imagePath = trim($imagePath);
                            if (empty($imagePath)) continue;

                            // استخراج نام اصلی فایل
                            $originalFilename = basename($imagePath);
                            $originalFilename = explode('?', $originalFilename)[0]; // حذف پارامترها

                            if (empty($originalFilename)) continue;

                            $newPath = 'products/gallery/' . $originalFilename;

                            // استفاده مستقیم از DB::table برای اطمینان از ذخیره‌سازی و جلوگیری از مشکلات Mass Assignment
                            DB::table('product_galleries')->insert([
                                'product_id' => $newProduct->id,
                                'file_path'  => $newPath,
                                'file_type'  => 'image',
                                'is_primary' => ($index === 0),
                                'sort_order' => $index,
                                'created_at' => Carbon::now(),
                                'updated_at' => Carbon::now(),
                            ]);
                        }
                    }

                    $bar->advance();
                }
                $bar->finish();
                $this->newLine();
            }

            DB::commit();
            $this->info('Legacy catalog imported successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('An error occurred during import: ' . $e->getMessage());
        }
    }
}
