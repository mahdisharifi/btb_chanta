<?php
// database/seeders/MultiSupplierSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MultiSupplierSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        // ──────────────────────────────────────────────────────
        // مرحله 1: انتقال تأمین‌کنندگان فعلی به جدول واسط
        // ──────────────────────────────────────────────────────

        $this->command->info('📦 در حال انتقال تأمین‌کنندگان فعلی به جدول واسط...');

        $existingProducts = DB::table('products')
            ->whereNull('deleted_at')
            ->select('id', 'supplier_id', 'sku')
            ->get();

        $productSupplierRecords = [];
        foreach ($existingProducts as $product) {
            $productSupplierRecords[] = [
                'product_id'  => $product->id,
                'supplier_id' => $product->supplier_id,
                'sku'         => $product->sku,
                'is_primary'  => true,      // تأمین‌کننده فعلی = اصلی
                'is_active'   => true,
                'sort_order'  => 1,
                'created_at'  => $now,
                'updated_at'  => $now,
            ];
        }

        DB::table('product_supplier')->insert($productSupplierRecords);
        $this->command->info('✅ ' . count($productSupplierRecords) . ' رکورد به جدول واسط منتقل شد.');

        // ──────────────────────────────────────────────────────
        // مرحله 2: به‌روزرسانی product_prices با product_supplier_id
        // ──────────────────────────────────────────────────────

        $this->command->info('🔗 در حال اتصال قیمت‌های فعلی به جدول واسط...');

        $productSuppliers = DB::table('product_supplier')->get();
        foreach ($productSuppliers as $ps) {
            DB::table('product_prices')
                ->where('product_id', $ps->product_id)
                ->whereNull('product_supplier_id')
                ->update(['product_supplier_id' => $ps->id]);
        }
        $this->command->info('✅ قیمت‌های فعلی به جدول واسط متصل شدند.');

        // ──────────────────────────────────────────────────────
        // مرحله 3: اضافه کردن تأمین‌کنندگان اضافی به محصولات پرطرفدار
        // ──────────────────────────────────────────────────────

        $this->command->info('➕ در حال اضافه کردن تأمین‌کنندگان جدید...');

        // تأمین‌کنندگان موجود:
        // 1 = پارس تکنولوژی (الکترونیک)
        // 2 = خانه‌سازان البرز (لوازم خانگی)
        // 3 = آرین تجارت (مواد غذایی)

        $multiSupplierData = [
            // ───── Samsung Galaxy S25 Ultra (product_id: 1) ─────
            // تأمین‌کننده اصلی: پارس تکنولوژی (1) → از قبل هست
            // اضافه: خانه‌سازان البرز (2) با قیمت‌های متفاوت
            [
                'product_id'  => 1,
                'supplier_id' => 2,
                'sku'         => 'KHA-SAM-S25U',
                'is_primary'  => false,
                'is_active'   => true,
                'sort_order'  => 2,
                'prices'      => [
                    ['price_type' => 'sub_carton',    'price' => 88500000, 'min_quantity' => 1,  'max_quantity' => 4, 'description' => 'قیمت زیر کارتن'],
                    ['price_type' => 'carton',        'price' => 86000000, 'min_quantity' => 5,  'max_quantity' => null, 'description' => 'قیمت کارتن (۵ به بالا)'],
                    ['price_type' => 'warranty',      'price' => 91000000, 'min_quantity' => 1,  'warranty_months' => 12, 'description' => 'با گارانتی ۱۲ ماهه'],
                    ['price_type' => 'check_1_month', 'price' => 89500000, 'min_quantity' => 1,  'check_months' => 1, 'description' => 'چکی ۱ ماهه'],
                ],
            ],
            // اضافه: آرین تجارت (3) هم Samsung می‌فروشد
            [
                'product_id'  => 1,
                'supplier_id' => 3,
                'sku'         => 'ARI-SAM-S25U',
                'is_primary'  => false,
                'is_active'   => true,
                'sort_order'  => 3,
                'prices'      => [
                    ['price_type' => 'sub_carton',    'price' => 87000000, 'min_quantity' => 1,  'max_quantity' => 4, 'description' => 'قیمت زیر کارتن — رقابتی!'],
                    ['price_type' => 'carton',        'price' => 84500000, 'min_quantity' => 5,  'max_quantity' => null, 'description' => 'قیمت کارتن — ارزان‌ترین'],
                    ['price_type' => 'warranty',      'price' => 90000000, 'min_quantity' => 1,  'warranty_months' => 18, 'description' => 'با گارانتی ۱۸ ماهه طلایی'],
                    ['price_type' => 'check_2_month', 'price' => 89000000, 'min_quantity' => 1,  'check_months' => 2, 'description' => 'چکی ۲ ماهه'],
                    ['price_type' => 'check_3_month', 'price' => 91500000, 'min_quantity' => 1,  'check_months' => 3, 'description' => 'چکی ۳ ماهه'],
                ],
            ],

            // ───── iPhone 16 Pro Max (product_id: 2) ─────
            // تأمین‌کننده اصلی: پارس تکنولوژی (1) → از قبل هست
            // اضافه: آرین تجارت (3)
            [
                'product_id'  => 2,
                'supplier_id' => 3,
                'sku'         => 'ARI-IP16PM',
                'is_primary'  => false,
                'is_active'   => true,
                'sort_order'  => 2,
                'prices'      => [
                    ['price_type' => 'sub_carton',    'price' => 92000000, 'min_quantity' => 1,  'max_quantity' => 4, 'description' => 'قیمت زیر کارتن'],
                    ['price_type' => 'carton',        'price' => 89500000, 'min_quantity' => 5,  'max_quantity' => null, 'description' => 'قیمت کارتن'],
                    ['price_type' => 'warranty',      'price' => 95000000, 'min_quantity' => 1,  'warranty_months' => 12, 'description' => 'با گارانتی ۱۲ ماهه'],
                    ['price_type' => 'warranty',      'price' => 97000000, 'min_quantity' => 1,  'warranty_months' => 24, 'description' => 'با گارانتی ۲۴ ماهه VIP'],
                    ['price_type' => 'check_1_month', 'price' => 93500000, 'min_quantity' => 1,  'check_months' => 1, 'description' => 'چکی ۱ ماهه'],
                ],
            ],

            // ───── هدفون Sony WH-1000XM5 (product_id: 4) ─────
            // تأمین‌کننده اصلی: پارس تکنولوژی (1) → از قبل هست
            // اضافه: خانه‌سازان البرز (2)
            [
                'product_id'  => 4,
                'supplier_id' => 2,
                'sku'         => 'KHA-SONY-XM5',
                'is_primary'  => false,
                'is_active'   => true,
                'sort_order'  => 2,
                'prices'      => [
                    ['price_type' => 'sub_carton',    'price' => 73000000, 'min_quantity' => 1, 'max_quantity' => 4, 'description' => 'قیمت زیر کارتن'],
                    ['price_type' => 'carton',        'price' => 71000000, 'min_quantity' => 5, 'max_quantity' => null, 'description' => 'قیمت کارتن'],
                    ['price_type' => 'warranty',      'price' => 76000000, 'min_quantity' => 1, 'warranty_months' => 18, 'description' => 'با گارانتی ۱۸ ماهه'],
                ],
            ],

            // ───── نوشابه کوکاکولا (product_id: 12) ─────
            // تأمین‌کننده اصلی: آرین تجارت (3) → از قبل هست
            // اضافه: پارس تکنولوژی (1) هم مواد غذایی وارد می‌کند
            [
                'product_id'  => 12,
                'supplier_id' => 1,
                'sku'         => 'PAR-COCA-330',
                'is_primary'  => false,
                'is_active'   => true,
                'sort_order'  => 2,
                'prices'      => [
                    ['price_type' => 'sub_carton', 'price' => 285000, 'min_quantity' => 1,  'max_quantity' => 9, 'description' => 'قیمت زیر کارتن'],
                    ['price_type' => 'carton',     'price' => 268000, 'min_quantity' => 10, 'max_quantity' => null, 'description' => 'قیمت کارتن (۱۰ به بالا)'],
                ],
            ],

            // ───── MacBook Pro M4 (product_id: 3) ─────
            // اضافه: خانه‌سازان البرز (2)
            [
                'product_id'  => 3,
                'supplier_id' => 2,
                'sku'         => 'KHA-MBP-M4',
                'is_primary'  => false,
                'is_active'   => true,
                'sort_order'  => 2,
                'prices'      => [
                    ['price_type' => 'sub_carton',    'price' => 445000000, 'min_quantity' => 1,  'max_quantity' => 2, 'description' => 'قیمت تکی'],
                    ['price_type' => 'carton',        'price' => 430000000, 'min_quantity' => 3,  'max_quantity' => null, 'description' => 'قیمت عمده (۳ به بالا)'],
                    ['price_type' => 'warranty',      'price' => 465000000, 'min_quantity' => 1,  'warranty_months' => 24, 'description' => 'با گارانتی ۲۴ ماهه اپل'],
                    ['price_type' => 'check_1_month', 'price' => 455000000, 'min_quantity' => 1,  'check_months' => 1, 'description' => 'چکی ۱ ماهه'],
                    ['price_type' => 'check_2_month', 'price' => 462000000, 'min_quantity' => 1,  'check_months' => 2, 'description' => 'چکی ۲ ماهه'],
                ],
            ],
        ];

        foreach ($multiSupplierData as $entry) {
            // ثبت در جدول واسط
            $psId = DB::table('product_supplier')->insertGetId([
                'product_id'  => $entry['product_id'],
                'supplier_id' => $entry['supplier_id'],
                'sku'         => $entry['sku'],
                'is_primary'  => $entry['is_primary'],
                'is_active'   => $entry['is_active'],
                'sort_order'  => $entry['sort_order'],
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);

            // ثبت قیمت‌ها
            foreach ($entry['prices'] as $price) {
                DB::table('product_prices')->insert([
                    'product_id'          => $entry['product_id'],
                    'product_supplier_id' => $psId,
                    'price_type'          => $price['price_type'],
                    'price'               => $price['price'],
                    'min_quantity'         => $price['min_quantity'],
                    'max_quantity'         => $price['max_quantity'] ?? null,
                    'warranty_months'      => $price['warranty_months'] ?? null,
                    'check_months'         => $price['check_months'] ?? null,
                    'description'          => $price['description'],
                    'is_active'            => true,
                    'valid_from'           => null,
                    'valid_until'          => null,
                    'created_at'           => $now,
                    'updated_at'           => $now,
                ]);
            }

            $supplierName = DB::table('suppliers')->where('id', $entry['supplier_id'])->value('company_name');
            $productTitle = DB::table('products')->where('id', $entry['product_id'])->value('title');
            $this->command->info("  ✅ {$supplierName} → {$productTitle} (" . count($entry['prices']) . " قیمت)");
        }

        $this->command->newLine();
        $this->command->info('🎉 تمام تأمین‌کنندگان جدید اضافه شدند!');
        $this->command->newLine();

        // ──────────────────────────────────────────────────────
        // گزارش نهایی
        // ──────────────────────────────────────────────────────

        $this->command->table(
            ['محصول', 'تعداد تأمین‌کنندگان'],
            DB::table('product_supplier')
                ->join('products', 'products.id', '=', 'product_supplier.product_id')
                ->select('products.title', DB::raw('COUNT(*) as supplier_count'))
                ->groupBy('products.id', 'products.title')
                ->having('supplier_count', '>', 1)
                ->get()
                ->map(fn($row) => [$row->title, $row->supplier_count])
                ->toArray()
        );
    }
}
