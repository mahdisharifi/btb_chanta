<?php

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$app->instance('env', 'testing');
config(['session.driver' => 'array']);
$gateway = new class extends App\Services\KavenegarService {
    public int $sent = 0;
    public function __construct() {}
    public function sendOtp(string $mobile, string $code): bool
    {
        $this->sent++;
        return true;
    }
};
$app->instance(App\Services\KavenegarService::class, $gateway);
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$check = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$request = static function (string $uri, string $method = 'GET', array $data = []) use ($kernel) {
    return $kernel->handle(Illuminate\Http\Request::create($uri, $method, $data));
};
$redirect = static function ($response, string $route) use ($check): void {
    $check($response->isRedirect(route($route)), 'Expected '.$route.', got '.$response->getStatusCode().' '.$response->headers->get('Location'));
};
$brand = static function ($response) use ($check): void {
    $check($response->getStatusCode() === 200, 'Registration stage failed to render');
    $check(str_contains($response->getContent(), 'src="'.asset('favicon.jpg').'"'), 'Registration logo differs from favicon');
};

Illuminate\Support\Facades\DB::beginTransaction();
try {
    $brand($request('/register'));
    $mobile = '09'.random_int(100000000, 999999999);
    foreach ([['Ab1!', 'Ab1!'], ['abcdefgh1!', 'abcdefgh1!'], ['ABCDEFGH1!', 'ABCDEFGH1!'], ['Abcdefgh!', 'Abcdefgh!'], ['Abcdefgh1', 'Abcdefgh1'], ['Abcdefgh1!', 'Different1!']] as [$weak, $confirmation]) {
        $invalid = $kernel->handle(Illuminate\Http\Request::create('/register', 'POST', [
            'name' => 'Password rule smoke', 'mobile' => $mobile,
            'password' => $weak, 'password_confirmation' => $confirmation,
        ], [], [], ['HTTP_ACCEPT' => 'application/json']));
        $invalidData = json_decode($invalid->getContent(), true);
        $check($invalid->getStatusCode() === 422 && isset($invalidData['errors']['password']), 'Registration accepted an invalid password');
        $check(!App\Models\User::where('mobile', $mobile)->exists(), 'Invalid password created an account');
    }
    $redirect($request('/register', 'POST', [
        'name' => 'Registration flow smoke', 'mobile' => $mobile,
        'password' => 'Test-Password-123', 'password_confirmation' => 'Test-Password-123',
    ]), 'verify.mobile');
    $user = Illuminate\Support\Facades\Auth::user();
    $check($user && $user->needsMobileVerification(), 'New user bypassed OTP');
    $redirect($request('/register/role-selection'), 'verify.mobile');
    $response = $request('/verify-mobile');
    $brand($response);
    $check($response->getStatusCode() === 200 && $gateway->sent === 1, 'OTP was not sent exactly once');
    $otp = App\Models\OtpVerification::forMobile($mobile)->latest('id')->firstOrFail();
    $earlyResend = $kernel->handle(Illuminate\Http\Request::create('/verify-mobile/resend', 'POST', [], [], [], ['HTTP_ACCEPT' => 'application/json']));
    $earlyData = json_decode($earlyResend->getContent(), true);
    $check($earlyResend->getStatusCode() === 422 && !$earlyResend->isRedirect() && $earlyData['retryAfter'] > 0 && $gateway->sent === 1, 'Early resend did not return cooldown without sending SMS');
    Carbon\Carbon::setTestNow(now()->addSeconds(121));
    $resend = $kernel->handle(Illuminate\Http\Request::create('/verify-mobile/resend', 'POST', [], [], [], ['HTTP_ACCEPT' => 'application/json']));
    $resendData = json_decode($resend->getContent(), true);
    $check($resend->getStatusCode() === 200 && !$resend->isRedirect() && $resendData['success'] && $resendData['codeExpiresAt'] && $resendData['retryAfter'] === 120 && $gateway->sent === 2, 'Resend did not provide fresh inline timer state');
    $otp = App\Models\OtpVerification::forMobile($mobile)->latest('id')->firstOrFail();
    $wrong = $kernel->handle(Illuminate\Http\Request::create('/verify-mobile/verify', 'POST', ['code' => '000000'], [], [], ['HTTP_ACCEPT' => 'application/json']));
    $wrongData = json_decode($wrong->getContent(), true);
    $check($wrong->getStatusCode() === 422 && !$wrong->isRedirect() && $wrongData['can_retry'] === true, 'Wrong code did not return an inline retry response');
    $check($otp->fresh()->attempts === 1 && !$user->fresh()->hasMobileVerified(), 'Wrong code verification state incorrect');
    $correct = $kernel->handle(Illuminate\Http\Request::create('/verify-mobile/verify', 'POST', ['code' => $otp->code], [], [], ['HTTP_ACCEPT' => 'application/json']));
    $correctData = json_decode($correct->getContent(), true);
    $check($correct->getStatusCode() === 200 && $correctData['success'] && $correctData['redirect'] === route('user.role-selection'), 'Correct code did not provide the next page');
    $check($user->fresh()->hasMobileVerified(), 'Mobile verification was not saved');
    $brand($request('/register/role-selection'));
    $component = new App\Livewire\User\RoleSelection();
    $redirect($component->selectSupplier(), 'user.register.supplier.complete');
    $redirect($component->selectRetailer(), 'user.register.retailer.complete');
    $check(!$user->fresh()->supplier, 'Role selection created an incomplete business profile');
    $redirect($request('/account/pending'), 'user.role-selection');
    $brand($request('/register/supplier/complete'));
    $brand($request('/register/retailer/complete'));
    $profile = new App\Livewire\User\RegisterSupplier();
    $profile->mount();
    $city = App\Models\City::whereNotNull('province_id')->firstOrFail();
    $profile->company_name = 'Registration smoke '.bin2hex(random_bytes(6));
    $profile->address = 'Smoke test address';
    $profile->province_id = $city->province_id;
    $profile->city_id = $city->id;
    $redirect($profile->register(), 'account.pending');
    $supplier = $user->fresh()->supplier;
    $check($supplier && $supplier->verification_status === 'pending' && !$supplier->is_active, 'Business request not pending');
    Illuminate\Support\Facades\Auth::setUser($user->fresh());
    $redirect($component->selectSupplier(), 'account.pending');
    $check(App\Models\Supplier::where('user_id', $user->id)->count() === 1, 'Repeated selection created duplicate');
    $brand($request('/account/pending'));
    $redirect($request('/register/role-selection'), 'account.pending');
    echo "PASS: registration -> OTP -> role selection -> profile completion -> pending; OTP gate and repeated selection\n";
} finally {
    Illuminate\Support\Facades\DB::rollBack();
    Carbon\Carbon::setTestNow();
    Illuminate\Support\Facades\Auth::forgetGuards();
}
