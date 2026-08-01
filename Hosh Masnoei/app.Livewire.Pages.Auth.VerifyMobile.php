<?php

namespace App\Livewire\Pages\Auth;

use App\Enums\UserRole;
use App\Services\OtpService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.guest')]
class VerifyMobile extends Component
{
    public string $mobile = '';
    public string $code = '';
    public ?int $retryAfter = null;

    public function mount(OtpService $otpService)
    {
        $user = Auth::user();

        if (! $user->needsMobileVerification()) {
            return $this->redirectByRole($user);
        }

        $this->mobile = $user->mobile;
        $this->retryAfter = $otpService->getRetryAfter($user->mobile);

        // ارسال خودکار فقط وقتی کول‌داون فعال نیست و کد معتبری در انتظار نیست
        if ($this->retryAfter === null && ! $otpService->hasValidOtp($user->mobile)) {
            $result = $otpService->sendOtp($user->mobile, request()->ip());

            if (! ($result['success'] ?? false)) {
                session()->flash('error', $result['message']);
            }

            $this->retryAfter = $otpService->getRetryAfter($user->mobile);
        }
    }

    public function verify(OtpService $otpService)
    {
        $this->validate([
            'code' => ['required', 'string', 'size:6'],
        ], [
            'code.required' => 'کد تایید الزامی است',
            'code.size' => 'کد تایید باید 6 رقم باشد',
        ]);

        $user = Auth::user();

        if (! $user->needsMobileVerification()) {
            session()->flash('info', 'موبایل شما قبلاً تایید شده است');
            return $this->redirectByRole($user);
        }

        $result = $otpService->verifyOtp($user->mobile, $this->code);

        if (! ($result['success'] ?? false)) {
            throw ValidationException::withMessages([
                'code' => $result['message'],
            ]);
        }

        $user->markMobileAsVerified();

        session()->flash('success', 'موبایل شما با موفقیت تایید شد');
        return $this->redirectByRole($user);
    }

    public function resend(OtpService $otpService)
    {
        $user = Auth::user();

        if (! $user->needsMobileVerification()) {
            session()->flash('error', 'موبایل شما قبلاً تایید شده است');
            return;
        }

        $result = $otpService->sendOtp($user->mobile, request()->ip());

        $this->retryAfter = $otpService->getRetryAfter($user->mobile);
        $this->code = '';

        session()->flash(
            ($result['success'] ?? false) ? 'success' : 'error',
            $result['message']
        );
    }

    protected function redirectByRole($user)
    {
        if ($user->hasRole(UserRole::SUPER_ADMIN)) {
            return $this->redirectRoute('admin.dashboard', navigate: true);
        }

        if ($user->hasRole(UserRole::SUPPLIER)) {
            return $this->redirectRoute('supplier.dashboard', navigate: true);
        }

        if ($user->hasRole([UserRole::RETAILER_PREMIUM, UserRole::RETAILER_BASIC])) {
            return $this->redirectRoute('retailer.dashboard', navigate: true);
        }

        return $this->redirectRoute('home', navigate: true);
    }

    public function render()
    {
        return view('livewire.pages.auth.verify-mobile');
    }
}
