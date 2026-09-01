<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Config;
use InvalidArgumentException;

/**
 * کلاس کمکی برای کدگذاری و رمزگشایی امن شناسه‌ها
 * 
 * این کلاس از HMAC-SHA256 برای امنیت و Base64 برای کوتاه‌سازی استفاده می‌کند.
 * طول کد تولیدی: 8-12 کاراکتر (برای IDهای معمولی)
 */
class SecureIdEncoder
{
    /**
     * کدگذاری یک شناسه به صورت امن
     * 
     * @param int|string $id شناسه برای کدگذاری
     * @return string کد کدگذاری شده
     */
    public static function encode(int|string $id): string
    {
        $id = (string)$id;
        
        // ایجاد HMAC برای امنیت
        $signature = hash_hmac('sha256', $id, self::getKey());
        
        // استفاده از 8 کاراکتر اول signature (کافی برای امنیت)
        $shortSignature = substr($signature, 0, 8);
        
        // ترکیب ID + signature
        $payload = $id . '.' . $shortSignature;
        
        // Base64 encode و حذف کاراکترهای اضافی
        $encoded = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
        
        return $encoded;
    }

    /**
     * رمزگشایی یک کد به شناسه اصلی
     * 
     * @param string $encoded کد کدگذاری شده
     * @return int شناسه اصلی
     * @throws InvalidArgumentException اگر کد نامعتبر باشد
     */
    public static function decode(string $encoded): int
    {
        try {
            // بازگرداندن کاراکترهای Base64
            $encoded = strtr($encoded, '-_', '+/');
            
            // اضافه کردن padding در صورت نیاز
            $padding = strlen($encoded) % 4;
            if ($padding) {
                $encoded .= str_repeat('=', 4 - $padding);
            }
            
            // Base64 decode
            $payload = base64_decode($encoded, true);
            
            if ($payload === false) {
                throw new InvalidArgumentException('کد نامعتبر است (base64 decode failed).');
            }
            
            // جداسازی ID و signature
            $parts = explode('.', $payload, 2);
            
            if (count($parts) !== 2) {
                throw new InvalidArgumentException('فرمت کد نامعتبر است.');
            }
            
            [$id, $providedSignature] = $parts;
            
            // بررسی صحت signature
            $expectedSignature = substr(hash_hmac('sha256', $id, self::getKey()), 0, 8);
            
            if (!hash_equals($expectedSignature, $providedSignature)) {
                throw new InvalidArgumentException('کد دستکاری شده یا نامعتبر است (signature mismatch).');
            }
            
            // بررسی اینکه ID یک عدد معتبر است
            if (!ctype_digit($id)) {
                throw new InvalidArgumentException('شناسه باید یک عدد باشد.');
            }
            
            return (int)$id;
            
        } catch (\Exception $e) {
            throw new InvalidArgumentException('خطا در رمزگشایی کد: ' . $e->getMessage());
        }
    }

    /**
     * بررسی اینکه آیا یک کد معتبر است یا خیر
     * 
     * @param string $encoded کد برای بررسی
     * @return bool
     */
    public static function isValid(string $encoded): bool
    {
        try {
            self::decode($encoded);
            return true;
        } catch (InvalidArgumentException $e) {
            return false;
        }
    }

    /**
     * دریافت کلید رمزنگاری از تنظیمات
     * 
     * @return string
     */
    private static function getKey(): string
    {
        $key = Config::get('app.key');
        
        if (empty($key)) {
            throw new \RuntimeException('APP_KEY تنظیم نشده است.');
        }
        
        // حذف پیشوند base64: اگر وجود داشت
        if (str_starts_with($key, 'base64:')) {
            $key = base64_decode(substr($key, 7));
        }
        
        return $key;
    }

    /**
     * رمزگشایی batch یک آرایه از کدها
     * 
     * @param array $encodedIds آرایه کدهای کدگذاری شده
     * @return array آرایه شناسه‌های رمزگشایی شده
     */
    public static function decodeBatch(array $encodedIds): array
    {
        $decoded = [];
        $errors = [];
        
        foreach ($encodedIds as $index => $encoded) {
            try {
                $decoded[$index] = self::decode($encoded);
            } catch (InvalidArgumentException $e) {
                $errors[$index] = $e->getMessage();
            }
        }
        
        return [
            'success' => $decoded,
            'errors' => $errors,
        ];
    }
}
