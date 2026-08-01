<div>
    <!-- هدر صفحه -->
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-800">مدیریت کاربران</h2>
        <a href="{{ route('admin.users.create') }}" wire:navigate class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm transition">
            افزودن کاربر جدید
        </a>
    </div>

    <!-- پیام موفقیت -->
    @if (session()->has('success'))
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
        <span class="block sm:inline">{{ session('success') }}</span>
    </div>
    @endif

    <!-- بخش جستجو -->
    <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-100 mb-6">
        <div class="w-full md:w-1/3 relative">
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="جستجو در نام، ایمیل یا شماره تماس..."
                class="w-full pl-10 pr-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm"
            >
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
            </div>
        </div>
    </div>

    <!-- جدول کاربران -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-right whitespace-nowrap">
                <thead class="bg-gray-50 border-b border-gray-100 text-gray-600 text-sm">
                <tr>
                    <th class="py-3 px-4 font-semibold">ردیف</th>
                    <th class="py-3 px-4 font-semibold">نام</th>
                    <th class="py-3 px-4 font-semibold">ایمیل</th>
                    <th class="py-3 px-4 font-semibold">نقش کاربری</th>
                    <th class="py-3 px-4 font-semibold">تاریخ عضویت</th>
                    <th class="py-3 px-4 font-semibold text-center">عملیات</th>
                </tr>
                </thead>
                <tbody class="text-sm divide-y divide-gray-100">
                @forelse ($users as $index => $user)
                <tr class="hover:bg-gray-50 transition" wire:key="user-{{ $user->id }}">
                    <td class="py-3 px-4 text-gray-500">
                        {{ $users->firstItem() + $index }}
                    </td>
                    <td class="py-3 px-4 text-gray-800 font-medium">
                        {{ $user->name }}
                    </td>
                    <td class="py-3 px-4 text-gray-600">
                        {{ $user->email }}
                    </td>
                    <td class="py-3 px-4">
                                <span class="px-2 py-1 bg-blue-100 text-blue-700 rounded-full text-xs">
                    {{ $user->getRoleLabel() ?? 'کاربر عادی' }}
                                </span>
                    </td>
                    <td class="py-3 px-4 text-gray-500 text-xs">
                        <!-- با فرض اینکه از پکیج Morilog/Jalali برای تاریخ شمسی استفاده می‌کنید -->
<!--                         در غیر اینصورت از فرمت میلادی استفاده کنید: $user->created_at->format('Y-m-d') -->
                        {{ \Morilog\Jalali\Jalalian::fromDateTime($user->created_at)->format('Y/m/d') }}
                    </td>
                    <td class="py-3 px-4 text-center">
                        <div class="flex items-center justify-center gap-2">
                            <!-- دکمه ویرایش -->
                            <a href="{{ route('admin.users.edit', $user->id) }}" wire:navigate class="p-1 text-blue-600 hover:bg-blue-50 rounded transition" title="ویرایش">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                            </a>

                            <!-- دکمه حذف -->
                            <button
                                wire:click="deleteUser({{ $user->id }})"
                                wire:confirm="آیا از حذف این کاربر اطمینان دارید؟"
                                class="p-1 text-red-600 hover:bg-red-50 rounded transition"
                                title="حذف"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                            </button>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="py-8 text-center text-gray-500">
                        کاربری یافت نشد.
                    </td>
                </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <!-- صفحه‌بندی -->
        <div class="p-4 border-t border-gray-100" dir="ltr">
            {{ $users->links() }}
        </div>
    </div>
</div>
