<?php

namespace App\Services;

use App\Models\OtpVerification;
use Carbon\Carbon;

class OtpService
{
    /** فاصله زمانی مجاز بین دو ارسال متوالی (ثانیه) */
    private const RESEND_COOLDOWN = 5;

    /** حداکثر تعداد ارسال موفق برای هر شماره در یک ساعت */
    private const MAX_SENDS_PER_HOUR = 5;

    /** حداکثر تعداد ارسال موفق برای هر شماره در یک شبانه‌روز */
    private const MAX_SENDS_PER_DAY = 15;

    /** حداکثر تعداد ارسال از یک IP در یک ساعت (ضد اسپم گسترده) */
    private const MAX_SENDS_PER_IP_HOUR = 15;

    /** حداکثر تعداد تلاش نادرست برای تأیید یک کد */
    private const MAX_VERIFY_ATTEMPTS = 5;

    /** مدت اعتبار کد (دقیقه) */
    private const OTP_TTL_MINUTES = 5;

    public function __construct(
        protected KavenegarService $kavenegarService
    ) {}

    /**
     * ارسال کد یکبارمصرف با اعمال محدودیت‌های چندلایه (کاملاً دیتابیس‌محور).
     */
    public function sendOtp(string $mobile, ?string $ip = null): array
    {
        $now = now();

        // لایه ۱: فاصله بین دو ارسال (cooldown)
        $last = OtpVerification::forMobile($mobile)
            ->latest('created_at')
            ->first();

        if ($last) {
            $elapsed = $last->created_at->diffInSeconds($now);
            if ($elapsed < self::RESEND_COOLDOWN) {
                $retryAfter = self::RESEND_COOLDOWN - $elapsed;

                return [
                    'success'     => false,
                    'message'     => "لطفاً {$retryAfter} ثانیه دیگر تلاش کنید",
                    'retry_after' => $retryAfter,
                ];
            }
        }

        // لایه ۲: سقف ساعتی هر شماره
        $hourWindow = $now->copy()->subHour();
        $hourSends  = OtpVerification::forMobile($mobile)
            ->where('created_at', '>=', $hourWindow)
            ->orderBy('created_at')
            ->get();

        if ($hourSends->count() >= self::MAX_SENDS_PER_HOUR) {
            $retryAfter = $this->retryAfterFromOldest($hourSends->first(), 3600, $now);

            return [
                'success'     => false,
                'blocked'     => true,
                'message'     => 'تعداد درخواست‌های شما در یک ساعت اخیر بیش از حد مجاز است. لطفاً کمی بعد دوباره تلاش کنید',
                'retry_after' => $retryAfter,
            ];
        }

        // لایه ۳: سقف روزانه هر شماره (مسدودسازی موقت)
        $dayWindow = $now->copy()->subDay();
        $daySends  = OtpVerification::forMobile($mobile)
            ->where('created_at', '>=', $dayWindow)
            ->orderBy('created_at')
            ->get();

        if ($daySends->count() >= self::MAX_SENDS_PER_DAY) {
            $retryAfter = $this->retryAfterFromOldest($daySends->first(), 86400, $now);

            return [
                'success'     => false,
                'blocked'     => true,
                'message'     => 'به دلیل تعداد زیاد درخواست، ارسال کد برای این شماره موقتاً مسدود شده است. لطفاً بعداً تلاش کنید',
                'retry_after' => $retryAfter,
            ];
        }

        // لایه ۴: سقف ساعتی هر IP (ضد اسپم با شماره‌های مختلف)
        if ($ip) {
            $ipSends = OtpVerification::where('ip_address', $ip)
                ->where('created_at', '>=', $hourWindow)
                ->orderBy('created_at')
                ->get();

            if ($ipSends->count() >= self::MAX_SENDS_PER_IP_HOUR) {
                $retryAfter = $this->retryAfterFromOldest($ipSends->first(), 3600, $now);

                return [
                    'success'     => false,
                    'blocked'     => true,
                    'message'     => 'تعداد درخواست‌ها از این دستگاه بیش از حد مجاز است. لطفاً بعداً تلاش کنید',
                    'retry_after' => $retryAfter,
                ];
            }
        }

        // باطل کردن کدهای فعال قبلی این شماره
        OtpVerification::forMobile($mobile)->valid()->update(['is_used' => true]);

        // تولید و ذخیره کد جدید
        $code = OtpVerification::generateCode();

        $otp = OtpVerification::create([
            'mobile'     => $mobile,
            'code'       => $code,
            'expires_at' => $now->copy()->addMinutes(self::OTP_TTL_MINUTES),
            'ip_address' => $ip,
        ]);

        // ارسال پیامک
        $sent = $this->kavenegarService->sendOtp($mobile, $code);

        if (! $sent) {
            // حذف رکورد تا سهمیه‌ی محدودسازی با خطای کاوه‌نگار مصرف نشود
            $otp->delete();

            return [
                'success' => false,
                'message' => 'خطا در ارسال پیامک. لطفاً دوباره تلاش کنید',
            ];
        }

        return [
            'success'     => true,
            'message'     => 'کد تایید ارسال شد',
            'retry_after' => self::RESEND_COOLDOWN,
        ];
    }

    /**
     * بررسی کد یکبارمصرف با محافظت دیتابیس‌محور در برابر brute-force.
     */
    public function verifyOtp(string $mobile, string $code): array
    {
        $otp = OtpVerification::forMobile($mobile)
            ->valid()
            ->latest('created_at')
            ->first();

        if (! $otp) {
            return [
                'success' => false,
                'message' => 'کد تایید منقضی شده یا یافت نشد. لطفاً کد جدید درخواست کنید',
            ];
        }

        // محافظت brute-force: تلاش‌های نادرست بیش از حد مجاز
        if ($otp->attempts >= self::MAX_VERIFY_ATTEMPTS) {
            $otp->update(['is_used' => true]);

            return [
                'success' => false,
                'blocked' => true,
                'message' => 'تعداد تلاش‌های نادرست بیش از حد مجاز است. لطفاً کد جدید درخواست کنید',
            ];
        }

        // کد نادرست
        if (! hash_equals($otp->code, $code)) {
            $otp->increment('attempts');
            $remaining = max(0, self::MAX_VERIFY_ATTEMPTS - $otp->attempts);

            // اگر با این تلاش به سقف رسید، کد را باطل کن
            if ($remaining === 0) {
                $otp->update(['is_used' => true]);

                return [
                    'success' => false,
                    'blocked' => true,
                    'message' => 'تعداد تلاش‌های نادرست بیش از حد مجاز است. لطفاً کد جدید درخواست کنید',
                ];
            }

            return [
                'success'            => false,
                'message'            => 'کد تایید نامعتبر است',
                'remaining_attempts' => $remaining,
            ];
        }

        // تأیید موفق
        $otp->markAsUsed();

        return [
            'success' => true,
            'message' => 'شماره موبایل با موفقیت تایید شد',
        ];
    }

    /**
     * آیا کد معتبر و فعالی برای این شماره وجود دارد؟
     */
    public function hasValidOtp(string $mobile): bool
    {
        return OtpVerification::forMobile($mobile)->valid()->exists();
    }

    /**
     * زمان باقی‌مانده تا امکان ارسال مجدد (ثانیه) یا null.
     */
    public function getRetryAfter(string $mobile): ?int
    {
        $last = OtpVerification::forMobile($mobile)
            ->latest('created_at')
            ->first();

        if (! $last) {
            return null;
        }

        $elapsed = $last->created_at->diffInSeconds(now());

        return $elapsed < self::RESEND_COOLDOWN
            ? self::RESEND_COOLDOWN - $elapsed
            : null;
    }

    /**
     * محاسبه زمان آزاد شدن سهمیه بر اساس قدیمی‌ترین رکورد داخل پنجره.
     */
    private function retryAfterFromOldest(?OtpVerification $oldest, int $windowSeconds, Carbon $now): int
    {
        if (! $oldest) {
            return $windowSeconds;
        }

        $freeAt = $oldest->created_at->copy()->addSeconds($windowSeconds);

        return max(1, $now->diffInSeconds($freeAt, false));
    }
}
