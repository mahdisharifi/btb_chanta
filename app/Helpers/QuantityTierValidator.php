<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * ✅ Helper برای Validation تیراژهای محصول
 * 
 * قوانین:
 * - حداقل 3 تیراژ الزامی
 * - حداکثر 5 تیراژ مجاز
 * - تیراژها نباید overlap داشته باشند
 * - min_quantity باید صعودی باشد
 * - آخرین تیراژ باید open-ended باشد (max_quantity = null)
 */
class QuantityTierValidator
{
    /**
     * Validate تیراژها
     * 
     * @param array $tiers
     * @throws ValidationException
     * @return bool
     */
    public static function validate(array $tiers): bool
    {
        // 1. بررسی تعداد تیراژها (3 تا 4)
        if (count($tiers) < 3) {
            throw ValidationException::withMessages([
                'quantity_tiers' => ['حداقل 3 تیراژ برای محصول الزامی است.']
            ]);
        }

        if (count($tiers) > 4) {
            throw ValidationException::withMessages([
                'quantity_tiers' => ['حداکثر 4 تیراژ می‌توانید تعریف کنید.']
            ]);
        }

        // 2. بررسی ساختار هر تیراژ
        foreach ($tiers as $index => $tier) {
            if (!isset($tier['name']) || empty(trim($tier['name']))) {
                throw ValidationException::withMessages([
                    "quantity_tiers.{$index}.name" => ['نام تیراژ الزامی است.']
                ]);
            }

            if (!isset($tier['min_quantity']) || !is_numeric($tier['min_quantity'])) {
                throw ValidationException::withMessages([
                    "quantity_tiers.{$index}.min_quantity" => ['حداقل تعداد باید عدد صحیح باشد.']
                ]);
            }

            $minQty = (int)$tier['min_quantity'];
            $maxQty = self::maxQuantity($tier);

            // min باید مثبت یا صفر باشد (صفر برای تیراژهای غیرفعال مجاز است)
            if ($minQty < 0) {
                throw ValidationException::withMessages([
                    "quantity_tiers.{$index}.min_quantity" => ['حداقل تعداد نمی‌تواند منفی باشد.']
                ]);
            }

            // ✅ استثنا: تیراژ غیرفعال (min=0, max=0) مجاز است
            $isInactiveTier = ($minQty === 0 && $maxQty === 0);
            
            // ✅ استثنا: تیراژ تک‌عددی (min=max) مجاز است (مثلاً زیر کارتن با 1 عدد)
            $isSingleValueTier = ($maxQty !== null && $maxQty === $minQty);
            
            // ✅ قانون جدید: max باید بزرگتر از min باشد (به جز استثناها)
            if (!$isInactiveTier && !$isSingleValueTier && $maxQty !== null && $maxQty <= $minQty) {
                throw ValidationException::withMessages([
                    "quantity_tiers.{$index}.max_quantity" => [
                        'حداکثر تعداد باید بزرگتر از حداقل تعداد باشد. ' .
                        "(حداقل: {$minQty}، حداکثر: {$maxQty})"
                    ]
                ]);
            }
        }

        // 3. بررسی عدم overlap و ترتیب صعودی + قانون min = max_قبلی + 1
        $sortedTiers = collect($tiers)
            ->filter(function($tier) {
                // ✅ تیراژهای غیرفعال (min=0, max=0) را از بررسی overlap حذف کن
                $min = (int)($tier['min_quantity'] ?? 0);
                $max = self::maxQuantity($tier);
                return !($min === 0 && $max === 0);
            })
            ->sortBy('min_quantity')
            ->toArray();

        if (count($sortedTiers) < 2) {
            throw ValidationException::withMessages([
                'quantity_tiers' => ['حداقل 2 تیراژ فعال برای تعریف بازه‌ها لازم است.']
            ]);
        }
        
        $sortedIndexes = array_keys($sortedTiers);

        for ($i = 0; $i < count($sortedIndexes) - 1; $i++) {
            $currentIndex = $sortedIndexes[$i];
            $nextIndex = $sortedIndexes[$i + 1];
            $current = $sortedTiers[$currentIndex];
            $next = $sortedTiers[$nextIndex];

            $currentMin = (int)$current['min_quantity'];
            $currentMax = self::maxQuantity($current);
            $nextMin = (int)$next['min_quantity'];

            // ✅ قانون جدید: min تیراژ بعدی باید دقیقاً max تیراژ فعلی + 1 باشد (به جز تیراژ اول)
            if ($currentMax === null) {
                throw ValidationException::withMessages([
                    "quantity_tiers.{$currentIndex}.max_quantity" => [
                        "حداکثر تعداد تیراژ «{$current['name']}» باید مشخص باشد؛ تنها تیراژ آخر می‌تواند بدون سقف باشد."
                    ]
                ]);
            }

            $expectedNextMin = $currentMax + 1;
                
            // استثنا: اگر تیراژ اول (زیر کارتن) است و min=1 دارد، قانون اعمال نمی‌شود
            $isFirstTierException = ($i === 0 && $currentMin === 1);
                
            if (!$isFirstTierException && $nextMin !== $expectedNextMin) {
                throw ValidationException::withMessages([
                    "quantity_tiers.{$nextIndex}.min_quantity" => [
                        "تیراژ «{$next['name']}» باید از عدد {$expectedNextMin} شروع شود " .
                        "(یک عدد بعد از حداکثر تیراژ قبلی: {$currentMax})."
                    ]
                ]);
            }
                
            // بررسی overlap (برای امنیت بیشتر)
            if ($nextMin <= $currentMax) {
                throw ValidationException::withMessages([
                    "quantity_tiers.{$nextIndex}.min_quantity" => [
                        "تیراژ «{$next['name']}» با تیراژ «{$current['name']}» هم‌پوشانی دارد. " .
                        "تیراژ بعدی باید از عدد " . ($currentMax + 1) . " شروع شود."
                    ]
                ]);
            }
                
            // ✅ بازدارنده: بررسی اینکه max تیراژ بعدی از min آن بزرگتر است
            $nextMax = self::maxQuantity($next);
                
            if ($nextMax !== null && $nextMax < $nextMin) {
                throw ValidationException::withMessages([
                    "quantity_tiers.{$nextIndex}.max_quantity" => [
                        "تیراژ «{$next['name']}» نامعتبر است: حداکثر تعداد ({$nextMax}) " .
                        "نمی‌تواند کمتر از حداقل تعداد ({$nextMin}) باشد."
                    ]
                ]);
            }
        }

        // 4. آخرین تیراژ باید open-ended باشد
        $lastTier = end($sortedTiers);
        $lastIndex = array_key_last($sortedTiers);
        if (is_array($lastTier) && self::maxQuantity($lastTier) !== null) {
            throw ValidationException::withMessages([
                "quantity_tiers.{$lastIndex}.max_quantity" => [
                    'آخرین تیراژ («' . $lastTier['name'] . '») باید بدون سقف باشد (حداکثر تعداد = null یا خالی).'
                ]
            ]);
        }

        return true;
    }

    /**
     * بررسی سریع تعداد تیراژها بدون exception
     * 
     * @param array $tiers
     * @return array ['valid' => bool, 'errors' => array]
     */
    public static function quickCheck(array $tiers): array
    {
        $errors = [];

        if (count($tiers) < 3) {
            $errors[] = 'حداقل 3 تیراژ الزامی است.';
        }

        if (count($tiers) > 4) {
            $errors[] = 'حداکثر 4 تیراژ مجاز است.';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * نرمال‌سازی تیراژها (اضافه کردن کلیدهای پیش‌فرض)
     * 
     * @param array $tiers
     * @return array
     */
    public static function normalize(array $tiers): array
    {
        return array_map(function ($tier, $index) {
            return [
                'name' => $tier['name'] ?? "تیراژ " . ($index + 1),
                'min_quantity' => isset($tier['min_quantity']) ? (int)$tier['min_quantity'] : 1,
                'max_quantity' => self::maxQuantity($tier),
                'description' => $tier['description'] ?? null,
            ];
        }, $tiers, array_keys($tiers));
    }

    /**
     * بررسی اینکه آیا یک محصول با تیراژهای موجود قابل ذخیره است
     * 
     * @param array|null $tiers
     * @return bool
     */
    public static function canSave(?array $tiers): bool
    {
        if (empty($tiers)) {
            return false;
        }

        try {
            self::validate($tiers);
            return true;
        } catch (ValidationException $e) {
            return false;
        }
    }

    private static function maxQuantity(array $tier): ?int
    {
        $max = $tier['max_quantity'] ?? null;

        if ($max === null || (is_string($max) && trim($max) === '')) {
            return null;
        }

        return (int) $max;
    }
}
