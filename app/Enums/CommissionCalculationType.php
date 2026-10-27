<?php

declare(strict_types=1);

namespace App\Enums;

enum CommissionCalculationType: string
{
    case PER_CONTENT_TYPE = 'per_content_type';
    case PER_TASK = 'per_task';
    case PER_PRODUCT = 'per_product';
    case PER_APPROVED_SUBMISSION = 'per_approved_submission';
    case PER_REVISION = 'per_revision';
    case PER_FIELD_GROUP = 'per_field_group';

    public function label(): string
    {
        return match ($this) {
            self::PER_CONTENT_TYPE => 'به‌ازای نوع محتوا',
            self::PER_TASK => 'به‌ازای هر تسک',
            self::PER_PRODUCT => 'به‌ازای هر محصول',
            self::PER_APPROVED_SUBMISSION => 'به‌ازای ارسال تأییدشده',
            self::PER_REVISION => 'به‌ازای اصلاح قابل پرداخت',
            self::PER_FIELD_GROUP => 'به‌ازای گروه فیلد',
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
