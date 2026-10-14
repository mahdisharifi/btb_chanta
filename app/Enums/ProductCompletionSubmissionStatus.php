<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductCompletionSubmissionStatus: string
{
    case SUBMITTED = 'submitted';
    case UNDER_REVIEW = 'under_review';
    case WITHDRAWN = 'withdrawn';
    case CHANGES_REQUESTED = 'changes_requested';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case SUPERSEDED = 'superseded';
    case CONFLICTED = 'conflicted';

    public function label(): string
    {
        return match ($this) {
            self::SUBMITTED => 'ارسال‌شده',
            self::UNDER_REVIEW => 'در حال بررسی',
            self::WITHDRAWN => 'ارسال لغوشده توسط پیمانکار',
            self::CHANGES_REQUESTED => 'نیازمند اصلاح',
            self::APPROVED => 'تأییدشده',
            self::REJECTED => 'ردشده',
            self::SUPERSEDED => 'جایگزین‌شده با نسخه جدید',
            self::CONFLICTED => 'دارای تداخل',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [
            self::APPROVED,
            self::REJECTED,
            self::SUPERSEDED,
            self::WITHDRAWN,
        ], true);
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
