<div>
    <h1 class="mb-5 text-2xl font-bold">مدیریت پورسانت‌ها</h1>

    <x-product-completion-guide title="راهنمای مالی">
        <div class="space-y-2">
            <p>مبلغ از Snapshot قانون Task محاسبه می‌شود. تأیید، قابل‌پرداخت‌شدن و پرداخت مراحل جدا هستند؛ تأییدکننده نمی‌تواند همان پورسانت را پرداخت کند.</p>
            <p>پیش از تأیید مالی می‌توانید پاداش، جریمه یا تعدیل را با دلیل اجباری ثبت کنید. تمام تغییرات در Audit ذخیره می‌شوند.</p>
        </div>
    </x-product-completion-guide>

    @if($errors->any())
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800" role="alert">
            <ul class="list-inside list-disc">@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="mb-4">
        <label for="admin-commission-status" class="mb-1 block text-sm font-medium text-slate-700">فیلتر وضعیت</label>
        <select id="admin-commission-status" wire:model.live="status" class="rounded-lg border-slate-300">
            <option value="">همه وضعیت‌ها</option>
            @foreach($statuses as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
        </select>
    </div>

    <div class="space-y-4">
        @forelse($commissions as $commission)
            <article class="rounded-xl border bg-white p-4">
                <div class="grid gap-3 md:grid-cols-4">
                    <div><p class="text-xs text-slate-500">پیمانکار</p><p class="font-semibold">{{ $commission->user?->name }}</p></div>
                    <div><p class="text-xs text-slate-500">محصول</p><p class="font-semibold">{{ $commission->task?->product?->title }}</p></div>
                    <div><p class="text-xs text-slate-500">مبلغ / نرخ Task</p><p class="font-semibold text-emerald-700">{{ number_format((float) $commission->total_amount) }} {{ $commission->currency }}</p><p class="text-xs text-slate-500">{{ $commission->task?->commission_rate_percentage }}٪</p></div>
                    <div><p class="text-xs text-slate-500">وضعیت</p><p class="font-semibold">{{ $commission->status->label() }}</p></div>
                </div>

                @can('adjustCommission', $commission)
                    <form wire:submit="adjust('{{ $commission->public_id }}')" class="mt-4 grid gap-3 border-t pt-4 md:grid-cols-4">
                        <div><label for="admin-bonus-{{ $commission->public_id }}" class="mb-1 block text-xs font-medium">پاداش</label><input id="admin-bonus-{{ $commission->public_id }}" type="number" min="0" step="0.01" onwheel="this.blur()" wire:model="bonusAmounts.{{ $commission->public_id }}" class="w-full rounded-lg border-slate-300" placeholder="0"></div>
                        <div><label for="admin-penalty-{{ $commission->public_id }}" class="mb-1 block text-xs font-medium">جریمه</label><input id="admin-penalty-{{ $commission->public_id }}" type="number" min="0" step="0.01" onwheel="this.blur()" wire:model="penaltyAmounts.{{ $commission->public_id }}" class="w-full rounded-lg border-slate-300" placeholder="0"></div>
                        <div><label for="admin-adjustment-{{ $commission->public_id }}" class="mb-1 block text-xs font-medium">تعدیل مثبت یا منفی</label><input id="admin-adjustment-{{ $commission->public_id }}" type="number" step="0.01" onwheel="this.blur()" wire:model="adjustmentAmounts.{{ $commission->public_id }}" class="w-full rounded-lg border-slate-300" placeholder="0"></div>
                        <div><label for="admin-reason-{{ $commission->public_id }}" class="mb-1 block text-xs font-medium">دلیل تغییر مبلغ</label><input id="admin-reason-{{ $commission->public_id }}" type="text" maxlength="2000" wire:model="adjustmentReasons.{{ $commission->public_id }}" class="w-full rounded-lg border-slate-300" required></div>
                        <button wire:loading.attr="disabled" class="rounded-lg border border-indigo-600 px-4 py-2 text-sm font-semibold text-indigo-700 md:col-span-4">ثبت تعدیل مبلغ</button>
                    </form>
                @endcan

                <div class="mt-4 flex flex-wrap items-center gap-3 border-t pt-4">
                    @can('approveCommission', $commission)<button wire:click="approve('{{ $commission->public_id }}')" wire:loading.attr="disabled" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm text-white">تأیید مالی</button>@endcan
                    @can('markPayable', $commission)<button wire:click="markPayable('{{ $commission->public_id }}')" wire:loading.attr="disabled" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm text-white">قابل پرداخت</button>@endcan
                    @can('payCommission', $commission)
                        <label for="transaction-{{ $commission->public_id }}" class="sr-only">شماره پیگیری پرداخت</label>
                        <input id="transaction-{{ $commission->public_id }}" wire:model="transactionIds.{{ $commission->public_id }}" maxlength="255" class="w-48 rounded-lg border-slate-300" placeholder="شماره پیگیری">
                        <button wire:click="pay('{{ $commission->public_id }}')" wire:loading.attr="disabled" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm text-white">ثبت پرداخت</button>
                    @endcan
                </div>
            </article>
        @empty
            <div class="rounded-xl border border-dashed bg-white p-8 text-center text-slate-500">رکوردی وجود ندارد.</div>
        @endforelse
    </div>

    <div class="mt-4">{{ $commissions->links() }}</div>
</div>
