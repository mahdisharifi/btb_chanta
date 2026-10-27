<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AttributeValue;

class AttributeValueIdentity
{
    public static function normalize(string $value): string
    {
        $value = str_replace(['ي', 'ك', "\u{200C}"], ['ی', 'ک', ' '], $value);

        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $value) ?? $value));
    }

    public static function exists(int $attributeId, string $value): bool
    {
        $normalized = self::normalize($value);

        return AttributeValue::query()->where('attribute_id', $attributeId)->pluck('value')
            ->contains(static fn ($existing): bool => self::normalize((string) $existing) === $normalized);
    }
}
