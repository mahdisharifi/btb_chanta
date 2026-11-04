<div class="space-y-6" dir="rtl">
    <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">بررسی محصولات شعبه</h1>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">شعبهٔ جاری: {{ $this->currentSupplier->branchLabel() }}</p>
    </div>

    @if(!$this->currentSupplier->isBranch())
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-5 text-sm leading-7 text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-100">
            فهرست تأیید و رد برای شعبهٔ اصلی نمایش داده نمی‌شود. ابتدا یک شعبهٔ جدید بسازید یا از انتخابگر بالای صفحه وارد یکی از شعبه‌ها شوید.
            <a href="{{ route('supplier.branches.index') }}" class="mt-3 inline-flex min-h-11 items-center font-semibold underline underline-offset-4">مدیریت شعبه‌ها</a>
        </div>
    @else
        <p class="text-sm text-gray-600 dark:text-gray-300">این فهرست فقط درخواست‌های در انتظار بررسی را نشان می‌دهد. با تأیید، نسخه‌ای مستقل از محصول ساخته می‌شود و حالت فعال یا غیرفعال مطابق شعبهٔ اصلی حفظ می‌شود؛ قیمت و تنظیمات را بعد از آن تکمیل کنید. فایل‌های تصویری مفقود مانع اضافه‌شدن محصول نمی‌شوند.</p>

        <ul class="space-y-3" aria-label="محصولات قابل بررسی">
            @forelse($this->transfers as $transfer)
                <li wire:key="transfer-{{ $transfer->id }}" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <h2 class="break-words font-semibold text-gray-900 dark:text-white">{{ $transfer->sourceProduct?->title ?? 'محصول حذف‌شده' }}</h2>
                            <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                                @if($transfer->sourceProduct?->sku)<span>کد: {{ $transfer->sourceProduct->sku }}</span>@endif
                                @if($transfer->sourceProduct?->category)<span>دسته: {{ $transfer->sourceProduct->category->name }}</span>@endif
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <button type="button" wire:click="accept({{ $transfer->id }})" wire:loading.attr="disabled" wire:target="accept({{ $transfer->id }})"
                                class="min-h-11 rounded-lg bg-emerald-600 px-4 text-sm font-semibold text-white hover:bg-emerald-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 disabled:opacity-60">تأیید و افزودن</button>
                            <button type="button" wire:click="reject({{ $transfer->id }})" wire:confirm="این محصول به شعبه اضافه نشود؟" wire:loading.attr="disabled" wire:target="reject({{ $transfer->id }})"
                                class="min-h-11 rounded-lg border border-red-300 px-4 text-sm font-semibold text-red-700 hover:bg-red-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600 disabled:opacity-60 dark:border-red-800 dark:text-red-300 dark:hover:bg-red-950/40">رد</button>
                        </div>
                    </div>
                </li>
            @empty
                <li class="rounded-xl border border-dashed border-gray-300 bg-white p-8 text-center text-sm text-gray-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-400">محصولی برای بررسی وجود ندارد.</li>
            @endforelse
        </ul>

        <div>{{ $this->transfers->links() }}</div>
    @endif
</div>
