<div>
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3"><h1 class="text-2xl font-bold">داشبورد برون‌سپاری محتوا</h1><a href="{{ route('admin.outsourcing.tasks.index') }}" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm text-white">مدیریت تسک‌ها</a></div>
    <x-product-completion-guide title="راهنمای داشبورد">
        <p>این صفحه وضعیت عملیاتی تسک‌های تکمیل محتوا و جمع مبالغ پورسانت را نمایش می‌دهد. مبلغ‌ها فقط برای کاربران دارای دسترسی مالی قابل استفاده‌اند و هیچ تغییری در قیمت محصول ایجاد نمی‌کنند.</p>
    </x-product-completion-guide>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach(['draft' => 'پیش‌نویس', 'assigned' => 'تخصیص‌یافته', 'in_progress' => 'در حال انجام', 'submitted' => 'در انتظار بررسی'] as $status => $label)
            <div class="rounded-xl border bg-white p-5"><p class="text-sm text-slate-500">{{ $label }}</p><p class="mt-2 text-3xl font-bold">{{ number_format((int) ($counts[$status] ?? 0)) }}</p></div>
        @endforeach
        <div class="rounded-xl border border-red-200 bg-red-50 p-5"><p class="text-sm text-red-700">تسک تأخیردار</p><p class="mt-2 text-3xl font-bold text-red-800">{{ number_format($overdueCount) }}</p></div>
        <div class="rounded-xl border bg-white p-5"><p class="text-sm text-slate-500">پورسانت قابل پرداخت</p><p class="mt-2 text-xl font-bold">{{ number_format((float) $payableAmount) }} ریال</p></div>
        <div class="rounded-xl border bg-white p-5"><p class="text-sm text-slate-500">پورسانت پرداخت‌شده</p><p class="mt-2 text-xl font-bold">{{ number_format((float) $paidAmount) }} ریال</p></div>
    </div>
</div>
