{{-- resources/views/livewire/user/register-retailer.blade.php --}}
<div class="min-h-screen bg-gray-50 py-12" dir="rtl">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">

        {{-- Header --}}
        <div class="text-center mb-10">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-emerald-100 rounded-full mb-4">
                <x-heroicon-o-building-storefront class="w-8 h-8 text-emerald-600" />
            </div>
            <h1 class="text-3xl font-bold text-gray-900">
                {{ $editMode ? 'ویرایش اطلاعات کسب‌وکار' : 'ثبت‌نام به عنوان کاسب' }}
            </h1>
            <p class="mt-2 text-gray-600">
                {{ $editMode ? 'اطلاعات کسب‌وکار خود را به‌روزرسانی کنید' : 'اطلاعات کسب‌وکار خود را تکمیل کنید' }}
            </p>
        </div>

        {{-- Step Indicator (فقط در حالت ثبت‌نام) --}}
        @unless($editMode)
        <div class="flex items-center justify-center mb-10">
            <div class="flex items-center space-x-4 space-x-reverse">
                <div class="flex items-center">
                    <div class="w-8 h-8 bg-emerald-600 rounded-full flex items-center justify-center">
                        <x-heroicon-s-check class="w-4 h-4 text-white" />
                    </div>
                    <span class="mr-2 text-sm text-emerald-600 font-medium">ثبت حساب</span>
                </div>
                <div class="w-16 h-0.5 bg-emerald-300"></div>
                <div class="flex items-center">
                    <div class="w-8 h-8 bg-emerald-600 rounded-full flex items-center justify-center">
                        <x-heroicon-s-check class="w-4 h-4 text-white" />
                    </div>
                    <span class="mr-2 text-sm text-emerald-600 font-medium">تأیید موبایل</span>
                </div>
                <div class="w-16 h-0.5 bg-emerald-300"></div>
                <div class="flex items-center">
                    <div class="w-8 h-8 bg-emerald-600 rounded-full flex items-center justify-center">
                        <span class="text-white text-sm font-bold">۳</span>
                    </div>
                    <span class="mr-2 text-sm text-emerald-600 font-bold">اطلاعات کسب‌وکار</span>
                </div>
            </div>
        </div>
        @endunless

        {{-- نوار وضعیت در حالت ویرایش --}}
        @if($editMode && $supplier)
        <div @class([
        'mb-8 rounded-xl border p-4 flex items-start gap-3',
        'bg-green-50 border-green-200' => $supplier->verification_status === 'approved',
        'bg-amber-50 border-amber-200' => $supplier->verification_status === 'under_review',
        'bg-red-50 border-red-200'     => $supplier->verification_status === 'rejected',
        'bg-blue-50 border-blue-200'   => ! in_array($supplier->verification_status, ['approved', 'under_review', 'rejected'], true),
        ])>
        <x-heroicon-o-information-circle @class([
        'w-5 h-5 mt-0.5 shrink-0',
        'text-green-600' => $supplier->verification_status === 'approved',
        'text-amber-600' => $supplier->verification_status === 'under_review',
        'text-red-600'   => $supplier->verification_status === 'rejected',
        'text-blue-600'  => ! in_array($supplier->verification_status, ['approved', 'under_review', 'rejected'], true),
        ]) />
        <p @class([
        'text-sm',
        'text-green-800' => $supplier->verification_status === 'approved',
        'text-amber-800' => $supplier->verification_status === 'under_review',
        'text-red-800'   => $supplier->verification_status === 'rejected',
        'text-blue-800'  => ! in_array($supplier->verification_status, ['approved', 'under_review', 'rejected'], true),
        ])>
        @switch($supplier->verification_status)
        @case('approved')
        پروفایل شما تأیید شده است. توجه داشته باشید با ویرایش اطلاعات، پروفایل مجدداً برای بررسی ارسال می‌شود.
        @break
        @case('under_review')
        پروفایل شما در حال بررسی است.
        @break
        @case('rejected')
        پروفایل شما رد شده است. لطفاً اطلاعات را اصلاح و مجدداً ارسال کنید.
        @break
        @default
        پروفایل شما در انتظار بررسی است.
        @endswitch
        </p>
    </div>
    @endif


    {{-- Alerts --}}
    @if (session('message'))
    <div class="mb-6 rounded-lg bg-green-50 border border-green-200 p-4 flex items-start gap-3">
        <x-heroicon-o-check-circle class="w-5 h-5 text-green-500 mt-0.5 shrink-0" />
        <p class="text-green-800 text-sm">{{ session('message') }}</p>
    </div>
    @endif

    @if (session('error'))
    <div class="mb-6 rounded-lg bg-red-50 border border-red-200 p-4 flex items-start gap-3">
        <x-heroicon-o-x-circle class="w-5 h-5 text-red-500 mt-0.5 shrink-0" />
        <p class="text-red-800 text-sm">{{ session('error') }}</p>
    </div>
    @endif

    <form wire:submit="register" class="space-y-8">

        {{-- Section 1: Business Info --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="bg-gray-50 border-b border-gray-100 px-6 py-4 flex items-center gap-3">
                <x-heroicon-o-building-office-2 class="w-5 h-5 text-emerald-600" />
                <h2 class="font-semibold text-gray-800">اطلاعات کسب‌وکار</h2>
            </div>

            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">

                {{-- company_name --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        نام شرکت / برند
                        <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="text"
                        wire:model="company_name"
                        placeholder="مثال: فروشگاه نمونه"
                        class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition @error('company_name') border-red-400 bg-red-50 @enderror"
                    />
                    @error('company_name')
                    <p class="mt-1 text-xs text-red-600 flex items-center gap-1">
                        <x-heroicon-s-exclamation-circle class="w-3.5 h-3.5" />
                        {{ $message }}
                    </p>
                    @enderror
                </div>

                {{-- company_name_en --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        نام انگلیسی (اختیاری)
                    </label>
                    <input
                        type="text"
                        wire:model="company_name_en"
                        placeholder="Example Store"
                        dir="ltr"
                        class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition"
                    />
                </div>

                {{-- brand_name --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        نام برند (اختیاری)
                    </label>
                    <input
                        type="text"
                        wire:model="brand_name"
                        placeholder="نام برند تجاری"
                        class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition"
                    />
                </div>

                {{-- logo --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        لوگو / تصویر کسب‌وکار (اختیاری)
                    </label>
                    <div class="flex items-center gap-4">
                        @if ($logo)
                        <img src="{{ $logo->temporaryUrl() }}" class="w-14 h-14 rounded-lg object-cover border border-gray-200" />
                        @elseif ($editMode && $existingLogo)
                        <img src="{{ Storage::url($existingLogo) }}" class="w-14 h-14 rounded-lg object-cover border border-gray-200" />
                        @else
                        <div class="w-14 h-14 rounded-lg bg-gray-100 flex items-center justify-center border border-dashed border-gray-300">
                            <x-heroicon-o-photo class="w-6 h-6 text-gray-400" />
                        </div>
                        @endif
                        <label class="cursor-pointer flex items-center gap-2 text-sm text-emerald-600 hover:text-emerald-700 font-medium">
                            <x-heroicon-o-arrow-up-tray class="w-4 h-4" />
                            {{ $editMode && $existingLogo ? 'تغییر تصویر' : 'آپلود تصویر' }}
                            <input type="file" wire:model="logo" accept="image/*" class="hidden" />
                        </label>
                    </div>
                    <p class="mt-1 text-xs text-gray-500">حداکثر ۲ مگابایت — JPG, PNG, WEBP</p>
                    @error('logo')
                    <p class="mt-1 text-xs text-red-600 flex items-center gap-1">
                        <x-heroicon-s-exclamation-circle class="w-3.5 h-3.5" />
                        {{ $message }}
                    </p>
                    @enderror
                </div>

                {{-- description --}}
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        معرفی کسب‌وکار (اختیاری)
                    </label>
                    <textarea
                        wire:model="description"
                        rows="3"
                        placeholder="توضیح مختصری درباره فعالیت کسب‌وکار خود بنویسید..."
                        class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition resize-none"
                    ></textarea>
                </div>

            </div>
        </div>

        {{-- Section 2: Contact --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="bg-gray-50 border-b border-gray-100 px-6 py-4 flex items-center gap-3">
                <x-heroicon-o-phone class="w-5 h-5 text-emerald-600" />
                <h2 class="font-semibold text-gray-800">اطلاعات تماس</h2>
            </div>

            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">

                {{-- mobile --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        شماره موبایل
                        <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="tel"
                        wire:model="mobile"
                        placeholder="09xxxxxxxxx"
                        dir="ltr"
                        class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition @error('mobile') border-red-400 bg-red-50 @enderror"
                    />
                    @error('mobile')
                    <p class="mt-1 text-xs text-red-600 flex items-center gap-1">
                        <x-heroicon-s-exclamation-circle class="w-3.5 h-3.5" />
                        {{ $message }}
                    </p>
                    @enderror
                </div>

                {{-- phone --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        تلفن ثابت (اختیاری)
                    </label>
                    <input
                        type="tel"
                        wire:model="phone"
                        placeholder="02112345678"
                        dir="ltr"
                        class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition"
                    />
                </div>

                {{-- email --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        ایمیل(اختیاری)
                    </label>
                    <input
                        type="email"
                        wire:model="email"
                        placeholder="example@domain.com"
                        dir="ltr"
                        class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition @error('email') border-red-400 bg-red-50 @enderror"
                    />
                    @error('email')
                    <p class="mt-1 text-xs text-red-600 flex items-center gap-1">
                        <x-heroicon-s-exclamation-circle class="w-3.5 h-3.5" />
                        {{ $message }}
                    </p>
                    @enderror
                </div>

                {{-- website --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        وب‌سایت (اختیاری)
                    </label>
                    <input
                        type="url"
                        wire:model="website"
                        placeholder="https://example.com"
                        dir="ltr"
                        class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition"
                    />
                </div>

            </div>
        </div>

        {{-- Section 3: Address --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="bg-gray-50 border-b border-gray-100 px-6 py-4 flex items-center gap-3">
                <x-heroicon-o-map-pin class="w-5 h-5 text-emerald-600" />
                <h2 class="font-semibold text-gray-800">آدرس کسب‌وکار</h2>
            </div>

            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">

                <!-- انتخاب استان -->
                <div class="col-span-1">
                    <x-input-label for="province_id" value="استان" />
                    <select wire:model.live="province_id" id="province_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                        <option value="">انتخاب استان...</option>
                        @foreach($provinces as $province)
                        <option value="{{ $province->id }}">{{ $province->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error class="mt-2" :messages="$errors->get('province_id')" />
                </div>

                <!-- انتخاب شهر -->
                <div class="col-span-1">
                    <x-input-label for="city_id" value="شهر" />
                    <select wire:model="city_id" id="city_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" {{ empty($citiesList) ? 'disabled' : '' }}>
                    <option value="">انتخاب شهر...</option>
                    @foreach($citiesList as $city)
                    <option value="{{ $city->id }}">{{ $city->name }}</option>
                    @endforeach
                    </select>
                    <x-input-error class="mt-2" :messages="$errors->get('city_id')" />
                </div>

                {{-- address --}}
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        آدرس کامل
                        <span class="text-red-500">*</span>
                    </label>
                    <textarea
                        wire:model="address"
                        rows="2"
                        placeholder="خیابان، کوچه، پلاک..."
                        class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition resize-none @error('address') border-red-400 bg-red-50 @enderror"
                    ></textarea>
                    @error('address')
                    <p class="mt-1 text-xs text-red-600 flex items-center gap-1">
                        <x-heroicon-s-exclamation-circle class="w-3.5 h-3.5" />
                        {{ $message }}
                    </p>
                    @enderror
                </div>

                {{-- postal_code --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        کد پستی
                        <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="text"
                        wire:model="postal_code"
                        placeholder="1234567890"
                        maxlength="10"
                        dir="ltr"
                        class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition @error('postal_code') border-red-400 bg-red-50 @enderror"
                    />
                    @error('postal_code')
                    <p class="mt-1 text-xs text-red-600 flex items-center gap-1">
                        <x-heroicon-s-exclamation-circle class="w-3.5 h-3.5" />
                        {{ $message }}
                    </p>
                    @enderror
                </div>

            </div>
        </div>

        {{-- Section 4: Legal --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="bg-gray-50 border-b border-gray-100 px-6 py-4 flex items-center gap-3">
                <x-heroicon-o-identification class="w-5 h-5 text-emerald-600" />
                <h2 class="font-semibold text-gray-800">اطلاعات قانونی</h2>
                <span class="text-xs text-gray-500 font-normal">(برای تأیید هویت کسب‌وکار)</span>
            </div>

            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">

                {{-- national_id --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        کد ملی / شناسه ملی
                        <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="text"
                        wire:model="national_id"
                        placeholder="کد ملی ۱۰ رقمی یا شناسه ملی ۱۱ رقمی"
                        maxlength="11"
                        dir="ltr"
                        class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition @error('national_id') border-red-400 bg-red-50 @enderror"
                    />
                    @error('national_id')
                    <p class="mt-1 text-xs text-red-600 flex items-center gap-1">
                        <x-heroicon-s-exclamation-circle class="w-3.5 h-3.5" />
                        {{ $message }}
                    </p>
                    @enderror
                </div>

                {{-- tax_id --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        شناسه مالیاتی (اختیاری)
                    </label>
                    <input
                        type="text"
                        wire:model="tax_id"
                        placeholder="شناسه مالیاتی"
                        dir="ltr"
                        class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition"
                    />
                </div>

            </div>

            <div class="px-6 pb-4">
                <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 flex items-start gap-2">
                    <x-heroicon-o-lock-closed class="w-4 h-4 text-amber-600 mt-0.5 shrink-0" />
                    <p class="text-xs text-amber-800">
                        اطلاعات قانونی شما صرفاً جهت تأیید هویت استفاده شده و محرمانه نگه‌داری می‌شود.
                    </p>
                </div>
            </div>
        </div>

        {{-- Section 5: Social Networks --}}
        @if($availableSocialNetworks && $availableSocialNetworks->isNotEmpty())
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="bg-gray-50 border-b border-gray-100 px-6 py-4 flex items-center gap-3">
                <x-heroicon-o-share class="w-5 h-5 text-emerald-600" />
                <h2 class="font-semibold text-gray-800">شبکه‌های اجتماعی (اختیاری)</h2>
            </div>

            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-5">
                @foreach($availableSocialNetworks as $network)
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        {{ $network->display_name }}
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                            <x-heroicon-o-at-symbol class="w-4 h-4 text-gray-400" />
                        </div>
                        <input
                            type="text"
                            wire:model="social_networks.{{ $network->name }}"
                            placeholder="{{ $network->placeholder_example ?? 'نام کاربری' }}"
                            dir="ltr"
                            class="w-full rounded-lg border border-gray-300 pr-9 pl-4 py-2.5 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition"
                        />
                    </div>
                    @if($network->base_url)
                    <p class="mt-1 text-xs text-gray-400">{{ $network->base_url }}</p>
                    @endif
                    @error("social_networks.{$network->name}")
                    <p class="mt-1 text-xs text-red-600 flex items-center gap-1">
                        <x-heroicon-s-exclamation-circle class="w-3.5 h-3.5" />
                        {{ $message }}
                    </p>
                    @enderror
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Terms / Edit notice --}}
        @if($editMode)
        <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 flex items-start gap-3">
            <x-heroicon-o-information-circle class="w-5 h-5 text-blue-600 mt-0.5 shrink-0" />
            <p class="text-sm text-blue-800">
                پس از ذخیره تغییرات، پروفایل شما مجدداً توسط تیم ما بررسی خواهد شد.
            </p>
        </div>
        @else
        <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 flex items-start gap-3">
            <x-heroicon-o-information-circle class="w-5 h-5 text-blue-600 mt-0.5 shrink-0" />
            <p class="text-sm text-blue-800">
                با ثبت‌نام، با
                <a href="{{ route('terms') }}" target="_blank" class="font-semibold underline hover:no-underline">قوانین و مقررات</a>
                سایت موافقت می‌کنید. پس از ثبت، پروفایل شما توسط تیم ما بررسی خواهد شد.
            </p>
        </div>
        @endif

        {{-- Submit --}}
        <div class="flex items-center justify-between pt-2">
            <a href="{{ $editMode ? route('retailer.dashboard') : route('user.role-selection') }}" class="inline-flex items-center gap-2 text-sm text-gray-600 hover:text-gray-800 transition">
                <x-heroicon-o-arrow-right class="w-4 h-4" />
                {{ $editMode ? 'بازگشت به داشبورد' : 'بازگشت به انتخاب نقش' }}
            </a>

            <button
                type="submit"
                wire:loading.attr="disabled"
                wire:target="register,logo"
                class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-60 text-white font-semibold px-8 py-3 rounded-xl transition shadow-sm"
            >
                    <span wire:loading.remove wire:target="register">
                        <x-heroicon-o-check class="w-5 h-5" />
                    </span>
                <span wire:loading wire:target="register">
                        <svg class="animate-spin w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 22 6.477 22 12h-4z"/>
                        </svg>
                    </span>
                {{ $editMode ? 'ذخیره تغییرات' : 'ثبت‌نام به عنوان کاسب' }}
            </button>
        </div>
    </form>
</div>
</div>
