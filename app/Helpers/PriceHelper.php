<?php

namespace App\Helpers;

class PriceHelper
{
    /**
     * تبدیل ریال به تومان
     */
    public static function toToman(int|float|null $Amount): int|float
    {
        if ($Amount === null || $Amount == 0) {
            return 0;
        }
        return $Amount ;
    }

    /**
     * فرمت عدد ریالی به تومان با جداکننده هزارگان (بدون واحد)
     */
    public static function formatNumber(int|float|null $Amount): string
    {
        if ($Amount === null || $Amount == 0) {
            return '0';
        }
        return number_format(self::toToman($Amount));
    }

    /**
     * فرمت عدد ریالی به تومان + واحد «تومان»
     */
    public static function format(int|float|null $Amount): string
    {
        if ($Amount === null || $Amount == 0) {
            return '0 تومان';
        }
        return number_format(self::toToman($Amount)) . ' تومان';
    }

    /**
     * فرمت بازه قیمت (ریال → تومان)
     */
    public static function formatRange(?int $minRial, ?int $maxRial): string
    {
        if (!$minRial && !$maxRial) {
            return 'قیمت ثبت نشده';
        }

        $min = self::formatNumber($minRial);
        $max = self::formatNumber($maxRial);

        if ($minRial === $maxRial || !$maxRial) {
            return $min . ' تومان';
        }

        return $min . ' ~ ' . $max . ' تومان';
    }
}
