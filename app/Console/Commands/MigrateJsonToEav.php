<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Product;
use App\Models\Attribute;
use App\Models\AttributeValue;
use Illuminate\Support\Facades\DB;

class MigrateJsonToEav extends Command
{
    protected $signature = 'eav:migrate-json';
    protected $description = 'تبدیل مشخصات JSON محصولات فعلی به ساختار جدید EAV';

    public function handle()
    {
        $this->info('شروع انتقال داده‌های JSON به EAV...');

        $products = Product::whereNotNull('specifications')->get();
        $bar = $this->output->createProgressBar(count($products));

        DB::beginTransaction();
        try {
            foreach ($products as $product) {
                $specs = $product->specifications;

                if (!is_array($specs)) {
                    $specs = json_decode($specs, true) ?? [];
                }

                foreach ($specs as $key => $value) {
                    // ۱. پیدا کردن یا ایجاد ویژگی (Attribute) بر اساس کلید JSON
                    $attribute = Attribute::firstOrCreate(
                        ['name' => $key],
                        [
                            'type' => 'text', // نوع پیش‌فرض
                            'is_filterable' => true,
                        ]
                    );

                    // ۲. اتصال ویژگی به دسته‌بندی محصول (در صورت عدم وجود)
                    $product->category->attributes()->syncWithoutDetaching([$attribute->id]);

                    // ۳. پیدا کردن یا ایجاد مقدار ویژگی (AttributeValue)
                    $attributeValue = AttributeValue::firstOrCreate([
                        'attribute_id' => $attribute->id,
                        'value' => (string) $value,
                    ]);

                    // ۴. اتصال مقدار به محصول در جدول واسط (attribute_product)
                    // فرض بر این است که متد values() در مدل Product رابطه morphToMany یا belongsToMany با AttributeValue دارد
                    $product->attributeValues()->syncWithoutDetaching([$attributeValue->id]);
                }

                $bar->advance();
            }

            DB::commit();
            $this->newLine();
            $this->info('انتقال داده‌ها با موفقیت انجام شد.');

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('خطا در انتقال داده‌ها: ' . $e->getMessage());
        }
    }
}
