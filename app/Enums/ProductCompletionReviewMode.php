<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductCompletionReviewMode: string
{
    case REVIEWER = 'reviewer';
    case SUPPLIER = 'supplier';
    case EITHER = 'either';

    public function label(): string
    {
        return match ($this) {
            self::REVIEWER => 'بازبین تعیین‌شده',
            self::SUPPLIER => 'تأمین‌کننده مالک محصول',
            self::EITHER => 'بازبین یا تأمین‌کننده',
        };
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
