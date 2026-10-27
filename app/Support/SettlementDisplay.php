<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Validation\ValidationException;
use Morilog\Jalali\CalendarUtils;
use Morilog\Jalali\Jalalian;

final class SettlementDisplay
{
    public static function latin(string $value): string
    {
        return strtr(trim($value), array_combine(
            preg_split('//u', '۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩', -1, PREG_SPLIT_NO_EMPTY),
            str_split('01234567890123456789'),
        ));
    }

    public static function gregorian(string $value, string $field): string
    {
        $value = self::latin($value);
        if (! preg_match('/^(1[34]\d{2})\/(\d{2})\/(\d{2})$/', $value, $parts)
            || ! CalendarUtils::checkDate((int) $parts[1], (int) $parts[2], (int) $parts[3])) {
            throw ValidationException::withMessages([$field => 'تاریخ شمسی معتبر با قالب ۱۴۰۵/۰۷/۰۱ وارد کنید.']);
        }

        return Jalalian::fromFormat('Y/m/d', $value, new \DateTimeZone('Asia/Tehran'))->toCarbon()->format('Y-m-d');
    }

    public static function money(string|int|null $amount): string
    {
        [$whole, $fraction] = array_pad(explode('.', (string) ($amount ?? '0'), 2), 2, '00');

        return preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $whole)
            . ($fraction === '00' ? '' : '.'.$fraction);
    }
}
