<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductCompletionRevisionPolicy: string
{
    case NONE = 'none';
    case ALL_RESOLVED = 'all_resolved';
    case SCOPE_CHANGE_ONLY = 'scope_change_only';

    public function label(): string
    {
        return match ($this) {
            self::NONE => 'بدون پورسانت اصلاح',
            self::ALL_RESOLVED => 'تمام اصلاحات رفع‌شده',
            self::SCOPE_CHANGE_ONLY => 'فقط تغییر خارج از درخواست اولیه',
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
