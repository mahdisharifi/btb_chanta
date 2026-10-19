<div>
    <x-admin.subheader title="ایجاد کاربر جدید" :breadcrumbs="[
        ['title' => 'داشبورد', 'url' => route('admin.dashboard')],
        ['title' => 'کاربران', 'url' => route('admin.users.index')],
        ['title' => 'ایجاد کاربر']
    ]" />

    <div class="grid grid-cols-12 gap-6">
        <div class="col-span-12">
            <x-admin.card>
                <form wire:submit="save">
                    <div class="grid grid-cols-12 gap-x-6 gap-y-8">
                        {{-- Base User Information --}}
                        <div class="col-span-12 sm:col-span-6 md:col-span-4">
                            <x-label for="name" required>نام و نام خانوادگی</x-label>
                            <x-input id="name" wire:model="name" class="mt-1 w-full" type="text" />
                            <x-input-error :messages="$errors->get('name')"  class="mt-2" />
                        </div>

                        <div class="col-span-12 sm:col-span-6 md:col-span-4">
                            <x-label for="email" required>ایمیل</x-label>
                            <x-input id="email" wire:model="email" class="mt-1 w-full ltr" type="email" />
                            <x-input-error :messages="$errors->get('email')" class="mt-2" />
                        </div>

                        <div class="col-span-12 sm:col-span-6 md:col-span-4">
                            <x-label for="mobile">
                                موبایل
                                @if($isSystemRole)
                                    <span class="text-xs font-normal text-gray-500">(اختیاری برای کاربر سیستمی)</span>
                                @else
                                    <span class="text-red-600" aria-hidden="true">*</span>
                                @endif
                            </x-label>
                            <x-input id="mobile" wire:model="mobile" class="mt-1 w-full ltr" type="text" />
                            <x-input-error :messages="$errors->get('mobile')" class="mt-2" />
                            @if($isSystemRole)
                                <p class="mt-2 text-xs leading-5 text-gray-500">اگر موبایل خالی باشد، کاربر با وضعیت تأییدشده ساخته می‌شود.</p>
                            @endif
                        </div>

                        <div class="col-span-12 sm:col-span-6 md:col-span-4">
                            <x-label for="role" required>نقش</x-label>
                            <x-select id="role" wire:model.live="role" :options="\App\Enums\UserRole::options()" placeholder="- انتخاب نقش -" class="mt-1 w-full" />
                            <x-input-error :messages="$errors->get('role')" class="mt-2" />
                        </div>

                        <div class="col-span-12 sm:col-span-6 md:col-span-4">
                            <x-label for="password" required>رمز عبور</x-label>
                            <x-input id="password" wire:model="password" class="mt-1 w-full" type="password" />
                            <x-input-error :messages="$errors->get('password')" class="mt-2" />
                        </div>

                        <div class="col-span-12 sm:col-span-6 md:col-span-4">
                            <x-label for="password_confirmation" required>تکرار رمز عبور</x-label>
                            <x-input id="password_confirmation" wire:model="password_confirmation" class="mt-1 w-full" type="password" />
                        </div>

                        {{-- Supplier Specific Fields --}}
                        @if($role === \App\Enums\UserRole::SUPPLIER->value)
                        <div class="col-span-12">
                            <hr class="border-gray-200 dark:border-gray-700">
                            <h3 class="mt-4 text-lg font-medium text-gray-900 dark:text-gray-100">اطلاعات تکمیلی تامین کننده</h3>
                        </div>

                        <div class="col-span-12 sm:col-span-6 md:col-span-4">
                            <x-label for="company_name" required>نام شرکت</x-label>
                            <x-input id="company_name" wire:model="company_name" class="mt-1 w-full" type="text" />
                            <x-input-error :messages="$errors->get('company_name')" class="mt-2" />
                        </div>

                        <div class="col-span-12 sm:col-span-6 md:col-span-4">
                            <x-label for="brand_name">نام برند</x-label>
                            <x-input id="brand_name" wire:model="brand_name" class="mt-1 w-full" type="text" />
                            <x-input-error :messages="$errors->get('brand_name')" class="mt-2" />
                        </div>

                        <div class="col-span-12 sm:col-span-6 md:col-span-4">
                            <x-label for="national_id">شناسه ملی</x-label>
                            <x-input id="national_id" wire:model="national_id" class="mt-1 w-full ltr" type="text" />
                            <x-input-error :messages="$errors->get('national_id')" class="mt-2" />
                        </div>

                        <div class="col-span-12 sm:col-span-6 md:col-span-4">
                            <x-label for="tax_id">کد اقتصادی</x-label>
                            <x-input id="tax_id" wire:model="tax_id" class="mt-1 w-full ltr" type="text" />
                            <x-input-error :messages="$errors->get('tax_id')" class="mt-2" />
                        </div>

                        <div class="col-span-12 sm:col-span-6 md:col-span-4">
                            <x-label for="phone">تلفن ثابت</x-label>
                            <x-input id="phone" wire:model="phone" class="mt-1 w-full ltr" type="text" />
                            <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                        </div>

                        <div class="col-span-12 sm:col-span-6 md:col-span-4">
                            <x-label for="postal_code">کد پستی</x-label>
                            <x-input id="postal_code" wire:model="postal_code" class="mt-1 w-full ltr" type="text" />
                            <x-input-error :messages="$errors->get('postal_code')" class="mt-2" />
                        </div>

                        <div class="col-span-12 sm:col-span-6 md:col-span-4">
                            <x-label for="province">استان</x-label>
                            <x-input id="province" wire:model="province" class="mt-1 w-full" type="text" />
                            <x-input-error :messages="$errors->get('province')" class="mt-2" />
                        </div>

                        <div class="col-span-12 sm:col-span-6 md:col-span-4">
                            <x-label for="city">شهر</x-label>
                            <x-input id="city" wire:model="city" class="mt-1 w-full" type="text" />
                            <x-input-error :messages="$errors->get('city')" class="mt-2" />
                        </div>

                        <div class="col-span-12">
                            <x-label for="address">آدرس</x-label>
                            <x-textarea id="address" wire:model="address" class="mt-1 w-full" rows="3"></x-textarea>
                            <x-input-error :messages="$errors->get('address')" class="mt-2" />
                        </div>
                        @endif
                    </div>

                    <div class="mt-8 flex items-center justify-end space-x-4 space-x-reverse">
                        <x-primary-button>ذخیره و ایجاد</x-primary-button>
                        <x-secondary-button type="button" href="{{ route('admin.users.index') }}" wire:navigate>انصراف</x-secondary-button>
                    </div>
                </form>
            </x-admin.card>
        </div>
    </div>
</div>
