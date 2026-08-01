<div class="space-y-6" wire:key="users-pending">

    {{-- Header --}}
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="flex items-center gap-2 text-2xl font-bold text-gray-900">
                <x-heroicon-o-user-plus class="h-7 w-7 text-indigo-600" />
                درخواست‌های در انتظار تایید
            </h1>
            <p class="mt-1 text-sm text-gray-500">
                بررسی، تایید یا رد کاربران تامین‌کننده و کسب‌وکار
            </p>
        </div>
    </div>

    {{-- Flash messages --}}
    @if (session('message'))
    <div class="flex items-center gap-2 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
        <x-heroicon-o-check-circle class="h-5 w-5 shrink-0" />
        <span>{{ session('message') }}</span>
    </div>
    @endif
    @if (session('error'))
    <div class="flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
        <x-heroicon-o-exclamation-triangle class="h-5 w-5 shrink-0" />
        <span>{{ session('error') }}</span>
    </div>
    @endif

    {{-- Stat cards --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <button type="button" wire:click="$set('statusFilter', 'pending')"
                class="flex items-center justify-between rounded-xl border bg-white p-5 text-right shadow-sm transition hover:shadow-md
                   {{ $statusFilter === 'pending' ? 'border-yellow-400 ring-2 ring-yellow-200' : 'border-gray-100' }}">
            <div>
                <p class="text-sm text-gray-500">در انتظار</p>
                <p class="mt-1 text-2xl font-bold text-gray-900">{{ number_format($this->stats['pending']) }}</p>
            </div>
            <span class="rounded-lg bg-yellow-100 p-3 text-yellow-700">
                <x-heroicon-o-clock class="h-6 w-6" />
            </span>
        </button>

        <button type="button" wire:click="$set('statusFilter', 'under_review')"
                class="flex items-center justify-between rounded-xl border bg-white p-5 text-right shadow-sm transition hover:shadow-md
                   {{ $statusFilter === 'under_review' ? 'border-blue-400 ring-2 ring-blue-200' : 'border-gray-100' }}">
            <div>
                <p class="text-sm text-gray-500">در حال بررسی</p>
                <p class="mt-1 text-2xl font-bold text-gray-900">{{ number_format($this->stats['under_review']) }}</p>
            </div>
            <span class="rounded-lg bg-blue-100 p-3 text-blue-700">
                <x-heroicon-o-magnifying-glass class="h-6 w-6" />
            </span>
        </button>

        <button type="button" wire:click="$set('statusFilter', 'rejected')"
                class="flex items-center justify-between rounded-xl border bg-white p-5 text-right shadow-sm transition hover:shadow-md
                   {{ $statusFilter === 'rejected' ? 'border-red-400 ring-2 ring-red-200' : 'border-gray-100' }}">
            <div>
                <p class="text-sm text-gray-500">رد شده</p>
                <p class="mt-1 text-2xl font-bold text-gray-900">{{ number_format($this->stats['rejected']) }}</p>
            </div>
            <span class="rounded-lg bg-red-100 p-3 text-red-700">
                <x-heroicon-o-x-circle class="h-6 w-6" />
            </span>
        </button>
    </div>

    {{-- Filters --}}
    <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
        <div class="grid grid-cols-1 gap-3 md:grid-cols-12">
            <div class="md:col-span-6">
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400">
                        <x-heroicon-o-magnifying-glass class="h-5 w-5" />
                    </span>
                    <input type="text" wire:model.live.debounce.400ms="search"
                           placeholder="جستجو نام، ایمیل، نام کسب‌وکار یا موبایل..."
                           class="w-full rounded-lg border-gray-300 pr-10 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                </div>
            </div>

            <div class="md:col-span-3">
                <select wire:model.live="roleFilter"
                        class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">همه نقش‌ها</option>
                    <option value="regular_supplier">تامین‌کننده</option>
                    <option value="retailer_basic">کسب‌وکار</option>
                </select>
            </div>

            <div class="md:col-span-2">
                <select wire:model.live="statusFilter"
                        class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">همه وضعیت‌ها</option>
                    <option value="pending">در انتظار</option>
                    <option value="under_review">در حال بررسی</option>
                    <option value="rejected">رد شده</option>
                </select>
            </div>

            <div class="md:col-span-1">
                <button type="button" wire:click="clearFilters"
                        class="flex h-full w-full items-center justify-center rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-600 transition hover:bg-gray-50"
                        title="پاک کردن فیلترها">
                    <x-heroicon-o-arrow-path class="h-5 w-5" />
                </button>
            </div>
        </div>
    </div>

    {{-- Bulk action bar --}}
    @if (count($selectedUsers) > 0)
    <div class="flex flex-col gap-3 rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
            <span class="text-sm font-medium text-indigo-800">
                {{ count($selectedUsers) }} کاربر انتخاب شده است
            </span>
        <div class="flex gap-2">
            <button type="button" wire:click="bulkApprove"
                    wire:confirm="آیا از تایید گروهی کاربران انتخاب‌شده مطمئن هستید؟"
                    class="inline-flex items-center gap-1 rounded-lg bg-green-600 px-3 py-2 text-sm font-medium text-white transition hover:bg-green-700">
                <x-heroicon-o-check-circle class="h-5 w-5" />
                تایید گروهی
            </button>
            <button type="button" wire:click="confirmBulkReject"
                    class="inline-flex items-center gap-1 rounded-lg bg-red-600 px-3 py-2 text-sm font-medium text-white transition hover:bg-red-700">
                <x-heroicon-o-x-circle class="h-5 w-5" />
                رد گروهی
            </button>
        </div>
    </div>
    @endif

    {{-- Table --}}
    <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-right">
                        <input type="checkbox" wire:model.live="selectAll"
                               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                    </th>
                    <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500">کاربر</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500">نقش</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500">نام کسب‌وکار</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500">وضعیت</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500">تاریخ ثبت‌نام</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500">عملیات</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                @forelse ($users as $user)
                @php $status = $user->supplier?->verification_status; @endphp
                <tr class="transition hover:bg-gray-50" wire:key="user-{{ $user->id }}">
                    <td class="px-4 py-4">
                        <input type="checkbox" wire:model.live="selectedUsers" value="{{ $user->id }}"
                               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                    </td>

                    <td class="px-6 py-4">
                        <div class="flex items-center gap-3">
                                    <span class="flex h-9 w-9 items-center justify-center rounded-full bg-indigo-100 text-sm font-bold text-indigo-700">
                                        {{ mb_substr($user->name, 0, 1) }}
                                    </span>
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ $user->name }}</p>
                                <p class="text-xs text-gray-500">{{ $user->email }}</p>
                            </div>
                        </div>
                    </td>

                    <td class="px-6 py-4">
                        @if ($user->hasRole('regular_supplier'))
                        <span class="inline-flex rounded-full bg-purple-100 px-2.5 py-1 text-xs font-semibold text-purple-800">
                                        تامین‌کننده
                                    </span>
                        @elseif ($user->hasRole('retailer_basic'))
                        <span class="inline-flex rounded-full bg-blue-100 px-2.5 py-1 text-xs font-semibold text-blue-800">
                                        کسب‌وکار
                                    </span>
                        @endif
                    </td>

                    <td class="px-6 py-4 text-sm text-gray-900">
                        {{ $user->supplier?->name ?: '—' }}
                        @if ($user->supplier?->mobile)
                        <span class="mt-0.5 block text-xs text-gray-400">{{ $user->supplier->mobile }}</span>
                        @endif
                    </td>

                    <td class="px-6 py-4">
                        @switch($status)
                        @case('pending')
                        <span class="inline-flex items-center gap-1 rounded-full bg-yellow-100 px-2.5 py-1 text-xs font-semibold text-yellow-800">
                                            <x-heroicon-o-clock class="h-4 w-4" /> در انتظار
                                        </span>
                        @break
                        @case('under_review')
                        <span class="inline-flex items-center gap-1 rounded-full bg-blue-100 px-2.5 py-1 text-xs font-semibold text-blue-800">
                                            <x-heroicon-o-magnifying-glass class="h-4 w-4" /> در حال بررسی
                                        </span>
                        @break
                        @case('rejected')
                        <span class="inline-flex items-center gap-1 rounded-full bg-red-100 px-2.5 py-1 text-xs font-semibold text-red-800">
                                            <x-heroicon-o-x-circle class="h-4 w-4" /> رد شده
                                        </span>
                        @break
                        @default
                        <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700">
                                            {{ $status ?? 'نامشخص' }}
                                        </span>
                        @endswitch
                    </td>

                    <td class="px-6 py-4 text-sm text-gray-600">
                        @if ($user->created_at)
                        {{ jdate($user->created_at)->format('Y/m/d') }}
                        <span class="block text-xs text-gray-400">{{ jdate($user->created_at)->format('H:i') }}</span>
                        @else
                        —
                        @endif
                    </td>

                    <td class="px-6 py-4">
                        <div class="flex items-center gap-1.5">
                            <a href="{{ route('admin.users.show', $user) }}"
                               class="inline-flex items-center justify-center rounded-lg p-2 text-gray-500 transition hover:bg-gray-100 hover:text-gray-800"
                               title="مشاهده جزئیات">
                                <x-heroicon-o-eye class="h-5 w-5" />
                            </a>
                            <button type="button" wire:click="approve({{ $user->id }})"
                                    wire:confirm="آیا از تایید این کاربر مطمئن هستید؟"
                                    class="inline-flex items-center justify-center rounded-lg p-2 text-green-600 transition hover:bg-green-50"
                                    title="تایید">
                                <x-heroicon-o-check-circle class="h-5 w-5" />
                            </button>
                            <button type="button" wire:click="confirmReject({{ $user->id }})"
                                    class="inline-flex items-center justify-center rounded-lg p-2 text-red-600 transition hover:bg-red-50"
                                    title="رد">
                                <x-heroicon-o-x-circle class="h-5 w-5" />
                            </button>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-6 py-16 text-center">
                        <div class="flex flex-col items-center gap-2 text-gray-400">
                            <x-heroicon-o-inbox class="h-12 w-12" />
                            <p class="text-sm font-medium">موردی برای نمایش یافت نشد</p>
                            <p class="text-xs">با فیلترهای فعلی هیچ درخواستی در انتظار تایید وجود ندارد.</p>
                        </div>
                    </td>
                </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if ($users->hasPages())
        <div class="border-t border-gray-100 px-4 py-3">
            {{ $users->links() }}
        </div>
        @endif
    </div>

    {{-- Reject modal --}}
    @if ($showRejectModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" wire:keydown.escape="closeRejectModal">
        <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
            <div class="mb-4 flex items-center gap-2 text-red-600">
                <x-heroicon-o-exclamation-triangle class="h-6 w-6" />
                <h3 class="text-lg font-bold text-gray-900">
                    {{ $bulkRejectMode ? 'رد گروهی کاربران' : 'رد درخواست کاربر' }}
                </h3>
            </div>

            <p class="mb-3 text-sm text-gray-500">
                لطفاً دلیل رد را وارد کنید. این متن برای کاربر قابل مشاهده خواهد بود.
            </p>

            <textarea wire:model="rejectReason" rows="4"
                      placeholder="مثال: مدارک ارسالی ناخوانا است..."
                      class="w-full rounded-lg border-gray-300 text-sm focus:border-red-500 focus:ring-red-500"></textarea>
            @error('rejectReason')
            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror

            <div class="mt-5 flex justify-end gap-2">
                <button type="button" wire:click="closeRejectModal"
                        class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-600 transition hover:bg-gray-50">
                    انصراف
                </button>
                <button type="button" wire:click="submitReject"
                        class="inline-flex items-center gap-1 rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-red-700">
                    <x-heroicon-o-x-circle class="h-5 w-5" />
                    ثبت رد
                </button>
            </div>
        </div>
    </div>
    @endif
</div>
