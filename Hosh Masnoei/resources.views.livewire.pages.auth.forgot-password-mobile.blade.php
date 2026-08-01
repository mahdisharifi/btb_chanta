<div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">

        {{-- لوگو --}}
        <div class="text-center">
            <h2 class="mt-6 text-3xl font-bold text-gray-900">
                @if($step === 'mobile') بازیابی رمز عبور
                @elseif($step === 'verify') تایید شماره موبایل
                @else تعیین رمز عبور جدید
                @endif
            </h2>
            <p class="mt-2 text-sm text-gray-600">
                @if($step === 'mobile')
                شماره موبایل خود را وارد کنید
                @elseif($step === 'verify')
                کد تایید به شماره <strong class="font-bold">{{ $mobile }}</strong> ارسال شد
                @else
                رمز عبور جدید خود را وارد کنید
                @endif
            </p>
        </div>

        {{-- نمایش پیام‌ها --}}
        @if (session('success'))
        <div class="rounded-md bg-green-50 p-4 border border-green-200">
            <div class="flex">
                <x-heroicon-s-check-circle class="h-5 w-5 text-green-400" />
                <div class="mr-3">
                    <p class="text-sm font-medium text-green-800">{{ session('success') }}</p>
                </div>
            </div>
        </div>
        @endif

        @if (session('error'))
        <div class="rounded-md bg-red-50 p-4 border border-red-200">
            <div class="flex">
                <x-heroicon-s-x-circle class="h-5 w-5 text-red-400" />
                <div class="mr-3">
                    <p class="text-sm font-medium text-red-800">{{ session('error') }}</p>
                </div>
            </div>
        </div>
        @endif

        {{-- مرحله: شماره موبایل --}}
        @if($step === 'mobile')
        <form wire:submit="sendCode" class="mt-8 space-y-6">
            <div>
                <label for="mobile" class="block text-sm font-medium text-gray-700">
                    شماره موبایل
                </label>
                <div class="mt-1">
                    <input
                        id="mobile"
                        wire:model="mobile"
                        type="tel"
                        inputmode="numeric"
                        required
                        autofocus
                        class="appearance-none block w-full px-3 py-3 border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 text-center text-lg font-mono @error('mobile') border-red-500 @enderror"
                        placeholder="09xxxxxxxxx"
                    >
                </div>
                @error('mobile')
                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <button
                    type="submit"
                    class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors"
                >
                        <span class="absolute right-0 inset-y-0 flex items-center pr-3">
                            <x-heroicon-s-device-phone-mobile class="h-5 w-5 text-indigo-500 group-hover:text-indigo-400" />
                        </span>
                    ارسال کد تایید
                </button>
            </div>
        </form>

        {{-- مرحله: تایید کد --}}
        @elseif($step === 'verify')
        <form wire:submit="verifyCode" class="mt-8 space-y-6">
            <div>
                <label for="code" class="block text-sm font-medium text-gray-700">
                    کد تایید 6 رقمی
                </label>
                <div class="mt-1">
                    <input
                        id="code"
                        wire:model="code"
                        type="text"
                        inputmode="numeric"
                        maxlength="6"
                        pattern="\d{6}"
                        required
                        autofocus
                        class="appearance-none block w-full px-3 py-3 border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 text-center text-2xl tracking-widest font-mono @error('code') border-red-500 @enderror"
                        placeholder="------"
                    >
                </div>
                @error('code')
                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <button
                    type="submit"
                    class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors"
                >
                        <span class="absolute right-0 inset-y-0 flex items-center pr-3">
                            <x-heroicon-s-lock-closed class="h-5 w-5 text-indigo-500 group-hover:text-indigo-400" />
                        </span>
                    تایید کد
                </button>
            </div>
        </form>

        <div class="text-center">
            @if($retryAfter)
            <span class="text-sm text-gray-500">ارسال مجدد پس از {{ $retryAfter }} ثانیه</span>
            @else
            <button
                type="button"
                wire:click="resend"
                class="font-medium text-indigo-600 hover:text-indigo-500 transition-colors"
            >
                ارسال مجدد کد
            </button>
            @endif
        </div>

        {{-- مرحله: تعیین رمز جدید --}}
        @else
        <form wire:submit="resetPassword" class="mt-8 space-y-6">
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700">
                    رمز عبور جدید
                </label>
                <div class="mt-1">
                    <input
                        id="password"
                        wire:model="password"
                        type="password"
                        required
                        autofocus
                        class="appearance-none block w-full px-3 py-3 border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 @error('password') border-red-500 @enderror"
                    >
                </div>
                @error('password')
                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-gray-700">
                    تکرار رمز عبور
                </label>
                <div class="mt-1">
                    <input
                        id="password_confirmation"
                        wire:model="password_confirmation"
                        type="password"
                        required
                        class="appearance-none block w-full px-3 py-3 border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                    >
                </div>
            </div>

            <div>
                <button
                    type="submit"
                    class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors"
                >
                        <span class="absolute right-0 inset-y-0 flex items-center pr-3">
                            <x-heroicon-s-key class="h-5 w-5 text-indigo-500 group-hover:text-indigo-400" />
                        </span>
                    ذخیره رمز عبور
                </button>
            </div>
        </form>
        @endif

        {{-- بازگشت به ورود --}}
        <div class="text-center">
            <a href="{{ route('login') }}" wire:navigate class="font-medium text-gray-600 hover:text-gray-500 transition-colors">
                بازگشت به ورود
            </a>
        </div>

        {{-- راهنما --}}
        @if($step === 'verify')
        <div class="mt-6 p-4 bg-gray-100 rounded-lg">
            <div class="flex">
                <x-heroicon-o-information-circle class="h-5 w-5 text-gray-400 ml-2" />
                <div class="text-sm text-gray-600">
                    <p>کد تایید به شماره موبایل شما پیامک شد.</p>
                    <p class="mt-1">در صورت دریافت نکردن، از قسمت "ارسال مجدد" استفاده کنید.</p>
                </div>
            </div>
        </div>
        @endif

    </div>
    @script
    <script>
        document.addEventListener('input', function (e) {
            if (e.target && e.target.id === 'code') {
                e.target.value = e.target.value.replace(/[^0-9]/g, '');

                if (e.target.value.length === 6) {
                    $wire.verifyCode();
                }
            }
        });
    </script>
    @endscript

</div>
