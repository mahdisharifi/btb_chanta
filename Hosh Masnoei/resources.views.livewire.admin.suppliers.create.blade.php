<div>
    <x-admin.subheader title="افزودن تامین‌کننده جدید" />

    <x-admin.card>
        <form wire:submit="save" class="space-y-8">

            <!-- انتخاب نوع ثبت کاربر -->
            <div class="bg-gray-50 p-4 rounded-xl border border-gray-200">
                <x-input-label value="نحوه ثبت کاربر *" class="mb-3 text-base font-bold text-gray-800" />
                <div class="flex items-center space-x-6 space-x-reverse">
                    <label class="flex items-center cursor-pointer">
                        <input type="radio" wire:model.live="is_new_user" value="0" class="w-5 h-5 text-indigo-600 focus:ring-indigo-500 border-gray-300">
                        <span class="mr-2 text-sm text-gray-700">انتخاب از کاربران فعلی</span>
                    </label>
                    <label class="flex items-center cursor-pointer">
                        <input type="radio" wire:model.live="is_new_user" value="1" class="w-5 h-5 text-indigo-600 focus:ring-indigo-500 border-gray-300">
                        <span class="mr-2 text-sm text-gray-700">ایجاد کاربر جدید</span>
                    </label>
                </div>
            </div>

            <!-- بخش اطلاعات اکانت (متغیر بر اساس انتخاب کاربر) -->
            <div class="p-5 border border-indigo-100 rounded-xl bg-indigo-50/30 shadow-sm relative">
                <div class="absolute -top-3 right-4 bg-white px-2 text-sm font-semibold text-indigo-600 border border-indigo-100 rounded-md">اطلاعات کاربری (جهت ورود به پنل کاربری)</div>

                @if(!$is_new_user)
                <!-- انتخاب کاربر موجود -->
                <div class="mt-2">
                    <x-input-label for="user_search" value="جستجو و انتخاب کاربر (فقط کاربران بدون پروفایل تامین‌کننده) *" />
                    <x-text-input wire:model.live.debounce.300ms="search" id="user_search" type="text" class="mt-1 block w-full mb-2" placeholder="جستجو بر اساس نام، ایمیل یا موبایل..." />

                    <select wire:model="user_id" id="user_id" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block w-full mt-1">
                        <option value="">-- یک کاربر را انتخاب کنید --</option>
                        @foreach($users as $user)
                        <option value="{{ $user->id }}">{{ $user->name }} - (شماره ورود: {{ $user->mobile ?? 'بدون موبایل' }})</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('user_id')" class="mt-2" />
                </div>
                @else
                <!-- فرم ایجاد کاربر جدید -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-2">
                    <div>
                        <x-input-label for="name" value="نام و نام خانوادگی کاربر *" />
                        <x-text-input wire:model="name" id="name" type="text" class="mt-1 block w-full" required />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="mobile" value="شماره موبایل کاربر (جهت ورود به سیستم) *" />
                        <x-text-input wire:model="mobile" id="mobile" type="text" dir="ltr" class="mt-1 block w-full text-right" required />
                        <x-input-error :messages="$errors->get('mobile')" class="mt-2" />
                    </div>
                    <div class="md:col-span-2">
                        <x-input-label for="email" value="ایمیل کاربر (اختیاری - جهت ورود)" />
                        <x-text-input wire:model="email" id="email" type="email" dir="ltr" class="mt-1 block w-full text-right" />
                        <x-input-error :messages="$errors->get('email')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="password" value="رمز عبور *" />
                        <x-text-input wire:model="password" id="password" type="password" dir="ltr" class="mt-1 block w-full text-right" required />
                        <x-input-error :messages="$errors->get('password')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="password_confirmation" value="تکرار رمز عبور *" />
                        <x-text-input wire:model="password_confirmation" id="password_confirmation" type="password" dir="ltr" class="mt-1 block w-full text-right" required />
                        <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
                    </div>
                </div>
                @endif
            </div>

            <!-- آپلود لوگو -->
            <div>
                <x-input-label value="لوگوی تامین‌کننده (اختیاری)" class="mb-2" />
                <div class="flex items-center gap-4">
                    @if ($logo)
                    <img src="{{ $logo->temporaryUrl() }}" class="w-20 h-20 rounded-full object-cover border-2 border-indigo-100 shadow-sm">
                    @else
                    <div class="w-20 h-20 rounded-full bg-gray-50 flex items-center justify-center border-2 border-dashed border-gray-300 text-gray-400">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    </div>
                    @endif

                    <div class="flex flex-col gap-1">
                        <input type="file" wire:model="logo" id="logo" class="text-sm text-gray-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 transition-colors" accept="image/*">
                        <div wire:loading wire:target="logo" class="text-xs text-indigo-600 font-medium">در حال آپلود، لطفاً صبر کنید...</div>
                    </div>
                </div>
                <x-input-error :messages="$errors->get('logo')" class="mt-2" />
            </div>

            <!-- بخش اطلاعات تجاری و کسب‌وکار -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <x-input-label for="company_name" value="نام شرکت *" />
                    <x-text-input wire:model="company_name" id="company_name" type="text" class="mt-1 block w-full" required />
                    <x-input-error :messages="$errors->get('company_name')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="brand_name" value="نام برند" />
                    <x-text-input wire:model="brand_name" id="brand_name" type="text" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('brand_name')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="national_id" value="شناسه ملی" />
                    <x-text-input wire:model="national_id" id="national_id" type="text" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('national_id')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="tax_id" value="کد اقتصادی" />
                    <x-text-input wire:model="tax_id" id="tax_id" type="text" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('tax_id')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="phone" value="تلفن ثابت کسب‌وکار" />
                    <x-text-input wire:model="phone" id="phone" type="text" dir="ltr" class="mt-1 block w-full text-right" />
                    <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="supplier_mobile" value="شماره موبایل تامین‌کننده (تماس کسب‌وکار)" />
                    <x-text-input wire:model="supplier_mobile" id="supplier_mobile" type="text" dir="ltr" class="mt-1 block w-full text-right" placeholder="موبایل رسمی تجاری" />
                    <x-input-error :messages="$errors->get('supplier_mobile')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="province" value="استان" />
                    <x-text-input wire:model="province" id="province" type="text" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('province')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="city" value="شهر" />
                    <x-text-input wire:model="city" id="city" type="text" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('city')" class="mt-2" />
                </div>
            </div>

            <div>
                <x-input-label for="address" value="آدرس کامل" />
                <textarea wire:model="address" id="address" rows="3" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block w-full mt-1"></textarea>
                <x-input-error :messages="$errors->get('address')" class="mt-2" />
            </div>

            <!-- تنظیمات وضعیت (فعال بودن و تایید) -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 bg-gray-50 border border-gray-100 p-5 rounded-xl">
                <div class="flex items-center">
                    <input wire:model="is_active" id="is_active" type="checkbox" class="w-5 h-5 text-indigo-600 bg-white border-gray-300 rounded focus:ring-indigo-500 focus:ring-2 cursor-pointer">
                    <label for="is_active" class="mr-3 ml-2 block text-sm font-medium text-gray-800 cursor-pointer">
                        تامین‌کننده فعال باشد (اجازه ورود و فعالیت در پنل)
                    </label>
                </div>

                <div class="flex items-center">
                    <input wire:model="is_verified" id="is_verified" type="checkbox" class="w-5 h-5 text-indigo-600 bg-white border-gray-300 rounded focus:ring-indigo-500 focus:ring-2 cursor-pointer">
                    <label for="is_verified" class="mr-3 ml-2 block text-sm font-medium text-gray-800 cursor-pointer">
                        تامین‌کننده احراز هویت شده است (تایید مدارک)
                    </label>
                </div>
            </div>

            <div class="flex items-center justify-end mt-8 gap-4 pt-4 border-t border-gray-100">
                <x-secondary-button x-on:click="window.history.back()">
                    انصراف
                </x-secondary-button>
                <x-primary-button>
                    ثبت اطلاعات تامین‌کننده
                </x-primary-button>
            </div>
        </form>
    </x-admin.card>
</div>
