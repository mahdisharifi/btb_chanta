<div class="min-w-0">
    <x-admin.subheader :title="$isEditing ? 'ویرایش کاربر' : 'ایجاد کاربر جدید'" :breadcrumbs="[
        ['title' => 'داشبورد', 'url' => route('admin.dashboard')],
        ['title' => 'کاربران', 'url' => route('admin.users.index')],
        ['title' => $isEditing ? 'ویرایش کاربر' : 'ایجاد کاربر']
    ]" />

    <x-admin.card padding="p-4 sm:p-6" class="min-w-0">
        <form wire:submit="save" class="min-w-0 space-y-6">
            <section class="min-w-0">
                <h3 class="mb-3 text-sm font-bold text-gray-900">نوع حساب و اطلاعات پایه</h3>
                <div class="grid min-w-0 grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <div class="min-w-0">
                        <x-label for="role" required>نقش</x-label>
                        <x-select id="role" wire:model.live="role" :options="\App\Enums\UserRole::options()" placeholder="- انتخاب نقش -" class="mt-1 w-full" />
                    </div>
                    <div class="min-w-0">
                        <x-label for="name" required>نام و نام خانوادگی</x-label>
                        <x-input id="name" wire:model="name" class="mt-1 w-full" type="text" autocomplete="name" />
                    </div>
                    <div class="min-w-0 sm:col-span-2 xl:col-span-1">
                        <x-label for="email" required>ایمیل</x-label>
                        <x-input id="email" wire:model="email" class="ltr mt-1 w-full" type="email" autocomplete="email" />
                    </div>
                </div>
            </section>

            <section class="min-w-0 border-t border-gray-100 pt-6">
                <h3 class="mb-3 text-sm font-bold text-gray-900">تماس و وضعیت حساب</h3>
                <div class="grid min-w-0 grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <div class="min-w-0">
                        <x-label for="mobile">
                            موبایل
                            @if($isSystemRole || $allow_without_mobile)
                                <span class="text-xs font-normal text-gray-500">(اختیاری)</span>
                            @else
                                <span class="text-red-600" aria-hidden="true">*</span>
                            @endif
                        </x-label>
                        <x-input id="mobile" wire:model="mobile" class="ltr mt-1 w-full" type="tel" inputmode="tel" autocomplete="tel" />
                    </div>

                    @if($canUseAdminOverrides)
                        <label class="flex min-h-11 min-w-0 cursor-pointer items-start gap-3 rounded-lg border border-gray-200 p-3 hover:bg-gray-50">
                            <input type="checkbox" wire:model.live="allow_without_mobile" class="mt-1 shrink-0 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                            <span class="min-w-0">
                                <span class="block text-sm font-medium text-gray-900">ثبت بدون شماره موبایل</span>
                                <span class="mt-1 block text-xs leading-5 text-gray-600">وقتی شماره در دسترس نیست.</span>
                            </span>
                        </label>

                        <label class="flex min-h-11 min-w-0 cursor-pointer items-start gap-3 rounded-lg border border-gray-200 p-3 hover:bg-gray-50">
                            <input type="checkbox" wire:model="mark_as_verified" class="mt-1 shrink-0 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                            <span class="min-w-0">
                                <span class="block text-sm font-medium text-gray-900">حساب تأییدشده باشد</span>
                                <span class="mt-1 block text-xs leading-5 text-gray-600">ایمیل، موبایل یا پروفایل تأمین‌کننده را تأیید نمی‌کند.</span>
                            </span>
                        </label>
                    @endif

                    @if($canToggleStatus)
                        <label class="flex min-h-11 min-w-0 cursor-pointer items-start gap-3 rounded-lg border border-gray-200 p-3 hover:bg-gray-50">
                            <input type="checkbox" wire:model="is_active" class="mt-1 shrink-0 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                            <span class="min-w-0">
                                <span class="block text-sm font-medium text-gray-900">حساب فعال باشد</span>
                                <span class="mt-1 block text-xs leading-5 text-gray-600">کاربر غیرفعال نمی‌تواند وارد شود.</span>
                            </span>
                        </label>
                    @endif
                </div>
            </section>

            <section class="min-w-0 border-t border-gray-100 pt-6">
                <h3 class="mb-1 text-sm font-bold text-gray-900">رمز عبور</h3>
                @if($isEditing)
                    <p class="mb-3 text-xs text-gray-500">برای حفظ رمز فعلی، این بخش را خالی بگذارید.</p>
                @endif
                <div class="grid min-w-0 grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3 {{ $isEditing ? '' : 'mt-3' }}">
                    <div class="min-w-0">
                        <x-label for="password" :required="! $isEditing">{{ $isEditing ? 'رمز عبور جدید' : 'رمز عبور' }}</x-label>
                        <x-input id="password" wire:model="password" class="mt-1 w-full" type="password" autocomplete="new-password" />
                    </div>
                    <div class="min-w-0">
                        <x-label for="password_confirmation" :required="! $isEditing">تکرار رمز عبور</x-label>
                        <x-input id="password_confirmation" wire:model="password_confirmation" class="mt-1 w-full" type="password" autocomplete="new-password" />
                    </div>
                </div>
            </section>

            <div class="flex flex-col-reverse gap-3 border-t border-gray-100 pt-6 sm:flex-row sm:justify-end">
                <x-secondary-button type="button" href="{{ route('admin.users.index') }}" wire:navigate class="min-h-11 w-full justify-center sm:w-auto">انصراف</x-secondary-button>
                <x-primary-button wire:loading.attr="disabled" wire:target="save" class="min-h-11 w-full justify-center disabled:opacity-60 sm:w-auto">
                    <span wire:loading.remove wire:target="save">{{ $isEditing ? 'ذخیره تغییرات' : 'ذخیره و ایجاد' }}</span>
                    <span wire:loading wire:target="save">در حال ذخیره…</span>
                </x-primary-button>
            </div>
        </form>
    </x-admin.card>
</div>
