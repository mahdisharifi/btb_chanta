<div>
    <h1 class="mb-5 text-2xl font-bold">پورسانت‌های بررسی‌شده</h1>

    <x-product-completion-guide title="راهنمای تعدیل مبلغ">
        <div class="space-y-2">
            <p>فقط پورسانت Taskهایی نمایش داده می‌شود که شما بازبین آن‌ها بوده‌اید.</p>
            <p>تا پیش از تأیید مالی می‌توانید پاداش، جریمه یا تعدیل را با دلیل روشن ثبت کنید. این تغییر در سوابق مالی ثبت می‌شود و اختیار تأیید مالی یا پرداخت به شما نمی‌دهد.</p>
        </div>
    </x-product-completion-guide>

    @if($errors->any())
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800" role="alert">
            <ul class="list-inside list-disc">@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="mb-4">
        <label for="reviewed-commission-status" class="mb-1 block text-sm font-medium text-slate-700">فیلتر وضعیت</label>
        <select id="reviewed-commission-status" wire:model.live="status" class="rounded-lg border-slate-300">
            <option value="">همه وضعیت‌ها</option>
            @foreach($statuses as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
        </select>
    </div>

    <div class="space-y-4">
        @forelse($commissions as $commission)
            <article class="rounded-xl border bg-white p-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="font-bold">{{ $commission->task?->product?->title }}</h2>
                        <p class="mt-1 text-sm text-slate-500">پیمانکار: {{ $commission->user?->name }} · نرخ Task: {{ $commission->task?->commission_rate_percentage }}٪</p>
                    </div>
                    <div class="text-left">
                        <p class="font-bold text-emerald-700">{{ number_format((float) $commission->total_amount) }} {{ $commission->currency }}</p>
                        <p class="text-xs text-slate-500">{{ $commission->status->label() }}</p>
                    </div>
                </div>

                @can('adjustCommission', $commission)
                    <form wire:submit="adjust('{{ $commission->public_id }}')" class="mt-4 grid gap-3 border-t pt-4 md:grid-cols-4">
                        <div><label for="reviewer-bonus-{{ $commission->public_id }}" class="mb-1 block text-xs font-medium">پاداش</label><input id="reviewer-bonus-{{ $commission->public_id }}" type="number" min="0" step="0.01" onwheel="this.blur()" wire:model="bonusAmounts.{{ $commission->public_id }}" class="w-full rounded-lg border-slate-300" placeholder="0"></div>
                        <div><label for="reviewer-penalty-{{ $commission->public_id }}" class="mb-1 block text-xs font-medium">جریمه</label><input id="reviewer-penalty-{{ $commission->public_id }}" type="number" min="0" step="0.01" onwheel="this.blur()" wire:model="penaltyAmounts.{{ $commission->public_id }}" class="w-full rounded-lg border-slate-300" placeholder="0"></div>
                        <div><label for="reviewer-adjustment-{{ $commission->public_id }}" class="mb-1 block text-xs font-medium">تعدیل مثبت یا منفی</label><input id="reviewer-adjustment-{{ $commission->public_id }}" type="number" step="0.01" onwheel="this.blur()" wire:model="adjustmentAmounts.{{ $commission->public_id }}" class="w-full rounded-lg border-slate-300" placeholder="0"></div>
                        <div><label for="reviewer-reason-{{ $commission->public_id }}" class="mb-1 block text-xs font-medium">دلیل تغییر مبلغ</label><input id="reviewer-reason-{{ $commission->public_id }}" type="text" maxlength="2000" wire:model="adjustmentReasons.{{ $commission->public_id }}" class="w-full rounded-lg border-slate-300" required></div>
                        <button wire:loading.attr="disabled" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white md:col-span-4">ثبت تعدیل مبلغ</button>
                    </form>
                @endcan
            </article>
        @empty
            <div class="rounded-xl border border-dashed bg-white p-8 text-center text-slate-500">پورسانتی برای Taskهای بررسی‌شده شما وجود ندارد.</div>
        @endforelse
    </div>

    <div class="mt-4">{{ $commissions->links() }}</div>
</div>
