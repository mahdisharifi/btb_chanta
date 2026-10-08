<div>
    <h1 class="mb-2 text-2xl font-bold">بررسی {{ $task->product?->title }}</h1>
    <p class="mb-5 text-sm text-slate-500">نسخه {{ $submission->version_no }}</p>

    <x-product-completion-guide title="راهنمای بررسی">
        <div class="space-y-2">
            <p>تغییرات نسخه را بررسی کنید. تأیید، فرآیند اعمال محتوا و انتشار رسانه را در صف قرار می‌دهد.</p>
            <p>رفع اشتباه پیمانکار پورسانت اضافه ندارد. فقط «تغییر درخواست تأمین‌کننده» پس از رفع، مشمول درصد اصلاح قانون می‌شود.</p>
            @if($task->task_type->value === 'correction')
                <p>Task اصلاح محصول به‌صورت پیش‌فرض با نرخ ۳۰٪ محاسبه می‌شود. فقط در صورت توجیه واقعی می‌توانید کل Task را با نرخ کامل تأیید کنید.</p>
            @endif
        </div>
    </x-product-completion-guide>

    @if($errors->any())
        <div class="mb-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">
            <ul class="list-inside list-disc">
                @foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach
            </ul>
        </div>
    @endif

    <div class="grid gap-5 lg:grid-cols-3">
        <section class="space-y-3 lg:col-span-2">
            @forelse($submission->diff_json ?? [] as $field => $change)
                <article class="rounded-xl border bg-white p-4">
                    <label class="mb-2 flex items-center gap-2 font-bold">
                        <input type="checkbox" wire:model="requestedFields" value="{{ $field }}" class="rounded border-slate-300 text-indigo-600">
                        {{ $field }}
                    </label>
                    <div class="grid gap-3 md:grid-cols-2">
                        <div class="rounded bg-red-50 p-3 text-sm">
                            <p class="mb-1 font-semibold text-red-700">قبل</p>
                            <pre class="overflow-auto whitespace-pre-wrap break-words">{{ is_array($change['before']) ? json_encode($change['before'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : $change['before'] }}</pre>
                        </div>
                        <div class="rounded bg-green-50 p-3 text-sm">
                            <p class="mb-1 font-semibold text-green-700">بعد</p>
                            <pre class="overflow-auto whitespace-pre-wrap break-words">{{ is_array($change['after']) ? json_encode($change['after'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : $change['after'] }}</pre>
                        </div>
                    </div>
                </article>
            @empty
                <div class="rounded-xl border border-dashed bg-white p-6 text-center text-slate-500">تغییری برای نمایش ثبت نشده است.</div>
            @endforelse
        </section>

        <aside class="space-y-4">
            <div class="rounded-xl border bg-white p-4">
                <label for="review-note" class="mb-1.5 block text-sm font-semibold text-slate-700">یادداشت بررسی</label>
                <textarea id="review-note" wire:model="reviewNote" maxlength="2000" class="mb-3 w-full rounded-lg border-slate-300" rows="4" placeholder="یادداشت بررسی"></textarea>

                @if($task->task_type->value === 'correction')
                    @can('outsourcing.approve_full_correction_rate')
                        <label class="mb-4 flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-950">
                            <input type="checkbox" wire:model="approveFullRate" class="mt-0.5 rounded border-amber-400 text-amber-600">
                            <span><strong>تأیید نرخ کامل (۱۰۰٪)</strong><br><span class="text-xs">در حالت عادی این Task با نرخ ۳۰٪ محاسبه می‌شود. انتخاب این گزینه باید دارای توجیه روشن در یادداشت بررسی باشد.</span></span>
                        </label>
                    @endcan
                @endif

                <div class="flex flex-wrap gap-2">
                    <button type="button" wire:click="approve" wire:loading.attr="disabled" class="rounded-lg bg-emerald-600 px-4 py-2 text-white disabled:opacity-60">تأیید</button>
                    <button type="button" wire:click="reject" wire:loading.attr="disabled" class="rounded-lg bg-red-600 px-4 py-2 text-white disabled:opacity-60">رد نهایی</button>
                </div>
            </div>

            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                <label for="revision-classification" class="mb-1.5 block text-sm font-semibold text-amber-950">علت درخواست اصلاح</label>
                <select id="revision-classification" wire:model="revisionClassification" class="mb-3 w-full rounded-lg border-amber-300">
                    @foreach($classifications as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                </select>
                <p class="mb-3 text-xs text-amber-900">فقط «تغییر درخواست تأمین‌کننده» می‌تواند پس از رفع، پورسانت اصلاح دریافت کند.</p>

                <label for="revision-reason" class="mb-1.5 block text-sm font-semibold text-amber-950">توضیح دقیق اصلاح</label>
                <textarea id="revision-reason" wire:model="revisionReason" maxlength="2000" class="mb-3 w-full rounded-lg border-amber-300" rows="4" placeholder="دلیل و اقدام موردنیاز"></textarea>
                <button type="button" wire:click="requestRevision" wire:loading.attr="disabled" class="rounded-lg bg-amber-600 px-4 py-2 text-white disabled:opacity-60">درخواست اصلاح</button>
            </div>
        </aside>
    </div>
</div>
