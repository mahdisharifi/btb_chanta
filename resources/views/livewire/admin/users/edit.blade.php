<div>
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-800">ویرایش کاربر: {{ $user->name }}</h2>
        <a wire:navigate href="{{ route('admin.users.index') }}" class="px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600 transition">
            بازگشت به لیست
        </a>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <form wire:submit="update" class="space-y-6">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- نام -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">نام و نام خانوادگی</label>
                    <input type="text" wire:model="name" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" placeholder="نام کاربر">
                    @error('name') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                </div>

                <!-- ایمیل -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">ایمیل</label>
                    <input type="email" wire:model="email" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" dir="ltr" placeholder="user@example.com">
                    @error('email') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                </div>

                <!-- موبایل -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">شماره موبایل</label>
                    <input type="text" wire:model="mobile" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" dir="ltr" placeholder="0912...">
                    @error('mobile') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                </div>

                <!-- رمز عبور -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">رمز عبور جدید (اختیاری)</label>
                    <input type="password" wire:model="password" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" dir="ltr" placeholder="برای تغییر رمز وارد کنید">
                    @error('password') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                    <span class="text-gray-400 text-xs">اگر نمی‌خواهید رمز تغییر کند، این فیلد را خالی بگذارید.</span>
                </div>

                <!-- نقش کاربری -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">نقش کاربری</label>
                    <select wire:model="role" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        @foreach($roles as $roleEnum)
                        <!-- تغییر در این خط انجام شده است -->
                        <option value="{{ $roleEnum->value }}">{{ $roleEnum->label() }}</option>
                        @endforeach
                    </select>
                    @error('role') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                </div>


                <!-- وضعیت -->
                <div class="flex items-center mt-4">
                    <input type="checkbox"
                           id="is_active"
                           wire:model="is_active"
                           class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500">
                    <label for="is_active" class="mr-2 block text-sm text-gray-900">
                        کاربر فعال است
                    </label>
                </div>

            </div>

            <!-- دکمه ثبت -->
            <div class="flex justify-end pt-4 border-t border-gray-100">
                <button type="submit" class="px-6 py-2 bg-blue-600 text-white font-medium rounded-md hover:bg-blue-700 transition focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <span wire:loading.remove wire:target="update">ذخیره تغییرات</span>
                    <span wire:loading wire:target="update">در حال ذخیره...</span>
                </button>
            </div>

        </form>
    </div>
</div>
