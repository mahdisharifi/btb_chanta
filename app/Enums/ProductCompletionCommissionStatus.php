<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductCompletionCommissionStatus: string
{
    case PENDING = 'pending';
    case CALCULATED = 'calculated';
    case UNDER_REVIEW = 'under_review';
    case APPROVED = 'approved';
    case PAYABLE = 'payable';
    case PAID = 'paid';
    case CANCELLED = 'cancelled';
    case DISPUTED = 'disputed';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'در انتظار محاسبه',
            self::CALCULATED => 'محاسبه‌شده',
            self::UNDER_REVIEW => 'در حال بررسی مالی',
            self::APPROVED => 'تأییدشده',
            self::PAYABLE => 'قابل پرداخت',
            self::PAID => 'پرداخت‌شده',
            self::CANCELLED => 'لغوشده',
            self::DISPUTED => 'مورد اختلاف',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::PAID, self::CANCELLED], true);
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
