<?php

namespace App\Console\Commands;

use App\Models\ProductSlugRedirect;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Attribute;
use App\Traits\GeneratesPersianSlug;
use Carbon\Carbon;

class ImportLegacyCatalog extends Command
{
    use GeneratesPersianSlug;
    protected $signature = 'import:legacy-catalog {--sync-images : همگام‌سازی گالری تصاویر برای محصولات موجود}';
    protected $description = 'Import/sync categories, attributes, and products from legacy database';

    public function handle()
    {
        $this->info('Starting legacy data import/sync...');
        try {
            // 1. Sync Categories
            $this->info('Syncing Categories...');
            $legacyCategories = DB::connection('legacy')->table('categories')->orderBy('id')->get();
            $categoryMap = [];

            foreach ($legacyCategories as $legacyCat) {
                $existing = DB::table('categories')->where('legacy_id', $legacyCat->id)->first();

                if (!$existing) {
                    $existing = DB::table('categories')
                        ->whereNull('legacy_id')
                        ->where('name', $legacyCat->name)
                        ->first();

                    if ($existing) {
                        DB::table('categories')->where('id', $existing->id)->update([
                            'legacy_id'  => $legacyCat->id,
                            'updated_at' => Carbon::now(),
                        ]);
                    }
                }

                if ($existing) {
                    if ($existing->name !== $legacyCat->name) {
                        DB::table('categories')->where('id', $existing->id)->update([
                            'name'       => $legacyCat->name,
                            'updated_at' => Carbon::now(),
                        ]);
                    }
                    $categoryMap[$legacyCat->id] = $existing->id;
                } else {
                    $parentId = $legacyCat->category_id ? ($categoryMap[$legacyCat->category_id] ?? null) : null;
                    $newCat = Category::create([
                        'name'      => $legacyCat->name,
                        'slug'      => $this->makePersianSlug($legacyCat->name) . '-' . uniqid(),
                        'parent_id' => $parentId,
                        'is_active' => true,
                        'legacy_id' => $legacyCat->id,
                    ]);
                    $categoryMap[$legacyCat->id] = $newCat->id;
                }
            }

            // 2. Sync Attributes
            $this->info('Syncing Attributes...');
            $attributeMap = [];
            if (DB::connection('legacy')->getSchemaBuilder()->hasTable('properties')) {
                $legacyProperties = DB::connection('legacy')->table('properties')->get();
                foreach ($legacyProperties as $legacyProp) {
                    $newAttr = Attribute::updateOrCreate(
                        ['name' => $legacyProp->name],
                        ['type' => 'text']
                    );
                    $attributeMap[$legacyProp->id] = $newAttr->id;
                }
            }

            // 3. Default Supplier
            $this->info('Setting up default supplier...');
            $defaultSupplier = Supplier::first() ?? Supplier::create([
                'user_id'      => 1,
                'store_name'   => 'تامین‌کننده سیستم (ایمپورت)',
                'manager_name' => 'مدیر سیستم',
                'phone'        => '02100000000',
                'status'       => 'approved',
            ]);

            // 4. Sync Products
            $this->info('Syncing Products...');
            $legacyProducts = DB::connection('legacy')->table('products')->get();
            $bar = $this->output->createProgressBar(count($legacyProducts));

            $chunkSize = 10;
            $totalProducts = count($legacyProducts);

            for ($i = 0; $i < $totalProducts; $i += $chunkSize) {
                // شروع تراکنش دیتابیس برای هر ۱۰ کالا
                DB::beginTransaction();
                try {
                    $chunk = $legacyProducts->slice($i, $chunkSize);

                    foreach ($chunk as $legacyProduct) {
                        $newCategoryId   = $categoryMap[$legacyProduct->category_id] ?? null;
                        $specifications  = [];
                        $syncAttributesData = [];

                        // پردازش ویژگی‌ها
                        if (!empty($legacyProduct->properties)) {
                            $decoded = json_decode($legacyProduct->properties, true);
                            if (json_last_error() !== JSON_ERROR_NONE) {
                                $decoded = json_decode(stripslashes($legacyProduct->properties), true);
                            }

                            if (is_array($decoded)) {
                                foreach ($decoded as $propId => $propValue) {
                                    if (isset($attributeMap[$propId])) {
                                        $attrId   = $attributeMap[$propId];
                                        $attrName = Attribute::find($attrId)->name ?? $propId;
                                    } else {
                                        $attrName = 'ویژگی_' . $propId;
                                        $newAttr  = Attribute::firstOrCreate(
                                            ['name' => $attrName],
                                            ['type' => 'text', 'is_filterable' => true]
                                        );
                                        $attrId = $newAttr->id;
                                        $attributeMap[$propId] = $attrId;
                                    }

                                    if ($newCategoryId) {
                                        DB::table('attribute_category')->updateOrInsert(
                                            ['attribute_id' => $attrId, 'category_id' => $newCategoryId],
                                            []
                                        );
                                    }

                                    $fullValue     = (string) $propValue;
                                    $normalizedValue = $this->normalizeAttributeValue($fullValue);
                                    $truncatedValue  = mb_substr($normalizedValue, 0, 250);

                                    $specifications[$attrName] = $fullValue;

                                    $attrValueRecord = DB::table('attribute_values')
                                        ->where('attribute_id', $attrId)
                                        ->where('value', $truncatedValue)
                                        ->first();

                                    if ($attrValueRecord) {
                                        $attrValueId = $attrValueRecord->id;
                                        if ($attrValueRecord->value !== $truncatedValue) {
                                            DB::table('attribute_values')->where('id', $attrValueId)->update([
                                                'value'      => $truncatedValue,
                                                'updated_at' => Carbon::now(),
                                            ]);
                                        }
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
                                        'value'              => $fullValue,
                                    ];
                                }
                            }
                        }

                        $isActive = (bool) $legacyProduct->active;

                        $existingProduct = DB::table('products')->where('legacy_id', $legacyProduct->id)->first();
                        if (!$existingProduct) {
                            $fallback = DB::table('products')
                                ->whereNull('legacy_id')
                                ->where('title', $legacyProduct->name)
                                ->when($newCategoryId, fn($q) => $q->where('category_id', $newCategoryId))
                                ->first();

                            if ($fallback) {
                                DB::table('products')->where('id', $fallback->id)->update([
                                    'legacy_id'  => $legacyProduct->id,
                                    'updated_at' => Carbon::now(),
                                ]);
                                $existingProduct = DB::table('products')->where('id', $fallback->id)->first();
                            }
                        }

                        if ($existingProduct) {
                            $updates = [];
                            if ((bool)$existingProduct->is_active !== $isActive) {
                                $updates['is_active'] = true;
                            }
                            if ($existingProduct->title !== $legacyProduct->name) {
                                $updates['title'] = $legacyProduct->name;
                            }
                            if ($existingProduct->category_id !== $newCategoryId) {
                                $updates['category_id'] = $newCategoryId;
                            }
                            $newStatus = 'active';
                            if ($existingProduct->status !== $newStatus) {
                                $updates['status'] = $newStatus;
                            }
                            if (!empty($specifications) && json_encode($existingProduct->specifications) !== json_encode($specifications)) {
                                $updates['specifications'] = json_encode($specifications);
                            }

                            if (!empty($updates)) {
                                $updates['updated_at'] = Carbon::now();
                                DB::table('products')->where('id', $existingProduct->id)->update($updates);
                            }

                            $productId = $existingProduct->id;

                            $newAttributeValueIds = collect($syncAttributesData)
                                ->pluck('attribute_value_id')
                                ->filter()
                                ->all();

                            DB::table('attribute_product')
                                ->where('product_id', $productId)
                                ->whereNotIn('attribute_value_id', $newAttributeValueIds)
                                ->delete();

                            foreach ($syncAttributesData as $attrData) {
                                DB::table('attribute_product')->updateOrInsert(
                                    ['product_id' => $productId, 'attribute_value_id' => $attrData['attribute_value_id']],
                                    ['value' => $attrData['value'], 'updated_at' => Carbon::now()]
                                );
                            }

                            $currentTitle = $updates['title'] ?? $existingProduct->title;
                            $this->regenerateSlugIfNeeded($existingProduct->id, $currentTitle, $existingProduct->slug);
                            if ($this->option('sync-images') && !empty($legacyProduct->images)) {
                                $this->importImages($legacyProduct->images, $productId, $existingProduct->slug, $currentTitle);
                            }
                        } else {
                            $persianSlug = $this->makeUniquePersianSlug($legacyProduct->name);

                            $newProduct = Product::create([
                                'supplier_id'    => $defaultSupplier->id,
                                'category_id'    => $newCategoryId,
                                'title'          => $legacyProduct->name,
                                'slug'           => $persianSlug,
                                'sku'            => 'محصول-' . $legacyProduct->id . '-' . rand(1000, 9999),
                                'specifications' => empty($specifications) ? null : $specifications,
                                'is_active'      => true,
                                'status'         => 'active',
                                'view_count'     => 0,
                                'legacy_id'      => $legacyProduct->id,
                            ]);

                            foreach ($syncAttributesData as $attrData) {
                                DB::table('attribute_product')->insert([
                                    'product_id'         => $newProduct->id,
                                    'attribute_value_id' => $attrData['attribute_value_id'],
                                    'value'              => $attrData['value'],
                                    'created_at'         => Carbon::now(),
                                    'updated_at'         => Carbon::now(),
                                ]);
                            }

                            if (!empty($legacyProduct->images)) {
                                $this->importImages($legacyProduct->images, $newProduct->id, $persianSlug, $legacyProduct->name);
                            }
                        }

                        $bar->advance();
                    }

                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollBack();
                    $this->error("\nError in chunk starting at index {$i}: " . $e->getMessage());
                    // ادامه اجرای حلقه‌های بعدی حتی در صورت خطا در یک چانک
                }
            }

            $bar->finish();
            $this->newLine();
            $this->info('Legacy catalog synced successfully!');

        } catch (\Exception $e) {
            $this->error('Fatal Error: ' . $e->getMessage());
        }
    }

    private function normalizeAttributeValue(string $value): string
    {
        $value = trim($value);
        $value = str_replace(['ي', 'ك', 'ۀ', 'ة', 'ؤ', 'إ', 'أ', "\u{200C}"], ['ی', 'ک', 'ه', 'ه', 'و', 'ا', 'ا', ' '], $value);
        $value = preg_replace('/\s+/', ' ', $value);
        return trim($value);
    }

    private function regenerateSlugIfNeeded(int $productId, string $title, string $currentSlug): void
    {
        $expectedSlug = $this->makeUniquePersianSlug($title, $productId);
        if ($currentSlug !== $expectedSlug) {
            ProductSlugRedirect::updateOrCreate(
                ['old_slug' => $currentSlug],
                ['product_id' => $productId]
            );
            DB::table('products')->where('id', $productId)->update([
                'slug'       => $expectedSlug,
                'updated_at' => Carbon::now(),
            ]);
        }
    }

    private function makeUniquePersianSlug(string $title, int $ignoreProductId = 0): string
    {
        $base = mb_substr($this->makePersianSlug($title), 0, 220);
        $slug = $base;
        $counter = 2;
        while (DB::table('products')
            ->where('slug', $slug)
            ->when($ignoreProductId, fn($q) => $q->where('id', '!=', $ignoreProductId))
            ->exists()
        ) {
            $slug = $base . '-' . $counter++;
        }
        return $slug;
    }

    private function importImages(string $rawImages, int $productId, string $productSlug, string $productName): void
    {
        $decoded = json_decode($rawImages, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $decoded = json_decode(stripslashes($rawImages), true);
        }
        $imagesList = is_array($decoded)
            ? array_values(array_filter($decoded))
            : array_values(array_filter(explode(',', $rawImages)));

        $srcDir       = storage_path('app/public/products/change-image');
        $imageService = app(\App\Services\ProductImageService::class);

        foreach ($imagesList as $index => $imgItem) {
            $imagePath = is_array($imgItem)
                ? ($imgItem['url'] ?? $imgItem['path'] ?? $imgItem['file'] ?? '')
                : (string) $imgItem;

            $originalFilename = explode('?', basename(trim($imagePath)))[0];
            if (empty($originalFilename)) continue;

            $ext = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) continue;

            $physicalSrc = $srcDir . DIRECTORY_SEPARATOR . $originalFilename;
            if (!file_exists($physicalSrc)) continue;

            try {


                $result = $imageService->process(
                    $physicalSrc,
                    $productSlug,
                    $index + 1,
                    true
                );

                if (!$result) {
                    $this->warn("Image processing failed [{$originalFilename}]");
                    continue;
                }
            } catch (\Exception $e) {
                $this->warn("Image failed [{$originalFilename}]: " . $e->getMessage());
                continue;
            }

            // استفاده از $baseName محلی به جای دسترسی به کلید ناموجود در $result
            $exists = DB::table('product_galleries')
                ->where('product_id', $productId)
                ->where('base_name', $result['file_name'])
                ->exists();

            if (!$exists) {
                $seoText = $productName . ($index > 0 ? ' - تصویر ' . ($index + 1) : '');
                DB::table('product_galleries')->insert([
                    'product_id' => $productId,
                    'base_name' => $result['base_name'],
                    'file_name' => $result['file_name'],
                    'file_path' => $result['file_path'],
                    'file_type' => 'image',
                    'mime_type' => $result['mime_type'] ?? 'image/webp',
                    'file_size' => $result['file_size'] ?? 0,
                    'width' => $result['width'] ?? null,
                    'height' => $result['height'] ?? null,
                    'versions' => json_encode($result['versions'] ?? []),
                    'is_primary' => $index === 0,
                    'sort_order' => $index,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'alt_text' => $seoText,
                    'title' => $seoText,
                ]);
            }
        }
    }

}
