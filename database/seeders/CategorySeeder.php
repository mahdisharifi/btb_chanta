<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            /*
            |----------------------------------------------------------
            | 1. Electronics (لوازم الکترونیک)
            |----------------------------------------------------------
            */
            [
                'name' => 'لوازم الکترونیک',
                'slug' => 'electronics',
                'description' => 'انواع لوازم الکترونیکی و دیجیتال',
                'icon' => 'cpu',
                'sort_order' => 1,
                'seo_title' => 'خرید لوازم الکترونیک | بازار B2B',
                'seo_description' => 'خرید عمده انواع لوازم الکترونیکی با بهترین قیمت از تأمین‌کنندگان معتبر',
                'children' => [
                    [
                        'name' => 'موبایل و تبلت',
                        'slug' => 'mobile-tablet',
                        'description' => 'انواع گوشی موبایل و تبلت',
                        'icon' => 'smartphone',
                        'sort_order' => 1,
                        'children' => [
                            ['name' => 'سامسونگ', 'slug' => 'samsung', 'sort_order' => 1],
                            ['name' => 'اپل', 'slug' => 'apple', 'sort_order' => 2],
                            ['name' => 'شیائومی', 'slug' => 'xiaomi', 'sort_order' => 3],
                            ['name' => 'هواوی', 'slug' => 'huawei', 'sort_order' => 4],
                        ],
                    ],
                    [
                        'name' => 'لپ‌تاپ',
                        'slug' => 'laptop',
                        'description' => 'انواع لپ‌تاپ و نوت‌بوک',
                        'icon' => 'laptop',
                        'sort_order' => 2,
                        'children' => [
                            ['name' => 'لپ‌تاپ گیمینگ', 'slug' => 'gaming-laptop', 'sort_order' => 1],
                            ['name' => 'لپ‌تاپ اداری', 'slug' => 'business-laptop', 'sort_order' => 2],
                            ['name' => 'لپ‌تاپ دانشجویی', 'slug' => 'student-laptop', 'sort_order' => 3],
                        ],
                    ],
                    [
                        'name' => 'لوازم جانبی موبایل',
                        'slug' => 'mobile-accessories',
                        'description' => 'انواع لوازم جانبی گوشی و تبلت',
                        'icon' => 'headphones',
                        'sort_order' => 3,
                    ],
                    [
                        'name' => 'کامپیوتر و قطعات',
                        'slug' => 'computer-parts',
                        'description' => 'قطعات و لوازم جانبی کامپیوتر',
                        'icon' => 'monitor',
                        'sort_order' => 4,
                    ],
                ],
            ],

            /*
            |----------------------------------------------------------
            | 2. Home Appliances (لوازم خانگی)
            |----------------------------------------------------------
            */
            [
                'name' => 'لوازم خانگی',
                'slug' => 'home-appliances',
                'description' => 'انواع لوازم خانگی برقی و غیر برقی',
                'icon' => 'home',
                'sort_order' => 2,
                'seo_title' => 'خرید لوازم خانگی | بازار B2B',
                'seo_description' => 'خرید عمده انواع لوازم خانگی با قیمت مناسب',
                'children' => [
                    [
                        'name' => 'آشپزخانه',
                        'slug' => 'kitchen-appliances',
                        'description' => 'لوازم آشپزخانه',
                        'icon' => 'utensils',
                        'sort_order' => 1,
                        'children' => [
                            ['name' => 'یخچال و فریزر', 'slug' => 'refrigerator', 'sort_order' => 1],
                            ['name' => 'ماشین ظرفشویی', 'slug' => 'dishwasher', 'sort_order' => 2],
                            ['name' => 'اجاق‌گاز', 'slug' => 'stove', 'sort_order' => 3],
                        ],
                    ],
                    [
                        'name' => 'شست‌وشو و نظافت',
                        'slug' => 'cleaning',
                        'description' => 'لوازم نظافت و شست‌وشو',
                        'icon' => 'sparkles',
                        'sort_order' => 2,
                        'children' => [
                            ['name' => 'ماشین لباسشویی', 'slug' => 'washing-machine', 'sort_order' => 1],
                            ['name' => 'جاروبرقی', 'slug' => 'vacuum-cleaner', 'sort_order' => 2],
                        ],
                    ],
                    [
                        'name' => 'صوتی و تصویری',
                        'slug' => 'audio-video',
                        'description' => 'تلویزیون و سیستم‌های صوتی',
                        'icon' => 'tv',
                        'sort_order' => 3,
                    ],
                ],
            ],

            /*
            |----------------------------------------------------------
            | 3. Fashion (مد و پوشاک)
            |----------------------------------------------------------
            */
            [
                'name' => 'مد و پوشاک',
                'slug' => 'fashion',
                'description' => 'انواع پوشاک و مد',
                'icon' => 'shirt',
                'sort_order' => 3,
                'seo_title' => 'خرید عمده پوشاک | بازار B2B',
                'seo_description' => 'خرید عمده انواع پوشاک مردانه و زنانه',
                'children' => [
                    ['name' => 'پوشاک مردانه', 'slug' => 'men-clothing', 'sort_order' => 1],
                    ['name' => 'پوشاک زنانه', 'slug' => 'women-clothing', 'sort_order' => 2],
                    ['name' => 'پوشاک بچگانه', 'slug' => 'kids-clothing', 'sort_order' => 3],
                    ['name' => 'کیف و کفش', 'slug' => 'bags-shoes', 'sort_order' => 4],
                    ['name' => 'اکسسوری', 'slug' => 'accessories', 'sort_order' => 5],
                ],
            ],

            /*
            |----------------------------------------------------------
            | 4. Health & Beauty (بهداشت و زیبایی)
            |----------------------------------------------------------
            */
            [
                'name' => 'بهداشت و زیبایی',
                'slug' => 'health-beauty',
                'description' => 'محصولات بهداشتی و آرایشی',
                'icon' => 'heart',
                'sort_order' => 4,
                'seo_title' => 'خرید عمده محصولات بهداشتی | بازار B2B',
                'seo_description' => 'خرید عمده انواع محصولات آرایشی و بهداشتی',
                'children' => [
                    ['name' => 'آرایشی', 'slug' => 'cosmetics', 'sort_order' => 1],
                    ['name' => 'بهداشتی', 'slug' => 'hygiene', 'sort_order' => 2],
                    ['name' => 'عطر و ادکلن', 'slug' => 'perfume', 'sort_order' => 3],
                    ['name' => 'مراقبت پوست', 'slug' => 'skincare', 'sort_order' => 4],
                ],
            ],

            /*
            |----------------------------------------------------------
            | 5. Food & Beverage (مواد غذایی)
            |----------------------------------------------------------
            */
            [
                'name' => 'مواد غذایی و نوشیدنی',
                'slug' => 'food-beverage',
                'description' => 'انواع مواد غذایی و نوشیدنی',
                'icon' => 'shopping-basket',
                'sort_order' => 5,
                'seo_title' => 'خرید عمده مواد غذایی | بازار B2B',
                'seo_description' => 'خرید عمده انواع مواد غذایی و نوشیدنی',
                'children' => [
                    ['name' => 'لبنیات', 'slug' => 'dairy', 'sort_order' => 1],
                    ['name' => 'نوشیدنی', 'slug' => 'beverages', 'sort_order' => 2],
                    ['name' => 'خشکبار و تنقلات', 'slug' => 'snacks', 'sort_order' => 3],
                    ['name' => 'کنسرو و غذای آماده', 'slug' => 'canned-food', 'sort_order' => 4],
                ],
            ],
        ];

        foreach ($categories as $categoryData) {
            $this->createCategory($categoryData);
        }
    }

    /**
     * Recursively create categories with children.
     */
    private function createCategory(array $data, ?int $parentId = null): void
    {
        $children = $data['children'] ?? [];
        unset($data['children']);

        $data['parent_id'] = $parentId;
        $data['is_active'] = $data['is_active'] ?? true;

        $category = Category::create($data);

        foreach ($children as $child) {
            $this->createCategory($child, $category->id);
        }
    }
}
