<?php

declare(strict_types=1);

namespace App\Enums;

enum PriceType: string
{
    case SUB_CARTON = 'sub_carton';
    case CARTON = 'carton';
    case WARRANTY = 'warranty';
    case CHECK_1_MONTH = 'check_1_month';
    case CHECK_2_MONTH = 'check_2_month';
    case CHECK_3_MONTH = 'check_3_month';
    case CUSTOM = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::SUB_CARTON     => 'زیر کارتن',
            self::CARTON         => 'کارتن',
            self::WARRANTY       => 'گارانتی‌دار',
            self::CHECK_1_MONTH  => 'چکی ۱ ماهه',
            self::CHECK_2_MONTH  => 'چکی ۲ ماهه',
            self::CHECK_3_MONTH  => 'چکی ۳ ماهه',
            self::CUSTOM         => 'سفارشی',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::SUB_CARTON     => 'emerald',
            self::CARTON         => 'blue',
            self::WARRANTY       => 'purple',
            self::CHECK_1_MONTH  => 'amber',
            self::CHECK_2_MONTH  => 'orange',
            self::CHECK_3_MONTH  => 'red',
            self::CUSTOM         => 'gray',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::SUB_CARTON     => 'cube',
            self::CARTON         => 'archive-box',
            self::WARRANTY       => 'shield-check',
            self::CHECK_1_MONTH,
            self::CHECK_2_MONTH,
            self::CHECK_3_MONTH  => 'document-check',
            self::CUSTOM         => 'adjustments-horizontal',
        };
    }

    public function isCredit(): bool
    {
        return match ($this) {
            self::CHECK_1_MONTH,
            self::CHECK_2_MONTH,
            self::CHECK_3_MONTH => true,
            default             => false,
        };
    }

    public function creditMonths(): ?int
    {
        return match ($this) {
            self::CHECK_1_MONTH => 1,
            self::CHECK_2_MONTH => 2,
            self::CHECK_3_MONTH => 3,
            default             => null,
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
