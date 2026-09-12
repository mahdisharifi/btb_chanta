<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * ✅ این migration تمام محصولات موجود را به ساختار جدید 4 تیراژی تبدیل می‌کند:
     * 
     * تیراژ 0: زیر کارتن (1 تا کارتن-1)
     * تیراژ 1: کارتن (1 تا 4 کارتن) - مرجع
     * تیراژ 2: عمده (5 تا 15 کارتن)
     * تیراژ 3: بار (16+ کارتن)
     */
    public function up(): void
    {
        echo "═══════════════════════════════════════════════════════════\n";
        echo "شروع به‌روزرسانی تیراژها به ساختار جدید 4 تیراژی...\n";
        echo "═══════════════════════════════════════════════════════════\n\n";

        // دریافت تمام محصولاتی که تیراژ دارند
        $products = DB::table('products')
            ->whereNotNull('quantity_tiers')
            ->where('quantity_tiers', '!=', '[]')
            ->select('id', 'title', 'quantity_tiers', 'quantity_per_packaging', 'packaging_unit_type')
            ->get();

        $totalProducts = $products->count();
        $updatedCount = 0;
        $skippedCount = 0;
        $errorCount = 0;

        echo "تعداد کل محصولات: {$totalProducts}\n\n";

        foreach ($products as $product) {
            try {
                $oldTiers = json_decode($product->quantity_tiers, true);
                
                if (!is_array($oldTiers) || empty($oldTiers)) {
                    echo "⚠️  محصول #{$product->id} ({$product->title}): تیراژهای نامعتبر - Skip\n";
                    $skippedCount++;
                    continue;
                }

                // تعداد عناصر در کارتن
                $qtyPerPack = (int)($product->quantity_per_packaging ?? 12);
                $unitLabel = $this->getPackagingUnitLabel($product->packaging_unit_type ?? 'carton');

                // ✅ ساخت تیراژهای جدید
                $newTiers = $this->generateNewTiers($qtyPerPack, $unitLabel);

                // به‌روزرسانی
                DB::table('products')
                    ->where('id', $product->id)
                    ->update([
                        'quantity_tiers' => json_encode($newTiers, JSON_UNESCAPED_UNICODE),
                        'updated_at' => now(),
                    ]);

                $updatedCount++;
                echo "✓ محصول #{$product->id} ({$product->title}): به‌روزرسانی شد\n";

            } catch (\Exception $e) {
                $errorCount++;
                echo "✗ محصول #{$product->id}: خطا - {$e->getMessage()}\n";
            }
        }

        echo "\n═══════════════════════════════════════════════════════════\n";
        echo "✅ به‌روزرسانی کامل شد!\n";
        echo "   - به‌روزرسانی شده: {$updatedCount}\n";
        echo "   - Skip شده: {$skippedCount}\n";
        echo "   - خطا: {$errorCount}\n";
        echo "═══════════════════════════════════════════════════════════\n";
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        echo "⚠️  Rollback این migration پشتیبانی نمی‌شود.\n";
        echo "   لطفاً از backup استفاده کنید: backup_before_tier_changes_*.sql\n";
    }

    /**
     * تولید تیراژهای جدید بر اساس quantity_per_packaging
     */
    private function generateNewTiers(int $qtyPerPack, string $unitLabel): array
    {
        // ✅ استثنا: اگر کارتن = 1
        if ($qtyPerPack === 1) {
            return [
                0 => [
                    'name' => 'زیر کارتن',
                    'min_quantity' => 0,
                    'max_quantity' => 0,
                    'description' => 'غیرفعال (کارتن 1 عددی است)',
                    'is_active' => false,
                ],
                1 => [
                    'name' => 'کارتن',
                    'min_quantity' => 1,
                    'max_quantity' => 4,
                    'description' => "1 تا 4 {$unitLabel}",
                    'is_active' => true,
                ],
                2 => [
                    'name' => 'عمده',
                    'min_quantity' => 5,
                    'max_quantity' => 15,
                    'description' => "5 تا 15 {$unitLabel}",
                    'is_active' => true,
                ],
                3 => [
                    'name' => 'بار',
                    'min_quantity' => 16,
                    'max_quantity' => null,
                    'description' => "16 {$unitLabel} به بالا",
                    'is_active' => true,
                ],
            ];
        }

        // ✅ حالت عادی (quantity_per_packaging >= 2)
        return [
            0 => [
                'name' => 'زیر کارتن',
                'min_quantity' => 1,
                'max_quantity' => $qtyPerPack - 1,
                'description' => "کمتر از یک {$unitLabel} (1 تا " . ($qtyPerPack - 1) . " عدد)",
                'is_active' => true,
            ],
            1 => [
                'name' => 'کارتن',
                'min_quantity' => $qtyPerPack,
                'max_quantity' => $qtyPerPack * 4,
                'description' => "1 تا 4 {$unitLabel} ({$qtyPerPack} تا " . ($qtyPerPack * 4) . " عدد)",
                'is_active' => true,
            ],
            2 => [
                'name' => 'عمده',
                'min_quantity' => $qtyPerPack * 5,
                'max_quantity' => $qtyPerPack * 15,
                'description' => "5 تا 15 {$unitLabel} (" . ($qtyPerPack * 5) . " تا " . ($qtyPerPack * 15) . " عدد)",
                'is_active' => true,
            ],
            3 => [
                'name' => 'بار',
                'min_quantity' => $qtyPerPack * 16,
                'max_quantity' => null,
                'description' => "16 {$unitLabel} به بالا (" . ($qtyPerPack * 16) . " عدد به بالا)",
                'is_active' => true,
            ],
        ];
    }

    /**
     * دریافت برچسب فارسی واحد بسته‌بندی
     */
    private function getPackagingUnitLabel(?string $packagingType): string
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

        return $labels[$packagingType ?? 'carton'] ?? 'کارتن';
    }
};
