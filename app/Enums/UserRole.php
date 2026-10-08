<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    case SUPER_ADMIN = 'super_admin';
    case ADMIN = 'admin';
    case SUPPLIER = 'supplier';
    case REGULAR_SUPPLIER = 'regular_supplier';
    case OUTSOURCER = 'outsourcer';
    case REVIEWER = 'reviewer';
    case FINANCE_MANAGER = 'finance_manager';
    case RETAILER_PREMIUM = 'retailer_premium';
    case RETAILER_BASIC = 'retailer_basic';
    case END_USER = 'end_user';

    public function label(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'مدیر ارشد',
            self::ADMIN => 'مدیر',
            self::SUPPLIER => 'تأمین‌کننده',
            self::REGULAR_SUPPLIER => 'تأمین‌کننده عادی',
            self::OUTSOURCER => 'پیمانکار محتوا',
            self::REVIEWER => 'بازبین محتوا',
            self::FINANCE_MANAGER => 'مدیر مالی',
            self::RETAILER_PREMIUM => 'کاسب ویژه',
            self::RETAILER_BASIC => 'کاسب عادی',
            self::END_USER => 'کاربر عادی',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'red',
            self::ADMIN => 'rose',
            self::SUPPLIER => 'blue',
            self::REGULAR_SUPPLIER => 'slate',
            self::OUTSOURCER => 'indigo',
            self::REVIEWER => 'purple',
            self::FINANCE_MANAGER => 'emerald',
            self::RETAILER_PREMIUM => 'amber',
            self::RETAILER_BASIC => 'green',
            self::END_USER => 'gray',
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
