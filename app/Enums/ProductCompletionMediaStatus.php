<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductCompletionMediaStatus: string
{
    case TEMPORARY = 'temporary';
    case UNDER_REVIEW = 'under_review';
    case ACCEPTED = 'accepted';
    case PUBLISHED = 'published';
    case REJECTED = 'rejected';
    case DELETED = 'deleted';

    public function label(): string
    {
        return match ($this) {
            self::TEMPORARY => 'موقت',
            self::UNDER_REVIEW => 'در حال بررسی',
            self::ACCEPTED => 'پذیرفته‌شده',
            self::PUBLISHED => 'منتشرشده',
            self::REJECTED => 'ردشده',
            self::DELETED => 'حذف‌شده',
        };
    }

    public function isMutable(): bool
    {
        return $this === self::TEMPORARY;
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
