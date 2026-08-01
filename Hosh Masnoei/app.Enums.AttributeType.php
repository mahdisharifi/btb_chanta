<?php

namespace App\Enums;

enum AttributeType: string
{
    case TEXT = 'text';
    case NUMBER = 'number';
    case BOOLEAN = 'boolean';

    /**
     * دریافت نام فارسی برای نمایش در فرم‌ها و جداول
     */
    public function label(): string
    {
        return match($this) {
            self::TEXT => 'متن (Text)',
            self::NUMBER => 'عدد (Number)',
            self::BOOLEAN => 'بله/خیر (Boolean)',
        };
    }
}
