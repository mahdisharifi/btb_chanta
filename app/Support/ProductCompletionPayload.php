<?php

declare(strict_types=1);

namespace App\Support;

final class ProductCompletionPayload
{
    public static function withoutExcludedFields(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if (self::isExcludedField((string) $key)) {
                unset($payload[$key]);
            } elseif (is_array($value)) {
                $payload[$key] = self::withoutExcludedFields($value);
            }
        }

        return $payload;
    }

    public static function isExcludedField(string $field): bool
    {
        return $field === 'specifications' || str_starts_with($field, 'specifications.')
            || $field === 'seo' || str_starts_with($field, 'seo.')
            || str_starts_with($field, 'seo_')
            || in_array($field, ['is_seo_image', 'select_seo'], true);
    }
}
