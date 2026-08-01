<div class="space-y-6">
    <x-admin.subheader title="مدیریت کاتالوگ‌ها">
        <x-admin.buttons.primary href="{{ route('admin.catalogs.create') }}">
            افزودن کاتالوگ جدید
        </x-admin.buttons.primary>
    </x-admin.subheader>

    @if (session('message'))
    <x-alert type="success" :message="session('message')" />
    @endif

    <x-admin.card>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm text-right">
                <thead class="bg-gray-50">
                <tr class="border-b border-gray-200">
                    <th class="px-4 py-3 font-medium text-gray-700">نام کاتالوگ</th>
                    <th class="px-4 py-3 font-medium text-gray-700">تعداد تأمین‌کننده</th>
                    <th class="px-4 py-3 font-medium text-gray-700">نوع</th>
                    <th class="px-4 py-3 font-medium text-gray-700">تاریخ ایجاد</th>
                    <th class="px-4 py-3 font-medium text-gray-700">عملیات</th>
                </tr>
                </thead>
                <tbody>
                @forelse($catalogs as $catalog)
                @php
                $supplierIds = data_get($catalog->config, 'supplier_ids', []);
                $supplierCount = is_array($supplierIds) && count($supplierIds)
                ? count($supplierIds)
                : ($catalog->supplier_id ? 1 : 0);
                @endphp

                <tr class="border-b border-gray-100 hover:bg-gray-50">
                    <td class="px-4 py-3 text-gray-900">{{ $catalog->name }}</td>
                    <td class="px-4 py-3 text-gray-700">{{ $supplierCount }}</td>
                    <td class="px-4 py-3 text-gray-700">
                        @if($supplierCount > 1)
                        تجمیعی
                        @elseif($catalog->supplier_id)
                        تک‌تأمین‌کننده
                        @else
                        نامشخص
                        @endif
                    </td>
                    <td class="px-4 py-3 text-gray-700">
                        {{ jdate($catalog->created_at)->format('Y-m-d') }}
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-3">
                            <a href="{{ route('admin.catalogs.edit', $catalog) }}"
                               class="text-amber-600 hover:text-amber-700">
                                ویرایش
                            </a>

                            <button
                                type="button"
                                wire:click="delete({{ $catalog->id }})"
                                wire:confirm="آیا از حذف این کاتالوگ مطمئن هستید؟"
                                class="text-red-600 hover:text-red-700"
                            >
                                حذف
                            </button>
                        </div>
                        <a href="{{ route('admin.catalogs.preview', $catalog->id) }}"
                           target="_blank"
                           class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                           title="پیش‌نمایش کاتالوگ">
                            <x-heroicon-o-eye class="w-5 h-5"/>
                        </a>
                    </td>

                </tr>
                @empty
                <tr>
                    <td colspan="5" class="px-4 py-6 text-center text-gray-500">
                        هنوز هیچ کاتالوگی ثبت نشده است.
                    </td>
                </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if($catalogs->hasPages())
        <div class="mt-6">
            {{ $catalogs->links() }}
        </div>
        @endif
    </x-admin.card>
</div>
