<div wire:poll.10s class="min-w-0 w-full print:hidden">
@if (!$status['available'] || $status['remaining'] > 0 || $status['failed'] > 0)
<section class="mb-4 min-w-0 w-full rounded-xl border border-gray-200 bg-white p-3 text-sm [overflow-wrap:anywhere] dark:border-gray-700 dark:bg-gray-900" aria-label="وضعیت عملیات شعبه">
    <div class="flex min-w-0 flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
        <p class="font-semibold text-gray-800 dark:text-gray-100">وضعیت عملیات شعبهٔ فعال</p>
        @if ($status['available'])
            <p role="status" class="text-gray-600 dark:text-gray-300">باقی‌مانده: {{ $status['remaining'] }} عملیات · ناموفق: {{ $status['failed'] }}</p>
        @else
            <p role="status" class="text-amber-700 dark:text-amber-300">وضعیت عملیات در دسترس نیست.</p>
        @endif
    </div>
    @if ($status['groups'])
        <p class="mt-2 leading-6 text-gray-600 dark:text-gray-300">موضوع عملیات: {{ implode('، ', array_unique(array_column($status['groups'], 'label'))) }}</p>
        <details class="mt-2">
            <summary class="min-h-11 cursor-pointer rounded py-3 text-indigo-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500 dark:text-indigo-300">جزئیات عملیات و درخواست مرتبط</summary>
            <ul class="grid min-w-0 grid-cols-1 gap-2 sm:grid-cols-2" role="list">
                @foreach ($status['groups'] as $group)
                    <li wire:key="job-type-{{ $group['type'] }}" class="min-w-0 rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                        <p class="font-medium text-gray-800 dark:text-gray-100">{{ $group['label'] }}: {{ $group['remaining'] }} باقی‌مانده</p>
                        <p class="mt-1 text-xs leading-6 text-gray-600 dark:text-gray-300">{{ $group['description'] }}</p>
                        <p class="mt-1 text-xs leading-6 text-gray-600 dark:text-gray-300">در انتظار: {{ $group['waiting'] }} · در حال پردازش: {{ $group['processing'] }} · با تأخیر: {{ $group['delayed'] }} · ناموفق: {{ $group['failed'] }}</p>
                    </li>
                @endforeach
            </ul>
            <p class="mt-2 text-xs leading-6 text-gray-500">هر درخواست شما ممکن است در چند عملیات پردازش شود و هر عملیات چند محصول را پوشش دهد. این تعداد، تعداد درخواست‌ها یا درصد پیشرفت محصولات نیست. وضعیت هر ۱۰ ثانیه به‌روزرسانی می‌شود.</p>
        </details>
    @endif
</section>
@endif
</div>
