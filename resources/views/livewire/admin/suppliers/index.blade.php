<div class="p-6 space-y-6">
    <!-- هدر صفحه -->
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 pb-6 border-b border-slate-100 dark:border-slate-800">
        <div class="space-y-1">
            <h1 class="text-xl font-black text-slate-800 dark:text-white tracking-tight flex items-center gap-2">
                <x-heroicon-o-building-office-2 class="w-6 h-6 text-blue-600"/>
                مدیریت تامین‌کنندگان
            </h1>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 font-medium">
                لیست، وضعیت همکاری، تایید مدارک و فیلترهای پیشرفته تامین‌کنندگان سیستم
            </p>
        </div>

        <div class="flex items-center gap-2 self-end lg:self-center">
            <button wire:click="exportExcel" class="whitespace-nowrap flex items-center justify-center gap-1.5 px-3 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-[11px] font-bold transition-all duration-200 shadow-sm">
                <x-heroicon-o-arrow-down-tray class="w-4 h-4"/>
                <span>خروجی اکسل</span>
            </button>

            <a href="{{ route('admin.suppliers.create') }}" class="whitespace-nowrap flex items-center justify-center gap-1.5 px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-[11px] font-bold transition-all duration-200 shadow-sm">
                <x-heroicon-o-plus class="w-4 h-4"/>
                <span>تامین‌کننده جدید</span>
            </a>
        </div>
    </div>

    <!-- اعلان‌ها -->
    @if (session()->has('message'))
    <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-xs font-semibold flex items-center gap-2.5 shadow-sm">
        <x-heroicon-o-check-circle class="w-5 h-5 text-emerald-600"/>
        {{ session('message') }}
    </div>
    @endif

    @if (session()->has('error'))
    <div class="p-3.5 bg-red-50 border border-red-200 text-red-800 rounded-xl text-[11px] font-semibold flex items-center gap-2 shadow-sm">
        <x-heroicon-o-x-circle class="w-5 h-5 text-red-600"/>
        {{ session('error') }}
    </div>
    @endif

    <!-- فیلترها -->
    <div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-xl p-4 shadow-sm">
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3">
            <!-- جستجو -->
            <div class="relative w-full">
                <input wire:model.live.debounce.400ms="search" type="text" placeholder="جستجو در نام شرکت، برند، موبایل..." class="w-full pr-9 pl-3 py-1.5 border-gray-300 focus:ring-blue-500 focus:border-blue-500 text-xs rounded-lg">
                <div class="absolute right-2.5 top-2 text-slate-400">
                    <x-heroicon-o-magnifying-glass class="w-4.5 h-4.5"/>
                </div>
            </div>

            <!-- وضعیت تایید -->
            <select wire:model.live="verification" class="block w-full py-1.5 border-gray-300 focus:ring-blue-500 focus:border-blue-500 text-xs rounded-lg cursor-pointer">
                <option value="">همه وضعیت‌های تایید</option>
                <option value="pending">در انتظار بررسی</option>
                <option value="approved">تایید شده</option>
                <option value="rejected">رد شده</option>
            </select>

            <!-- وضعیت فعالیت -->
            <select wire:model.live="status" class="block w-full py-1.5 border-gray-300 focus:ring-blue-500 focus:border-blue-500 text-xs rounded-lg cursor-pointer">
                <option value="">همه وضعیت‌های فعالیت</option>
                <option value="active">فعال</option>
                <option value="inactive">غیرفعال</option>
            </select>

            <!-- استان -->
            <select wire:model.live="province" class="block w-full py-1.5 border-gray-300 focus:ring-blue-500 focus:border-blue-500 text-xs rounded-lg cursor-pointer">
                <option value="">همه استان‌ها</option>
                @foreach($provinces as $prov)
                <option value="{{ $prov }}">{{ $prov }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <!-- عملیات گروهی -->
    @if(!empty($selectedSuppliers))
    <div class="p-3 bg-blue-50/70 dark:bg-blue-950/20 border border-blue-100 dark:border-blue-900 rounded-xl flex flex-wrap items-center justify-between gap-3">
        <div class="text-[11px] text-blue-800 dark:text-blue-300 font-bold">
            {{ count($selectedSuppliers) }} تامین‌کننده انتخاب شده
        </div>
        <div class="flex items-center gap-2">
            <button wire:click="bulkActivate" class="px-3 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-[10px] font-bold transition-all shadow-sm">
                <span>فعال‌سازی</span>
            </button>
            <button wire:click="bulkDeactivate" class="px-3 py-1 bg-amber-600 hover:bg-amber-700 text-white rounded-lg text-[10px] font-bold transition-all shadow-sm">
                <span>غیرفعال‌سازی</span>
            </button>
            <button wire:click="bulkVerify" class="px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-[10px] font-bold transition-all shadow-sm">
                <span>تایید همه</span>
            </button>
            <button wire:click="bulkReject" class="px-3 py-1 bg-red-600 hover:bg-red-700 text-white rounded-lg text-[10px] font-bold transition-all shadow-sm">
                <span>رد همه</span>
            </button>
        </div>
    </div>
    @endif

    <!-- جدول -->
    <div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-xl shadow-sm overflow-hidden">
        <div class="w-full overflow-x-auto">
            <table class="w-full text-right border-collapse min-w-[1000px]">
                <thead>
                <tr class="bg-slate-50/75 dark:bg-slate-800/50 border-b border-slate-100 text-slate-600 dark:text-slate-300 text-[10px] font-bold">
                    <th class="p-2.5 w-10 text-center">
                        <input wire:model.live="selectAll" type="checkbox" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 w-3.5 h-3.5">
                    </th>
                    <th class="p-2.5 w-12 cursor-pointer hover:bg-slate-100/50" wire:click="sortBy('id')">
                        <div class="flex items-center gap-1 justify-center">
                            شناسه
                            @if($sortField === 'id')
                            <span class="text-blue-600">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                            @endif
                        </div>
                    </th>
                    <th class="p-2.5 cursor-pointer hover:bg-slate-100/50" wire:click="sortBy('company_name')">
                        <div class="flex items-center gap-1">
                            تامین‌کننده
                            @if($sortField === 'company_name')
                            <span class="text-blue-600">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                            @endif
                        </div>
                    </th>
                    <th class="p-2.5">کاربر متصل</th>
                    <th class="p-2.5 w-28">تماس</th>
                    <th class="p-2.5 w-24">منطقه</th>
                    <th class="p-2.5 w-36 text-center">وضعیت تایید</th>
                    <th class="p-2.5 w-20 text-center">فعال</th>
                    <!-- ستون عملیات ثابت (sticky) سمت چپ -->
                    <th class="p-2.5 w-36 text-center sticky left-0 z-20 bg-slate-50 dark:bg-slate-800 shadow-[-8px_0_8px_-6px_rgba(0,0,0,0.08)]">عملیات</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-[11px] text-slate-700 dark:text-slate-300">
                @forelse($suppliers as $supplier)
                <tr class="group hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-all duration-150">
                    <!-- چک باکس -->
                    <td class="p-2.5 text-center">
                        <input wire:model.live="selectedSuppliers" type="checkbox" value="{{ $supplier->id }}" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 w-3.5 h-3.5">
                    </td>

                    <!-- شناسه -->
                    <td class="p-2.5 font-mono text-[10px] text-slate-500 text-center">{{ $supplier->id }}</td>

                    <!-- اطلاعات تامین‌کننده -->
                    <td class="p-2.5">
                        <div class="font-bold text-slate-800 dark:text-white text-[11.5px]">
                            {{ $supplier->company_name }}
                        </div>
                        @if($supplier->brand_name)
                        <div class="text-[9.5px] text-slate-400 mt-0.5">
                            برند: {{ $supplier->brand_name }}
                        </div>
                        @endif
                    </td>

                    <!-- کاربر متصل -->
                    <td class="p-2.5">
                        @if($supplier->user)
                        <div class="font-semibold text-slate-800 dark:text-white text-[10.5px]">
                            {{ $supplier->user->name }}
                        </div>
                        <div class="text-[9px] text-slate-400 mt-0.5 font-mono">
                            {{ $supplier->user->email }}
                        </div>
                        @else
                        <span class="text-slate-400 text-[9px]">بدون کاربر</span>
                        @endif
                    </td>

                    <!-- تماس -->
                    <td class="p-2.5 whitespace-nowrap">
                        <div class="font-mono text-[10.5px] text-slate-800">{{ $supplier->mobile ?? '---' }}</div>
                        <div class="font-mono text-[9px] text-slate-400 mt-0.5">{{ $supplier->phone ?? '---' }}</div>
                    </td>

                    <!-- منطقه -->
                    <td class="p-2.5 whitespace-nowrap">
                        <div class="font-semibold text-[10.5px] text-slate-800">{{ $supplier->province ?? '---' }}</div>
                        <div class="text-[9px] text-slate-400 mt-0.5">{{ $supplier->city ?? '---' }}</div>
                    </td>

                    <!-- وضعیت تایید -->
                    <td class="p-2.5">
                        @switch($supplier->verification_status)
                        @case('approved')
                        <div class="flex flex-col items-center gap-1">
                                    <span class="inline-flex items-center gap-1 px-2 py-1 text-[10px] rounded-full bg-green-100 text-green-800 font-bold">
                                        <x-heroicon-o-check-badge class="w-3.5 h-3.5"/>
                                        تایید شده
                                    </span>
                            @if($supplier->verified_at)
                            <div class="text-[8px] text-slate-500">
                                {{ \Morilog\Jalali\Jalalian::fromDateTime($supplier->verified_at)->format('Y/m/d H:i') }}
                            </div>
                            @endif
                            @if($supplier->verifier)
                            <div class="text-[8px] text-slate-400">
                                توسط: {{ $supplier->verifier->name }}
                            </div>
                            @endif
                        </div>
                        @break

                        @case('rejected')
                        <div class="flex flex-col items-center gap-1">
                                    <span class="inline-flex items-center gap-1 px-2 py-1 text-[10px] rounded-full bg-red-100 text-red-800 font-bold">
                                        <x-heroicon-o-x-circle class="w-3.5 h-3.5"/>
                                        رد شده
                                    </span>
                            @if($supplier->rejection_reason)
                            <div class="text-[8px] text-red-600 max-w-[120px] truncate" title="{{ $supplier->rejection_reason }}">
                                دلیل: {{ $supplier->rejection_reason }}
                            </div>
                            @endif
                        </div>
                        @break

                        @default
                        <div class="flex justify-center">
                                    <span class="inline-flex items-center gap-1 px-2 py-1 text-[10px] rounded-full bg-yellow-100 text-yellow-800 font-bold">
                                        <x-heroicon-o-clock class="w-3.5 h-3.5"/>
                                        در انتظار
                                    </span>
                        </div>
                        @endswitch
                    </td>

                    <!-- وضعیت فعالیت -->
                    <td class="p-2.5 text-center">
                        <button wire:click="toggleActive({{ $supplier->id }})" class="relative inline-flex items-center h-5 w-9 rounded-full transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1 {{ $supplier->is_active ? 'bg-blue-600' : 'bg-slate-300 dark:bg-slate-600' }}">
                            <span class="inline-block w-4 h-4 transform rounded-full bg-white transition-transform duration-200 shadow-sm {{ $supplier->is_active ? '-translate-x-4' : 'translate-x-0.5' }}"></span>
                        </button>
                    </td>

                    <!-- عملیات (ستون ثابت sticky) -->
                    <td class="p-2.5 sticky left-0 z-10 bg-white group-hover:bg-slate-50 dark:bg-slate-900 dark:group-hover:bg-slate-800/40 shadow-[-8px_0_8px_-6px_rgba(0,0,0,0.08)] transition-colors">
                        <div class="flex items-center justify-center gap-0.5">
                            <!-- تایید -->
                            @if($supplier->verification_status === 'pending' || $supplier->verification_status === 'rejected')
                            <button wire:click="approveSupplier({{ $supplier->id }})" class="p-1.5 text-green-600 bg-green-50 hover:bg-green-100 rounded-md transition-all" title="تایید">
                                <x-heroicon-o-check-circle class="w-4 h-4"/>
                            </button>
                            @endif

                            <!-- رد -->
                            @if($supplier->verification_status === 'pending' || $supplier->verification_status === 'approved')
                            <button wire:click="openRejectModal({{ $supplier->id }})" class="p-1.5 text-red-600 bg-red-50 hover:bg-red-100 rounded-md transition-all" title="رد">
                                <x-heroicon-o-x-circle class="w-4 h-4"/>
                            </button>
                            @endif

                            <!-- برگشت به انتظار -->
                            @if($supplier->verification_status === 'approved' || $supplier->verification_status === 'rejected')
                            <button wire:click="setPending({{ $supplier->id }})" class="p-1.5 text-amber-600 bg-amber-50 hover:bg-amber-100 rounded-md transition-all" title="برگشت به انتظار">
                                <x-heroicon-o-arrow-path class="w-4 h-4"/>
                            </button>
                            @endif

                            <!-- ویرایش -->
                            <a href="{{ route('admin.suppliers.edit', $supplier->id) }}" class="p-1.5 text-blue-600 bg-blue-50 hover:bg-blue-100 rounded-md transition-all" title="ویرایش">
                                <x-heroicon-o-pencil-square class="w-4 h-4"/>
                            </a>

                            <!-- حذف -->
                            <button wire:click="deleteSupplier({{ $supplier->id }})" wire:confirm="آیا مطمئن هستید که می‌خواهید این تامین‌کننده را حذف کنید؟" class="p-1.5 text-slate-500 bg-slate-100 hover:bg-red-100 hover:text-red-600 rounded-md transition-all" title="حذف">
                                <x-heroicon-o-trash class="w-4 h-4"/>
                            </button>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="p-8 text-center">
                        <div class="flex flex-col items-center gap-3 text-slate-400">
                            <x-heroicon-o-inbox class="w-12 h-12"/>
                            <p class="text-sm font-semibold">هیچ تامین‌کننده‌ای با مشخصات جستجو شده یافت نشد.</p>
                        </div>
                    </td>
                </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="px-4 py-3 border-t border-slate-100 dark:border-slate-800">
            {{ $suppliers->links() }}
        </div>
    </div>

    <!-- مودال رد درخواست -->
    @if($showRejectModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
        <div class="bg-white dark:bg-slate-900 rounded-xl shadow-2xl max-w-md w-full mx-4 overflow-hidden">
            <!-- هدر مودال -->
            <div class="p-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
                <h3 class="text-sm font-bold text-slate-800 dark:text-white flex items-center gap-2">
                    <x-heroicon-o-x-circle class="w-5 h-5 text-red-600"/>
                    رد درخواست تامین‌کننده
                </h3>
                <button wire:click="cancelReject" class="text-slate-400 hover:text-slate-600 transition-colors">
                    <x-heroicon-o-x-mark class="w-5 h-5"/>
                </button>
            </div>

            <!-- محتوای مودال -->
            <div class="p-4 space-y-3">
                <p class="text-xs text-slate-600 dark:text-slate-400">
                    لطفاً دلیل رد درخواست را وارد کنید. این دلیل برای تامین‌کننده نمایش داده خواهد شد.
                </p>

                <div>
                    <label class="block text-[10px] font-bold text-slate-700 dark:text-slate-300 mb-1">
                        دلیل رد <span class="text-red-600">*</span>
                    </label>
                    <textarea wire:model="rejectReason" rows="4" class="w-full px-3 py-2 border-gray-300 focus:ring-red-500 focus:border-red-500 text-xs rounded-lg resize-none" placeholder="مدارک ناقص، اطلاعات نادرست، و..."></textarea>
                    @error('rejectReason')
                    <p class="text-[10px] text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- دکمه‌های عملیات -->
            <div class="p-4 bg-slate-50 dark:bg-slate-800/50 flex items-center justify-end gap-2">
                <button wire:click="cancelReject" class="px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-lg text-xs font-bold transition-all">
                    انصراف
                </button>
                <button wire:click="confirmReject" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-xs font-bold transition-all flex items-center gap-1">
                    <x-heroicon-o-check class="w-4 h-4"/>
                    تایید رد
                </button>
            </div>
        </div>
    </div>
    @endif
</div>
