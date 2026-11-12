<?php

namespace App\Livewire\Concerns;

use App\Models\Product;

trait ManagesProductQuantityTiers
{
    public function addQuantityTier(): void
    {
        if (count($this->quantity_tiers) >= 4) {
            $this->dispatch('notify', [
                'message' => 'حداکثر 4 تیراژ می‌توانید تعریف کنید.',
                'type' => 'error',
            ]);
            return;
        }

        // محاسبه min_quantity جدید بر اساس max_quantity تیراژ قبلی
        if (! $this->canAddQuantityTier()) {
            $this->dispatch('notify', [
                'message' => 'برای افزودن تیراژ بعدی، ابتدا حداکثر تعداد تیراژ فعلی را وارد کنید.',
                'type' => 'error',
            ]);
            return;
        }

        $lastTier = $this->quantity_tiers[array_key_last($this->quantity_tiers)];
        $newMin = $this->normalizeMaxQuantity($lastTier['max_quantity'] ?? null) + 1;

        $this->quantity_tiers[] = [
            'name' => 'تیراژ ' . (count($this->quantity_tiers) + 1),
            'min_quantity' => $newMin,
            'max_quantity' => null,
        ];
    }

    public function canAddQuantityTier(): bool
    {
        if (count($this->quantity_tiers) >= 4 || $this->quantity_tiers === []) {
            return false;
        }

        $lastTier = $this->quantity_tiers[array_key_last($this->quantity_tiers)];
        $lastMax = $this->normalizeMaxQuantity($lastTier['max_quantity'] ?? null);

        return $lastMax !== null && $lastMax > 0;
    }
    
    /**
     * هنگامی که max_quantity تیراژی تغییر می‌کند، min_quantity تیراژ بعدی را خودکار محاسبه کن
     * و در صورت بی‌منطقی، تیراژهای بعدی را حذف کن
     */
    public function updateNextTierMin(int $index): void
    {
        $currentMax = $this->normalizeMaxQuantity($this->quantity_tiers[$index]['max_quantity'] ?? null);
        $currentMin = $this->quantity_tiers[$index]['min_quantity'] ?? 0;
        
        // بررسی 1: max باید بزرگتر از min باشد
        if ($currentMax !== null && $currentMax <= $currentMin) {
            // max نامعتبر است، تیراژهای بعدی را حذف کن
            $this->dispatch('notify', [
                'message' => 'حداکثر تعداد باید بزرگتر از حداقل تعداد باشد. تیراژهای بعدی حذف شدند.',
                'type' => 'warning',
            ]);
            return;
        }
        
        // اگر تیراژ بعدی وجود دارد
        if (isset($this->quantity_tiers[$index + 1])) {
            // فقط اگر max_quantity معتبر باشد
            if ($currentMax !== null && $currentMax > 0) {
                $expectedNextMin = $currentMax + 1;
                $this->quantity_tiers[$index + 1]['min_quantity'] = $expectedNextMin;
                
                // بررسی 2: اگر min جدید از max فعلی تیراژ بعدی بزرگتر شد
                $nextMax = $this->normalizeMaxQuantity($this->quantity_tiers[$index + 1]['max_quantity'] ?? null);
                if ($nextMax !== null && $nextMax < $expectedNextMin) {
                    // تداخل ایجاد شده، تیراژهای بعدی را حذف کن
                    $this->dispatch('notify', [
                        'message' => 'تغییر باعث تداخل در تیراژهای بعدی شد. تیراژهای نامعتبر حذف شدند.',
                        'type' => 'info',
                    ]);
                    return;
                }
                
                // بررسی تیراژهای بعدی را به‌طور بازگشتی انجام بده
                $this->cascadeUpdateTiers($index + 1);
            }
        }
    }
    
    /**
     * به‌روزرسانی بازگشتی تیراژهای بعدی
     */
    private function cascadeUpdateTiers(int $startIndex): void
    {
        // اگر تیراژ بعدی وجود ندارد، متوقف شو
        if (!isset($this->quantity_tiers[$startIndex + 1])) {
            return;
        }
        
        $currentMax = $this->normalizeMaxQuantity($this->quantity_tiers[$startIndex]['max_quantity'] ?? null);
        
        // اگر max فعلی معتبر نیست، متوقف شو
        if ($currentMax === null) {
            return;
        }
        
        $expectedNextMin = $currentMax + 1;
        $nextMax = $this->normalizeMaxQuantity($this->quantity_tiers[$startIndex + 1]['max_quantity'] ?? null);
        
        // اگر تداخل ایجاد شده، تیراژهای بعدی را حذف کن
        if ($nextMax !== null && $nextMax < $expectedNextMin) {
            $this->dispatch('notify', [
                'message' => 'بازه تیراژها معتبر نیست؛ حداکثر تیراژ بعدی با حداقل جدید آن تداخل دارد.',
                'type' => 'error',
            ]);
            return;
        }
        
        // به‌روزرسانی min تیراژ بعدی
        $this->quantity_tiers[$startIndex + 1]['min_quantity'] = $expectedNextMin;
        
        // ادامه به‌روزرسانی برای تیراژ بعدی
        $this->cascadeUpdateTiers($startIndex + 1);
    }
    
    /**
     * حذف تمام تیراژهای بعد از index مشخص شده
     */
    private function removeAllTiersAfter(int $index): void
    {
        $totalTiers = count($this->quantity_tiers);
        $removedCount = 0;
        
        // حذف تیراژهای بعد از index
        for ($i = $index + 1; $i < $totalTiers; $i++) {
            unset($this->quantity_tiers[$i]);
            $removedCount++;
        }
        
        // بازنویسی ایندکس‌ها
        $this->quantity_tiers = array_values($this->quantity_tiers);
        
        if ($removedCount > 0) {
            $this->dispatch('notify', [
                'message' => "{$removedCount} تیراژ نامعتبر حذف شد.",
                'type' => 'info',
            ]);
        }
    }
    
    /**
     * Livewire lifecycle hook - وقتی quantity_tiers تغییر می‌کند
     */
    public function updatedQuantityTiers($value, $key): void
    {
        $this->syncPackagingTier();
        if ($this->tiersApplied && str_starts_with($key, '0.')) {
            return;
        }

        if (str_ends_with($key, '.max_quantity')) {
            preg_match('/(\d+)\.max_quantity$/', $key, $matches);

            if (isset($matches[1])) {
                $index = (int) $matches[1];
                $this->quantity_tiers[$index]['max_quantity'] = $this->normalizeMaxQuantity($value);
            }
        }

        // اگر max_quantity تیراژی تغییر کرد
        if (strpos($key, '.max_quantity') !== false) {
            // پیدا کردن index تیراژ فعلی
            preg_match('/(\d+)\.max_quantity/', $key, $matches);
            if (isset($matches[1])) {
                $currentIndex = (int)$matches[1];
                $this->updateNextTierMin($currentIndex);
            }
        }
        
        // اگر min_quantity تیراژی تغییر کرد (برای بررسی دستی)
        if (strpos($key, '.min_quantity') !== false) {
            preg_match('/(\d+)\.min_quantity/', $key, $matches);
            if (isset($matches[1])) {
                $currentIndex = (int)$matches[1];
                $currentMin = $this->quantity_tiers[$currentIndex]['min_quantity'] ?? 0;
                $currentMax = $this->normalizeMaxQuantity($this->quantity_tiers[$currentIndex]['max_quantity'] ?? null);
                
                // بررسی: min باید کوچکتر از max باشد
                if ($currentMax !== null && $currentMin >= $currentMax) {
                    $this->dispatch('notify', [
                        'message' => 'حداقل تعداد نمی‌تواند بزرگتر یا مساوی حداکثر تعداد باشد.',
                        'type' => 'error',
                    ]);
                }
            }
        }
    }

    /**
     * حذف تیراژ
     */
    public function removeQuantityTier(int $index): void
    {
        if ($this->tiersApplied && $index === 0) {
            return;
        }

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

                $hasLaterPrices = $product->prices()
                    ->where('tier_index', '>', $index)
                    ->whereNotNull('price_type_id')
                    ->exists();
                
                if ($hasPrices || $hasLaterPrices) {
                    $tierName = $this->quantity_tiers[$index]['name'] ?? "تیراژ " . ($index + 1);
                    $this->dispatch('notify', [
                        'message' => "تیراژ «{$tierName}» یا تیراژهای بعد از آن قیمت‌گذاری شده‌اند و قابل حذف نیستند. ابتدا قیمت‌های مربوطه را حذف کنید.",
                        'type' => 'error',
                    ]);
                    return;
                }
            }
        }

        unset($this->quantity_tiers[$index]);
        $this->quantity_tiers = array_values($this->quantity_tiers); // Re-index

        if ($this->quantity_tiers !== []) {
            $lastIndex = array_key_last($this->quantity_tiers);
            $this->quantity_tiers[$lastIndex]['max_quantity'] = null;

            foreach ($this->quantity_tiers as $tierIndex => $tier) {
                if ($tierIndex === 0) {
                    continue;
                }

                $previousMax = $this->normalizeMaxQuantity(
                    $this->quantity_tiers[$tierIndex - 1]['max_quantity'] ?? null
                );

                if ($previousMax === null) {
                    continue;
                }

                $this->quantity_tiers[$tierIndex]['min_quantity'] = $previousMax + 1;
            }
        }
        
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
        if ($this->quantity_per_packaging < 1 || $this->quantity_per_packaging > 999999) {
            $this->dispatch('notify', [
                'message' => 'ابتدا تعداد در هر واحد بسته‌بندی را مشخص کنید.',
                'type' => 'error',
            ]);
            return;
        }

        if ($this->isEdit && $this->productId) {
            $product = Product::find($this->productId);
            $hasPrices = $product && $product->prices()
                ->whereNotNull('price_type_id')
                ->exists();

            if ($hasPrices) {
                $this->dispatch('notify', [
                    'message' => 'محصول دارای قیمت‌گذاری است؛ تیراژبندی خودکار بدون جابه‌جایی قیمت‌ها قابل اعمال نیست.',
                    'type' => 'error',
                ]);
                return;
            }
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
        // بررسی اینکه آیا محصول قیمت دارد یا خیر (فقط در حالت ویرایش)
        if ($this->isEdit && $this->productId) {
            $product = Product::find($this->productId);
            $hasPrices = $product && $product->prices()
                ->whereNotNull('price_type_id') // فقط قیمت‌های سیستم جدید
                ->exists();
                
            if ($hasPrices) {
                $this->dispatch('notify', [
                    'message' => 'امکان پاک کردن تیراژها وجود ندارد زیرا محصول دارای قیمت‌گذاری است.',
                    'type' => 'error',
                ]);
                return;
            }
        }
        
        $this->quantity_tiers = [];
        $this->showQuantityTiers = false;
        $this->tiersApplied = false;
        
        $this->dispatch('notify', [
            'message' => 'تیراژها پاک شدند. می‌توانید دوباره تیراژبندی خودکار را اعمال کنید.',
            'type' => 'info',
        ]);
    }

    /**
     * تولید پرامپت هوش مصنوعی بر اساس اطلاعات محصول
     * 
     * @return string
     */
    protected function generateAutoTiers(): array
    {
        $qtyPerPack = $this->quantity_per_packaging;
        
        if ($qtyPerPack <= 0) {
            return [];
        }
        
        $unitLabel = $this->getPackagingUnitLabel();
        
        // ═══════════════════════════════════════════════════════════
        // ✅ ساختار جدید 4 تیراژی با منطق بهبود یافته
        // ═══════════════════════════════════════════════════════════
        
        // ✅ حالت 1: اگر تعداد در کارتن = 1 باشد
        if ($qtyPerPack === 1) {
            return [
                0 => [
                    'name' => 'زیر کارتن',
                    'min_quantity' => 0,
                    'max_quantity' => 0,
                    'description' => 'غیرفعال (کارتن 1 عددی است)',
                    'is_active' => false, // ✅ غیرفعال
                ],
                1 => [
                    'name' => 'کارتن',
                    'min_quantity' => 1,
                    'max_quantity' => 5,
                    'description' => "1 تا 5 {$unitLabel}",
                    'is_active' => true,
                ],
                2 => [
                    'name' => 'عمده',
                    'min_quantity' => 6,
                    'max_quantity' => 15,
                    'description' => "6 تا 15 {$unitLabel}",
                    'is_active' => true,
                ],
                3 => [
                    'name' => 'کل موجودی',
                    'min_quantity' => 16,
                    'max_quantity' => null,
                    'description' => "16 {$unitLabel} به بالا",
                    'is_active' => true,
                ],
            ];
        }
        
        // ✅ حالت 2: اگر تعداد در کارتن = 2 باشد
        if ($qtyPerPack === 2) {
            return [
                0 => [
                    'name' => 'زیر کارتن',
                    'min_quantity' => 1,
                    'max_quantity' => 1,
                    'description' => "کمتر از یک {$unitLabel} (1 عدد)",
                    'is_active' => true,
                ],
                1 => [
                    'name' => 'کارتن',
                    'min_quantity' => $qtyPerPack,
                    'max_quantity' => $qtyPerPack * 5,
                    'description' => "1 تا 5 {$unitLabel} ({$qtyPerPack} تا " . ($qtyPerPack * 5) . " عدد)",
                    'is_active' => true,
                ],
                2 => [
                    'name' => 'عمده',
                    'min_quantity' => ($qtyPerPack * 5) + 1,
                    'max_quantity' => $qtyPerPack * 15,
                    'description' => "6 تا 15 {$unitLabel} (" . (($qtyPerPack * 5) + 1) . " تا " . ($qtyPerPack * 15) . " عدد)",
                    'is_active' => true,
                ],
                3 => [
                    'name' => 'کل موجودی',
                    'min_quantity' => ($qtyPerPack * 15) + 1,
                    'max_quantity' => null,
                    'description' => "16 {$unitLabel} به بالا (" . (($qtyPerPack * 15) + 1) . " عدد به بالا)",
                    'is_active' => true,
                ],
            ];
        }
        
        // ✅ حالت 3: اگر تعداد در کارتن > 2 باشد
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
                'max_quantity' => $qtyPerPack * 5,
                'description' => "1 تا 5 {$unitLabel} ({$qtyPerPack} تا " . ($qtyPerPack * 5) . " عدد)",
                'is_active' => true,
            ],
            2 => [
                'name' => 'عمده',
                'min_quantity' => ($qtyPerPack * 5) + 1,
                'max_quantity' => $qtyPerPack * 15,
                'description' => "6 تا 15 {$unitLabel} (" . (($qtyPerPack * 5) + 1) . " تا " . ($qtyPerPack * 15) . " عدد)",
                'is_active' => true,
            ],
            3 => [
                'name' => 'کل موجودی',
                'min_quantity' => ($qtyPerPack * 15) + 1,
                'max_quantity' => null,
                'description' => "16 {$unitLabel} به بالا (" . (($qtyPerPack * 15) + 1) . " عدد به بالا)",
                'is_active' => true,
            ],
        ];
    }

    public function updatedQuantityPerPackaging(): void
    {
        $this->syncPackagingTier();
    }

    private function syncPackagingTier(): void
    {
        if (!$this->tiersApplied || $this->quantity_per_packaging < 1 || !isset($this->quantity_tiers[0])) {
            return;
        }

        $this->quantity_tiers[0] = $this->generateAutoTiers()[0];
        if (isset($this->quantity_tiers[1])) {
            $this->quantity_tiers[1]['min_quantity'] = $this->quantity_per_packaging;
        }
    }

    private function normalizeMaxQuantity(mixed $value): mixed
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return $value;
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
