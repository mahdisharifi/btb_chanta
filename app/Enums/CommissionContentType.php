<?php

declare(strict_types=1);

namespace App\Enums;

enum CommissionContentType: string
{
    case IMAGE = 'image';
    case VIDEO = 'video';
    case ATTRIBUTE = 'attribute';
    case DESCRIPTION = 'description';

    public function label(): string
    {
        return match ($this) {
            self::IMAGE => 'تصویر',
            self::VIDEO => 'ویدئو',
            self::ATTRIBUTE => 'مشخصه',
            self::DESCRIPTION => 'توضیحات کوتاه و بلند',
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
