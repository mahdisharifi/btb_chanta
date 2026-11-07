<?php

// Standalone smoke test for environments without PHPUnit. No SMS is sent;
// all test records are rolled back.
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$gateway = new class extends App\Services\KavenegarService {
    public int $sent = 0;
    public function __construct() {}
    public function sendOtp(string $mobile, string $code): bool
    {
        $this->sent++;
        return true;
    }
};
$service = new App\Services\OtpService($gateway);
$check = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

Illuminate\Support\Facades\DB::beginTransaction();
try {
    foreach ([20, 120] as $duration) {
        config(['otp.ttl_seconds' => $duration, 'otp.cooldown_seconds' => $duration]);
        $start = Carbon\Carbon::parse('2030-01-01 12:00:00')->addDays($duration);
        Carbon\Carbon::setTestNow($start);
        $mobile = '09000000000';
        $check($service->sendOtp($mobile)['success'], 'Initial send failed');
        $check($service->getSecondsUntilResend($mobile) === $duration, 'Wrong initial cooldown');
        $otp = App\Models\OtpVerification::forMobile($mobile)->latest('id')->firstOrFail();
        $check($otp->expires_at->equalTo($start->copy()->addSeconds($duration)), 'Wrong expiry');

        Carbon\Carbon::setTestNow($start->copy()->addSeconds($duration - 1)->addMilliseconds(500));
        $before = $gateway->sent;
        $check(!$service->sendOtp($mobile)['success'], 'Early resend accepted');
        $check($service->getSecondsUntilResend($mobile) === 1, 'Fractional cooldown rounded down');
        $check($gateway->sent === $before, 'Blocked resend reached gateway');
        $check($service->hasValidOtp($mobile), 'Code expired too early');

        Carbon\Carbon::setTestNow($start->copy()->addSeconds($duration));
        $check(!$service->verifyOtp($mobile, $otp->code)['success'], 'Expired code accepted');
        $check($service->getSecondsUntilResend($mobile) === 0, 'Cooldown did not end');
        $check($service->sendOtp($mobile)['success'], 'Resend at deadline failed');
        $check($service->getSecondsUntilResend($mobile) === $duration, 'Resend did not restart cooldown');
        $otp = App\Models\OtpVerification::forMobile($mobile)->latest('id')->firstOrFail();
        $check($service->verifyOtp($mobile, $otp->code)['success'], 'Fresh code rejected');
        $check(!$service->verifyOtp($mobile, $otp->code)['success'], 'Used code accepted');
        echo "PASS: {$duration}s expiry, resend boundary, timer reset, single-use verification\n";
    }
} finally {
    Illuminate\Support\Facades\DB::rollBack();
    Carbon\Carbon::setTestNow();
}
