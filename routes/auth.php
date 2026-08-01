<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Support\Facades\Password;
use App\Services\OtpService;

/*
|--------------------------------------------------------------------------
| Helper: ریدایرکت بر اساس نقش کاربر
|--------------------------------------------------------------------------
| جایگزین بلوک‌های تکراری role-based redirect در سراسر این فایل.
*/
if (! function_exists('redirectByRole')) {
    function redirectByRole(User $user)
    {
        if ($user->hasRole(UserRole::SUPER_ADMIN)) {
            return redirect()->route('admin.dashboard');
        }

        if ($user->hasRole(UserRole::SUPPLIER)) {
            return redirect()->route('supplier.dashboard');
        }

        if ($user->hasRole([UserRole::RETAILER_PREMIUM, UserRole::RETAILER_BASIC])) {
            return redirect()->route('retailer.dashboard');
        }
        if ($user->hasRole(UserRole::END_USER)) {
            return redirect()->route('user.role-selection');
        }
        return redirect()->route('home');
    }
}

Route::middleware('guest')->group(function () {
    Route::get('/forgot-password-mobile', \App\Livewire\Pages\Auth\ForgotPasswordMobile::class)
        ->name('password.mobile');
    // نمایش فرم ورود
    Route::get('/login', fn() => view('auth.login'))->name('login');

    // پردازش ورود
    Route::post('/login', function (Request $request) {
        $validated = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required'],
        ]);

        // تشخیص نوع ورودی (موبایل یا ایمیل)
        $loginField = filter_var($validated['username'], FILTER_VALIDATE_EMAIL)
            ? 'email'
            : 'mobile';

        // اگر موبایل است، فرمت آن را چک کن
        if ($loginField === 'mobile') {
            if (!preg_match('/^09\d{9}$/', $validated['username'])) {
                throw ValidationException::withMessages([
                    'username' => 'فرمت موبایل باید 09xxxxxxxxx باشد.',
                ]);
            }
        }

        $credentials = [
            $loginField => $validated['username'],
            'password' => $validated['password'],
        ];

        if (!Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'username' => __('auth.failed'),
            ]);
        }

        $request->session()->regenerate();

        $user = Auth::user();

        // 🔥 چک mobile verification برای کاربران غیر END_USER
        if ($user->needsMobileVerification()) {
            $otpService = app(OtpService::class);

            // اگر کد معتبر فعالی از قبل وجود دارد، پیامک جدید نفرست (صرفه‌جویی هزینه)
            if ($otpService->hasValidOtp($user->mobile)) {
                return redirect()->route('verify.mobile')
                    ->with('info', 'کد تأیید قبلی هنوز معتبر است. لطفاً همان کد را وارد کنید.');
            }

            $result = $otpService->sendOtp($user->mobile, $request->ip());

            return redirect()->route('verify.mobile')
                ->with($result['success'] ? 'success' : 'error', $result['message']);
        }

        // Role-based redirect
        return redirectByRole($user);
    })->name('login.attempt');

    // نمایش فرم ثبت نام
    Route::get('/register', fn() => view('auth.register'))->name('register');

    // پردازش ثبت نام
    Route::post('/register', function () {
        $validated = request()->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255', 'unique:'.User::class],
            'mobile' => [ 'string', 'regex:/^09\d{9}$/', 'unique:'.User::class],
            'password' => ['required', 'confirmed', 'min:8'],
        ], [
            'mobile.regex' => 'فرمت موبایل باید 09xxxxxxxxx باشد.',
            'mobile.unique' => 'این شماره موبایل قبلاً ثبت شده است.',
            'password.min' => 'رمز عبور باید حداقل 8 کاراکتر باشد.',
        ]);

        $validated['password'] = Hash::make($validated['password']);

        $user = User::create($validated);

        $user->assignRole(UserRole::END_USER);

        event(new Registered($user));

        Auth::login($user);

        // کاربر جدید باید ابتدا موبایل را تأیید کند، سپس نقش انتخاب کند
        if ($user->needsMobileVerification()) {
            app(OtpService::class)->sendOtp($user->mobile);
            return redirect()->route('verify.mobile');
        }

        return redirect()->route('user.role-selection');
    })->name('register.store');

    // نمایش فرم فراموشی رمز عبور
    Route::get('/forgot-password', function () {
        return view('auth.forgot-password');
    })->name('password.request');
    // ارسال لینک بازیابی
    Route::post('/forgot-password', function (Request $request) {
        $request->validate([
            'email' => ['required', 'email'],
        ], [
            'email.required' => 'ایمیل الزامی است.',
            'email.email' => 'فرمت ایمیل نامعتبر است.',
        ]);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        return $status === Password::RESET_LINK_SENT
            ? back()->with('status', __($status))
            : back()->withErrors(['email' => __($status)]);
    })->name('password.email');

    // نمایش فرم تنظیم رمز جدید
    Route::get('/reset-password/{token}', function (string $token) {
        return view('auth.reset-password', ['token' => $token]);
    })->name('password.reset');

    // ذخیره رمز جدید
    Route::post('/reset-password', function (Request $request) {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'min:8', 'confirmed'],
        ], [
            'email.required' => 'ایمیل الزامی است.',
            'email.email' => 'فرمت ایمیل نامعتبر است.',
            'password.required' => 'رمز عبور الزامی است.',
            'password.min' => 'رمز عبور باید حداقل 8 کاراکتر باشد.',
            'password.confirmed' => 'تکرار رمز عبور مطابقت ندارد.',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password)
                ])->setRememberToken(Str::random(60));

                $user->save();

                event(new PasswordReset($user));
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', __($status))
            : back()->withErrors(['email' => [__($status)]]);
    })->name('password.update');
});

/*
|--------------------------------------------------------------------------
| Mobile Verification Routes (Protected)
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {


    // صفحه تایید موبایل
    Route::get('/verify-mobile', function () {

        // اگر نیازی به تایید ندارد، redirect به مقصد مناسب
        if (!Auth::user()->needsMobileVerification()) {
            return redirectByRole(Auth::user());
        }

        return view('livewire.pages.auth.verify-mobile', [
            'mobile' => Auth::user()->mobile,
        ]);
    })->name('verify.mobile');

    // ارسال مجدد کد OTP
    Route::post('/verify-mobile/resend', function (Request $request, OtpService $otpService) {
        $user = Auth::user();

        if (!$user->needsMobileVerification()) {
            return back()->with('error', 'موبایل شما قبلاً تایید شده است');
        }

        $result = $otpService->sendOtp($user->mobile, $request->ip());

        return back()->with(
            $result['success'] ? 'success' : 'error',
            $result['message']
        );
    })->name('verify.mobile.resend');

    // تایید کد OTP
    Route::post('/verify-mobile/verify', function (Request $request, OtpService $otpService) {
        $validated = $request->validate([
            'code' => 'required|string|size:6',
        ], [
            'code.required' => 'کد تایید الزامی است',
            'code.size' => 'کد تایید باید 6 رقم باشد',
        ]);

        $user = Auth::user();

        if (!$user->needsMobileVerification()) {
            return redirectByRole($user)->with('info', 'موبایل شما قبلاً تایید شده است');
        }

        $result = $otpService->verifyOtp($user->mobile, $validated['code']);

        if (!$result['success']) {
            return back()
                ->withErrors(['code' => $result['message']])
                ->withInput();
        }

        // علامت‌گذاری موبایل به عنوان تایید شده
        $user->markMobileAsVerified();

        // redirect بر اساس نقش
        return redirectByRole($user)->with('success', 'موبایل شما با موفقیت تایید شد');

    })->name('verify.mobile.verify');

});

/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/

// مسیر خروج از سیستم
Route::middleware('auth')->post('/logout', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    return redirect('/');
})->name('logout');

// مسیرهای مربوط به تأیید ایمیل
Route::middleware('auth')->group(function () {
    // نمایش صفحه نیاز به تأیید ایمیل
    Route::get('/email/verify', function () {
        return view('auth.verify-email');
    })->name('verification.notice');

    // ارسال مجدد لینک تأیید ایمیل
    Route::post('/email/verification-notification', function (Request $request) {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('dashboard');
        }
        $request->user()->sendEmailVerificationNotification();
        return back()->with('message', 'Verification link sent!');
    })->middleware(['throttle:6,1'])->name('verification.send');
});

// مسیر پروفایل
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/profile', function () {
        return redirect()->route('dashboard');
    })->name('profile');
});
// در routes/auth.php بعد از خط 107 اضافه کنید:


// اضافه کردن مسیرهای جدید
Route::middleware(['auth', 'verified'])->group(function () {

    Route::get('/account/pending', \App\Livewire\Account\Pending::class)->name('account.pending');
    Route::get('/account/rejected', \App\Livewire\Account\Rejected::class)->name('account.rejected');
    Route::get('/account/edit', \App\Livewire\User\EditProfile::class)->name('account.edit');
    Route::get('/account/under-review', \App\Livewire\Account\UnderReview::class)->name('account.under-review');

    Route::get('/profile/edit', \App\Livewire\User\RegisterSupplier::class)
        ->name('supplier.profile.edit')
        ->defaults('isEditMode', true);

    Route::get('/retailer/profile/edit', \App\Livewire\User\RegisterRetailer::class)
        ->name('retailer.profile.edit')
        ->defaults('isEditMode', true);

    Route::get('/register/role-selection', \App\Livewire\User\RoleSelection::class)
        ->name('user.role-selection');

    // 🔥 تکمیل اطلاعات تامین‌کننده
    Route::get('/register/supplier/complete', \App\Livewire\User\RegisterSupplier::class)
        ->name('user.register.supplier.complete');

    // 🔥 تکمیل اطلاعات خرده‌فروش
    Route::get('/register/retailer/complete', \App\Livewire\User\RegisterRetailer::class)
        ->name('user.register.retailer.complete');


});



