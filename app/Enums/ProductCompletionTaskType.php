<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductCompletionTaskType: string
{
    case CONTENT = 'content';
    case ATTRIBUTES = 'attributes';
    case MEDIA = 'media';
    case SEO = 'seo';
    case FULL_COMPLETION = 'full_completion';
    case CORRECTION = 'correction';

    public function label(): string
    {
        return match ($this) {
            self::CONTENT => 'تکمیل محتوا',
            self::ATTRIBUTES => 'تکمیل مشخصات فنی',
            self::MEDIA => 'تکمیل تصاویر',
            self::SEO => 'تکمیل سئو',
            self::FULL_COMPLETION => 'تکمیل کامل محصول',
            self::CORRECTION => 'اصلاح محتوای محصول',
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
