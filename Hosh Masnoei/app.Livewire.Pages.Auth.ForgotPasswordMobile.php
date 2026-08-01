<?php

namespace App\Livewire\Pages\Auth;
use Illuminate\View\View;

use App\Models\User;
use App\Services\OtpService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.guest')]
class ForgotPasswordMobile extends Component
{
    public string $mobile = '';
    public string $code = '';
    public string $password = '';
    public string $password_confirmation = '';
    public string $step = 'mobile'; // mobile | verify | reset
    public ?int $retryAfter = null;

    public function sendCode(OtpService $otpService): void
    {
        $this->validate([
            'mobile' => ['required', 'regex:/^09\d{9}$/'],
        ], [
            'mobile.required' => 'شماره موبایل الزامی است',
            'mobile.regex'    => 'فرمت موبایل باید 09xxxxxxxxx باشد',
        ]);

        if (! User::where('mobile', $this->mobile)->exists()) {
            throw ValidationException::withMessages([
                'mobile' => 'این شماره موبایل در سیستم ثبت نشده است',
            ]);
        }

        $result = $otpService->sendOtp($this->mobile, request()->ip());

        if (! ($result['success'] ?? false)) {
            throw ValidationException::withMessages([
                'mobile' => $result['message'],
            ]);
        }

        $this->retryAfter = $otpService->getRetryAfter($this->mobile);
        $this->step = 'verify';
    }

    public function resend(OtpService $otpService): void
    {
        $result = $otpService->sendOtp($this->mobile, request()->ip());

        $this->retryAfter = $otpService->getRetryAfter($this->mobile);
        $this->code = '';

        session()->flash(
            ($result['success'] ?? false) ? 'success' : 'error',
            $result['message']
        );
    }

    public function verifyCode(OtpService $otpService): void
    {
        $this->validate([
            'code' => ['required', 'string', 'size:6'],
        ], [
            'code.required' => 'کد تایید الزامی است',
            'code.size'     => 'کد تایید باید ۶ رقم باشد',
        ]);

        $result = $otpService->verifyOtp($this->mobile, $this->code);

        if (! ($result['success'] ?? false)) {
            throw ValidationException::withMessages([
                'code' => $result['message'],
            ]);
        }

        $this->step = 'reset';
    }

    public function resetPassword(): void
    {
        $this->validate([
            'password' => ['required', 'min:8', 'confirmed'],
        ], [
            'password.required'  => 'رمز عبور الزامی است',
            'password.min'       => 'رمز عبور باید حداقل ۸ کاراکتر باشد',
            'password.confirmed' => 'تکرار رمز عبور مطابقت ندارد',
        ]);

        $user = User::where('mobile', $this->mobile)->firstOrFail();
        $user->forceFill(['password' => Hash::make($this->password)])->save();

        session()->flash('success', 'رمز عبور با موفقیت تغییر یافت');

        $this->redirectRoute('login', navigate: true);
    }

    public function render(): View
    {
        $titles = [
            'mobile' => 'بازیابی رمز عبور',
            'verify' => 'تایید شماره موبایل',
            'reset'  => 'تعیین رمز عبور جدید',
        ];

        return view('livewire.pages.auth.forgot-password-mobile')
            ->title(($titles[$this->step] ?? 'بازیابی رمز عبور') . ' | ' . config('app.name'));
    }
}
