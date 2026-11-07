<?php

namespace App\Services;

use App\Models\OtpVerification;
use Carbon\Carbon;

class OtpService
{
    /** فاصله زمانی مجاز بین دو ارسال متوالی (ثانیه) */
    private const RESEND_COOLDOWN = 120;

    /** حداکثر تعداد ارسال موفق برای هر شماره در یک ساعت */
    private const MAX_SENDS_PER_HOUR = 10;

    /** حداکثر تعداد ارسال موفق برای هر شماره در یک شبانه‌روز */
    private const MAX_SENDS_PER_DAY = 20;

    /** حداکثر تعداد ارسال از یک IP در یک ساعت (ضد اسپم گسترده) */
    private const MAX_SENDS_PER_IP_HOUR = 30;

    /** حداکثر تعداد تلاش نادرست برای تأیید یک کد */
    private const MAX_VERIFY_ATTEMPTS = 5;

    /** مدت اعتبار کد (دقیقه) */
    private const OTP_TTL_MINUTES = 2;

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
            if ($elapsed < self::limits()['cooldown_seconds']) {
                $retryAfter = (int) ceil(self::limits()['cooldown_seconds'] - $elapsed);

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
            ->limit(self::MAX_SENDS_PER_HOUR)
            ->get(['created_at']);

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
            ->limit(self::MAX_SENDS_PER_DAY)
            ->get(['created_at']);

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
                ->limit(self::MAX_SENDS_PER_IP_HOUR)
                ->get(['created_at']);

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
            'expires_at' => $now->copy()->addSeconds(self::limits()['ttl_seconds']),
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
            'retry_after' => self::limits()['cooldown_seconds'],
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

    /** Read the verification page state in one query, without loading the secret code. */
    public function pageState(string $mobile): array
    {
        $last = OtpVerification::forMobile($mobile)->latest('created_at')->latest('id')
            ->first(['created_at', 'expires_at', 'is_used', 'attempts']);
        $now = now();
        $active = $last && !$last->is_used && $last->attempts < self::MAX_VERIFY_ATTEMPTS
            && $last->expires_at->greaterThan($now);

        return [
            'codeExpiresAt' => $active ? $last->expires_at->toIso8601String() : null,
            'retryAfter' => $last
                ? max(0, (int) ceil($now->diffInSeconds($last->created_at->copy()->addSeconds(self::limits()['cooldown_seconds']), false)))
                : 0,
        ];
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

        return $elapsed < self::limits()['cooldown_seconds']
            ? (int) ceil(self::limits()['cooldown_seconds'] - $elapsed)
            : null;
    }

    /** Seconds until a new code can be requested; zero means available now. */
    public function getSecondsUntilResend(string $mobile): int
    {
        return max(0, (int) ($this->getRetryAfter($mobile) ?? 0));
    }

    /** ISO timestamp for the active code's expiry, if one exists. */
    public function getCodeExpiresAt(string $mobile): ?string
    {
        $otp = OtpVerification::forMobile($mobile)
            ->valid()
            ->latest('created_at')
            ->first();

        return $otp?->expires_at?->toIso8601String();
    }

    /** Public description of the limits shown on the verification page. */
    public static function limits(): array
    {
        return [
            'ttl_seconds' => max(1, (int) config('otp.ttl_seconds', self::OTP_TTL_MINUTES * 60)),
            'cooldown_seconds' => max(1, (int) config('otp.cooldown_seconds', self::RESEND_COOLDOWN)),
            'per_hour' => self::MAX_SENDS_PER_HOUR,
            'per_day' => self::MAX_SENDS_PER_DAY,
            'wrong_attempts' => self::MAX_VERIFY_ATTEMPTS,
        ];
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
