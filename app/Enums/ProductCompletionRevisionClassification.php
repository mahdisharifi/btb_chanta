<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductCompletionRevisionClassification: string
{
    case CONTRACTOR_ERROR = 'contractor_error';
    case QUALITY_IMPROVEMENT = 'quality_improvement';
    case SUPPLIER_SCOPE_CHANGE = 'supplier_scope_change';
    case REVIEWER_CLARIFICATION = 'reviewer_clarification';
    case SYSTEM_CONFLICT = 'system_conflict';

    public function label(): string
    {
        return match ($this) {
            self::CONTRACTOR_ERROR => 'رفع نقص پیمانکار',
            self::QUALITY_IMPROVEMENT => 'بهبود کیفیت',
            self::SUPPLIER_SCOPE_CHANGE => 'تغییر درخواست تأمین‌کننده',
            self::REVIEWER_CLARIFICATION => 'شفاف‌سازی بازبین',
            self::SYSTEM_CONFLICT => 'رفع تداخل سیستمی',
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
