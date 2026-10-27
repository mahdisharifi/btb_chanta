<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductCompletionRevisionStatus: string
{
    case OPEN = 'open';
    case RESOLVED = 'resolved';
    case CANCELLED = 'cancelled';
    case WAIVED = 'waived';

    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'باز',
            self::RESOLVED => 'رفع‌شده',
            self::CANCELLED => 'لغوشده',
            self::WAIVED => 'صرف‌نظرشده',
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
