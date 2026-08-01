<?php

namespace App\Enums;

enum UserRole: string
{
    case SUPER_ADMIN = 'super_admin';
    case SUPPLIER = 'supplier';
    case REGULAR_SUPPLIER = 'regular_supplier';  // جدید ⭐

    case RETAILER_PREMIUM = 'retailer_premium';
    case RETAILER_BASIC = 'retailer_basic';
    case END_USER = 'end_user';

    /**
     * Get human-readable label (Persian).
     */
    public function label(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'مدیر ارشد',
            self::SUPPLIER => 'تأمین‌کننده',
            self::REGULAR_SUPPLIER => 'تأمین‌کننده عادی',  // جدید ⭐

            self::RETAILER_PREMIUM => 'کاسب ویژه',
            self::RETAILER_BASIC => 'کاسب عادی',
            self::END_USER => 'کاربر عادی',
        };
    }

    /**
     * Get badge color for UI.
     */
    public function color(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'red',
            self::SUPPLIER => 'blue',
            self::REGULAR_SUPPLIER => 'slate',  // جدید ⭐

            self::RETAILER_PREMIUM => 'amber',
            self::RETAILER_BASIC => 'green',
            self::END_USER => 'gray',
        };
    }

    /**
     * Get all values as array.
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get all as key => label array (for dropdowns).
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }
        return $options;
    }
}
