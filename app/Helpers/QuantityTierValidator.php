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
        // 1. بررسی تعداد تیراژها (3 تا 5)
        if (count($tiers) < 3) {
            throw ValidationException::withMessages([
                'quantity_tiers' => ['حداقل 3 تیراژ برای محصول الزامی است.']
            ]);
        }

        if (count($tiers) > 5) {
            throw ValidationException::withMessages([
                'quantity_tiers' => ['حداکثر 5 تیراژ می‌توانید تعریف کنید.']
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
            $maxQty = isset($tier['max_quantity']) && $tier['max_quantity'] !== null 
                ? (int)$tier['max_quantity'] 
                : null;

            // min باید مثبت باشد
            if ($minQty < 0) {
                throw ValidationException::withMessages([
                    "quantity_tiers.{$index}.min_quantity" => ['حداقل تعداد نمی‌تواند منفی باشد.']
                ]);
            }

            // max باید بزرگتر از min باشد (اگر مقداردهی شده)
            if ($maxQty !== null && $maxQty <= $minQty) {
                throw ValidationException::withMessages([
                    "quantity_tiers.{$index}.max_quantity" => [
                        'حداکثر تعداد باید بزرگتر از حداقل تعداد باشد.'
                    ]
                ]);
            }
        }

        // 3. بررسی عدم overlap و ترتیب صعودی
        $sortedTiers = collect($tiers)->sortBy('min_quantity')->values()->toArray();
        
        for ($i = 0; $i < count($sortedTiers) - 1; $i++) {
            $current = $sortedTiers[$i];
            $next = $sortedTiers[$i + 1];

            $currentMin = (int)$current['min_quantity'];
            $currentMax = isset($current['max_quantity']) && $current['max_quantity'] !== null 
                ? (int)$current['max_quantity'] 
                : null;
            $nextMin = (int)$next['min_quantity'];

            // اگر current max دارد، next باید بعد از آن شروع شود
            if ($currentMax !== null) {
                if ($nextMin <= $currentMax) {
                    throw ValidationException::withMessages([
                        'quantity_tiers' => [
                            "تیراژ «{$next['name']}» با تیراژ «{$current['name']}» هم‌پوشانی دارد. " .
                            "تیراژ بعدی باید از عدد " . ($currentMax + 1) . " شروع شود."
                        ]
                    ]);
                }
            }
        }

        // 4. آخرین تیراژ باید open-ended باشد
        $lastTier = end($sortedTiers);
        if (isset($lastTier['max_quantity']) && $lastTier['max_quantity'] !== null) {
            throw ValidationException::withMessages([
                'quantity_tiers' => [
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

        if (count($tiers) > 5) {
            $errors[] = 'حداکثر 5 تیراژ مجاز است.';
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
                'max_quantity' => isset($tier['max_quantity']) && $tier['max_quantity'] !== null 
                    ? (int)$tier['max_quantity'] 
                    : null,
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
}
