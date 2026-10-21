<div dir="rtl" class="min-w-0">
    <x-admin.subheader title="افزودن تأمین‌کننده" />
    <x-admin.card>
        <form wire:submit="save" class="space-y-6">
            <p id="user-help" class="text-sm leading-7 text-gray-700">کاربر موجود را انتخاب کنید. اطلاعات تأمین‌کننده در مرحله بعد تکمیل می‌شود. کاربران دارای پروفایل تأمین‌کننده، حتی حذف‌شده، قابل انتخاب نیستند.</p>
            <div class="grid min-w-0 grid-cols-1 gap-6 md:grid-cols-2">
                <div class="min-w-0">
                    <label for="user_search" class="block font-medium">جستجوی کاربر</label>
                    <input id="user_search" type="search" wire:model.live.debounce.300ms="search" placeholder="نام، موبایل یا ایمیل" aria-describedby="search-help" class="mt-2 min-h-11 w-full min-w-0 rounded-md border-gray-300 focus:ring-indigo-500">
                    <p id="search-help" class="mt-2 text-sm text-gray-600">حداکثر ۳۰ نتیجه به‌علاوه کاربر انتخاب‌شده نمایش داده می‌شود. تغییر جستجو انتخاب فعلی را پاک نمی‌کند.</p>
                    <p wire:loading wire:target="search" role="status">در حال جستجو…</p>
                </div>
                <div class="min-w-0">
                    <label for="user_id" class="block font-medium">کاربر (الزامی)</label>
                    <select id="user_id" wire:model="user_id" required aria-describedby="user-help user-results user-error" aria-invalid="{{ $errors->has('user_id') ? 'true' : 'false' }}" class="mt-2 min-h-11 w-full min-w-0 max-w-full rounded-md border-gray-300 focus:ring-indigo-500">
                        <option value="">یک کاربر انتخاب کنید</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" wire:key="supplier-user-{{ $user->id }}">{{ $user->name }} — {{ $user->mobile ?: ($user->email ?: 'شناسه: '.$user->id) }}</option>
                        @endforeach
                    </select>
                    <p id="user-results" role="status" aria-live="polite" class="mt-2 text-sm text-gray-600">
                        @if ($matchingCount === 0)
                            کاربر واجد شرایطی یافت نشد. عبارت جستجو را تغییر دهید.
                        @else
                            {{ $matchingCount }} نتیجه منطبق با جستجو نمایش داده می‌شود.
                        @endif
                    </p>
                    @if ($retainedSelection)
                        <p id="retained-selection" role="status" class="mt-2 break-words text-sm text-indigo-700">
                            انتخاب فعلی خارج از نتایج نمایش‌داده‌شده حفظ شده است: {{ $retainedSelection->name }}
                        </p>
                    @endif
                    <div id="user-error" role="alert">
                        <x-input-error :messages="$errors->get('user_id')" class="mt-2" />
                    </div>
                </div>
            </div>
            <div class="flex flex-col gap-3 border-t pt-5 sm:flex-row sm:justify-end">
                <a href="{{ route('admin.suppliers.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-md border px-4 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500">انصراف</a>
                <button type="submit" wire:loading.attr="disabled" wire:target="save,search" class="min-h-11 rounded-md bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-50">
                    <span wire:loading.remove wire:target="save">ادامه و تکمیل اطلاعات تأمین‌کننده</span>
                    <span wire:loading wire:target="save" role="status">در حال انتقال…</span>
                </button>
            </div>
        </form>
    </x-admin.card>
</div>
