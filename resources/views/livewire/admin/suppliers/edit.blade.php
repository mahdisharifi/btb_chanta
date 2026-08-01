<div>
    <x-admin.subheader title="ویرایش تامین‌کننده: {{ $supplier->company_name }}" />

    <x-admin.card>
        <form wire:submit="update" class="space-y-8">

            <!-- بخش اطلاعات اکانت (تغییر کاربر متصل) -->
            <div class="p-5 border border-indigo-100 rounded-xl bg-indigo-50/30 shadow-sm relative">
                <div class="absolute -top-3 right-4 bg-white px-2 text-sm font-semibold text-indigo-600 border border-indigo-100 rounded-md">حساب کاربری متصل</div>

                <div class="mt-2">
                    <x-input-label for="user_id" value="کاربر مالک پروفایل *" />
                    <select wire:model="user_id" id="user_id" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block w-full mt-1">
                        <option value="">-- یک کاربر را انتخاب کنید --</option>
                        @foreach($users as $user)
                        <option value="{{ $user->id }}">{{ $user->name }} - {{ $user->mobile ?? $user->email }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-500 mt-2">لیست بالا شامل کاربر فعلی این پروفایل و سایر کاربرانی است که هنوز تامین‌کننده نیستند.</p>
                    <x-input-error :messages="$errors->get('user_id')" class="mt-2" />
                </div>
            </div>

            <!-- آپلود و نمایش لوگو -->
            <div>
                <x-input-label value="لوگوی تامین‌کننده" class="mb-2" />
                <div class="flex items-center gap-4">
                    @if ($logo)
                    <!-- پیش‌نمایش لوگوی جدید انتخاب شده -->
                    <img src="{{ $logo->temporaryUrl() }}" class="w-20 h-20 rounded-full object-cover border-2 border-indigo-100 shadow-sm">
                    @elseif ($current_logo)
                    <!-- نمایش لوگوی ذخیره شده قبلی -->
                    <img src="{{ asset('storage/' . $current_logo) }}" class="w-20 h-20 rounded-full object-cover border-2 border-gray-200 shadow-sm">
                    @else
                    <!-- آیکون پیش‌فرض (بدون لوگو) -->
                    <div class="w-20 h-20 rounded-full bg-gray-50 flex items-center justify-center border-2 border-dashed border-gray-300 text-gray-400">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    </div>
                    @endif

                    <div class="flex flex-col gap-1">
                        <input type="file" wire:model="logo" id="logo" class="text-sm text-gray-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 transition-colors" accept="image/*">
                        <div wire:loading wire:target="logo" class="text-xs text-indigo-600 font-medium mt-1">در حال آپلود، لطفاً صبر کنید...</div>
                        <p class="text-xs text-gray-400 mt-1">فرمت‌های مجاز: JPG, PNG. حداکثر حجم: 2 مگابایت</p>
                    </div>
                </div>
                <x-input-error :messages="$errors->get('logo')" class="mt-2" />
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- اطلاعات شرکت -->
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
                    <x-input-label for="phone" value="تلفن ثابت" />
                    <x-text-input wire:model="phone" id="phone" type="text" dir="ltr" class="mt-1 block w-full text-right" />
                    <x-input-error :messages="$errors->get('phone')" class="mt-2" />
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
                    ذخیره تغییرات
                </x-primary-button>
            </div>
        </form>
    </x-admin.card>
</div>
