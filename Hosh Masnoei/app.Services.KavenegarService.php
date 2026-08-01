<?php

namespace App\Services;

use Kavenegar\KavenegarApi;
use Kavenegar\Exceptions\ApiException;
use Kavenegar\Exceptions\HttpException;
use Illuminate\Support\Facades\Log;

class KavenegarService
{
    protected KavenegarApi $api;

    public function __construct()
    {
        $apiKey = config('services.kavenegar.api_key');

        if (empty($apiKey)) {
            throw new \Exception('Kavenegar API Key is not set in config');
        }

        $this->api = new KavenegarApi($apiKey);
    }

    /**
     * ارسال کد OTP
     *
     * ابتدا تلاش می‌کند از الگوی VerifyLookup استفاده کند (نیازمند سرویس پیشرفته).
     * در صورت عدم دسترسی به الگو، به‌صورت خودکار با متد Send (پیامک ساده) ارسال می‌کند.
     */
    public function sendOtp(string $mobile, string $code): bool
    {
        // تلاش اول: استفاده از الگوی VerifyLookup
        if ($this->trySendOtpViaLookup($mobile, $code)) {
            return true;
        }

        // تلاش دوم (fallback): ارسال پیامک ساده با خط خدماتی
        return $this->trySendOtpViaSend($mobile, $code);
    }

    /**
     * ارسال OTP از طریق الگوی VerifyLookup
     */
    protected function trySendOtpViaLookup(string $mobile, string $code): bool
    {
        try {
            $template = config('services.kavenegar.otp_template', 'verify');

            $result = $this->api->VerifyLookup(
                $mobile,
                $code,
                null,
                null,
                $template
            );

            Log::info('OTP sent successfully via Kavenegar (VerifyLookup)', [
                'mobile' => $mobile,
                'messageid' => $result[0]->messageid ?? null
            ]);

            return true;

        } catch (ApiException $e) {
            // خطای دسترسی به الگو (پلن پایه) -> اجازه fallback
            Log::warning('Kavenegar VerifyLookup failed, will fallback to Send', [
                'mobile' => $mobile,
                'error' => $e->errorMessage(),
                'code' => $e->getCode()
            ]);
            return false;

        } catch (HttpException $e) {
            Log::warning('Kavenegar VerifyLookup HTTP error, will fallback to Send', [
                'mobile' => $mobile,
                'error' => $e->errorMessage(),
                'code' => $e->getCode()
            ]);
            return false;

        } catch (\Exception $e) {
            Log::warning('Kavenegar VerifyLookup general error, will fallback to Send', [
                'mobile' => $mobile,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * ارسال OTP به‌صورت پیامک ساده (fallback)
     */
    protected function trySendOtpViaSend(string $mobile, string $code): bool
    {
        $message = "کد تایید شما: {$code}";

        $sent = $this->sendSms($mobile, $message);

        if ($sent) {
            Log::info('OTP sent successfully via Kavenegar (Send fallback)', [
                'mobile' => $mobile,
            ]);
        } else {
            Log::error('OTP delivery failed on both VerifyLookup and Send', [
                'mobile' => $mobile,
            ]);
        }

        return $sent;
    }

    /**
     * ارسال پیامک ساده
     */
    public function sendSms(string $mobile, string $message, string $sender = null): bool
    {
        try {
            $sender = $sender ?? config('services.kavenegar.sender', '2000660110');

            $result = $this->api->Send(
                $sender,
                $mobile,
                $message
            );

            Log::info('SMS sent successfully via Kavenegar', [
                'mobile' => $mobile,
                'messageid' => $result[0]->messageid ?? null
            ]);

            return true;

        } catch (ApiException $e) {
            Log::error('Kavenegar SMS API Exception', [
                'mobile' => $mobile,
                'sender' => $sender,
                'error' => $e->errorMessage(),
                'code' => $e->getCode()
            ]);
            return false;

        } catch (HttpException $e) {
            Log::error('Kavenegar SMS HTTP Exception', [
                'mobile' => $mobile,
                'sender' => $sender,
                'error' => $e->errorMessage()
            ]);
            return false;

        } catch (\Exception $e) {
            Log::error('Kavenegar SMS General Exception', [
                'mobile' => $mobile,
                'sender' => $sender,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * چک کردن وضعیت پیام
     */
    public function getStatus(string $messageId): ?object
    {
        try {
            $result = $this->api->Status($messageId);
            return $result[0] ?? null;

        } catch (\Exception $e) {
            Log::error('Kavenegar Status Check Failed', [
                'messageid' => $messageId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}
