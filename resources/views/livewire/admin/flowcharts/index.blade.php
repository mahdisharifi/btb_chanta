<div>
    <x-admin.subheader title="مدیریت فلوچارت‌ها">
        <x-primary-button wire:navigate href="{{ route('admin.flowcharts.create') }}">
            ایجاد فلوچارت جدید
        </x-primary-button>
    </x-admin.subheader>

    <x-admin.card>
        <div class="flex justify-between items-center mb-6">
            <div class="w-1/3">
                <x-text-input wire:model.live.debounce.300ms="search" placeholder="جستجو در عنوان..." class="w-full" />
            </div>
        </div>

        <x-admin.recent-table>
            <x-slot name="head">
                <tr>
                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">عنوان</th>
                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">سازنده</th>
                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">تاریخ ایجاد</th>
                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">عملیات</th>
                </tr>
            </x-slot>
            <x-slot name="body">
                @forelse($flowcharts as $flowchart)
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-medium">{{ $flowchart->title }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $flowchart->user->name ?? 'نامشخص' }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500" dir="ltr">{{ jdate($flowchart->created_at)->format('Y/m/d H:i') }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium flex gap-3">
                        <a wire:navigate href="{{ route('admin.flowcharts.edit', $flowchart->id) }}" class="text-indigo-600 hover:text-indigo-900">ویرایش و مشاهده</a>
                        <button wire:click="delete({{ $flowchart->id }})" wire:confirm="آیا از حذف این فلوچارت اطمینان دارید؟" class="text-red-600 hover:text-red-900">حذف</button>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="px-6 py-4 text-center text-gray-500 text-sm">هیچ فلوچارتی یافت نشد.</td>
                </tr>
                @endforelse
            </x-slot>
        </x-admin.recent-table>

        <div class="mt-4">
            {{ $flowcharts->links() }}
        </div>
    </x-admin.card>
</div>
