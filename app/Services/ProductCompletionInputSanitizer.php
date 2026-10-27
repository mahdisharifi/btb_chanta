<?php

declare(strict_types=1);

namespace App\Services;

class ProductCompletionInputSanitizer
{
    public function plainText(?string $value, int $maxLength, bool $preserveLines = false): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = str_replace(['ي', 'ى', 'ك'], ['ی', 'ی', 'ک'], $value);
        $value = strip_tags($value);
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';

        if ($preserveLines) {
            $value = preg_replace('/[\t ]+/u', ' ', $value) ?? '';
            $value = preg_replace('/\R{3,}/u', "\n\n", $value) ?? '';
        } else {
            $value = preg_replace('/\s+/u', ' ', $value) ?? '';
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, $maxLength);
    }

    public function specifications(array $specifications): array
    {
        $result = [];
        $keyMax = (int) config('product_completion.validation.specification_key_max', 100);
        $valueMax = (int) config('product_completion.validation.specification_value_max', 2000);
        $maxItems = (int) config('product_completion.validation.specifications_max_items', 100);

        foreach (array_slice($specifications, 0, $maxItems, true) as $key => $value) {
            if (! is_scalar($value) && $value !== null) {
                continue;
            }

            $cleanKey = $this->plainText((string) $key, $keyMax);
            $cleanValue = $this->plainText($value === null ? null : (string) $value, $valueMax, true);

            if ($cleanKey !== null && $cleanValue !== null) {
                $result[$cleanKey] = $cleanValue;
            }
        }

        ksort($result);

        return $result;
    }

    public function keywords(?string $keywords): ?string
    {
        $keywords = $this->plainText(
            $keywords,
            (int) config('product_completion.validation.seo_keywords_max', 255)
        );

        if ($keywords === null) {
            return null;
        }

        $items = preg_split('/[،,;؛]+/u', $keywords, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $items = array_values(array_unique(array_filter(array_map(
            fn (string $item): ?string => $this->plainText($item, 80),
            $items
        ))));

        return $items === [] ? null : implode('، ', $items);
    }
}
