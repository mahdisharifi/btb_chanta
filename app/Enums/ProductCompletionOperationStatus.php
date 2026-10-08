<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductCompletionOperationStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case FAILED = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'در انتظار اجرا',
            self::PROCESSING => 'در حال اجرا',
            self::COMPLETED => 'تکمیل‌شده',
            self::FAILED => 'ناموفق',
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
