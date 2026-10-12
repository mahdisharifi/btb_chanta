<div>
    <h1 class="mb-2 text-2xl font-bold">بررسی {{ $task->product?->title }}</h1>
    <p class="mb-5 text-sm text-slate-500">نسخه {{ $submission->version_no }}</p>

    <x-product-completion-guide title="راهنمای بررسی">
        <div class="space-y-2">
            <p>تغییرات نسخه را بررسی کنید. تأیید، فرآیند اعمال محتوا و انتشار رسانه را در صف قرار می‌دهد.</p>
            <p>رفع اشتباه پیمانکار پورسانت اضافه ندارد. فقط «تغییر درخواست تأمین‌کننده» پس از رفع، مشمول درصد اصلاح قانون می‌شود.</p>
            <p>نرخ هر تصویر و ویژگی با اطلاعات آغاز تسک مقایسه می‌شود: اطلاعات تازه ۱۰۰٪ و اصلاح مقدار قبلی ۳۰٪. توضیحات کوتاه و کامل یک واحد هستند؛ اگر یکی از آن‌ها تازه باشد، این واحد نرخ ۱۰۰٪ می‌گیرد.</p>
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
            <div class="rounded-xl border border-indigo-200 bg-indigo-50 p-4 text-sm text-indigo-950">
                <p class="font-semibold">{{ $submission->version_no > 1 ? 'تغییرات این نسخه نسبت به ارسال قبلی' : 'مقایسه محتوای قبلی با نسخه ارسالی' }}</p>
                <p class="mt-1">مقادیر پیشنهادی با نشان زرد مشخص شده‌اند. برای مشاهده تصویر در اندازه کامل، روی آن کلیک کنید.</p>
                <p class="mt-1">برای درخواست اصلاح بخشی از محتوا، گزینه کنار عنوان همان بخش را انتخاب کنید.</p>
            </div>
            @forelse($submission->diff_json ?? [] as $field => $change)
                <article wire:key="review-field-{{ $field }}" class="rounded-xl border bg-white p-4">
                    <label class="mb-2 flex items-center gap-2 font-bold">
                        <input type="checkbox" wire:model="requestedFields" value="{{ $field }}" class="rounded border-slate-300 text-indigo-600">
                        {{ $fieldLabels[$field] ?? 'سایر تغییرات' }}
                    </label>
                    <div class="grid gap-3 md:grid-cols-2">
                        <div class="rounded bg-red-50 p-3 text-sm">
                            <p class="mb-3 font-semibold text-red-700">محتوای قبلی</p>
                            @include('livewire.reviewer.task.partials.review-value', ['value' => $change['before'] ?? null, 'side' => 'before'])
                        </div>
                        <div class="rounded bg-green-50 p-3 text-sm">
                            <p class="mb-3 font-semibold text-green-700">نسخه ارسالی محتواکار</p>
                            @include('livewire.reviewer.task.partials.review-value', ['value' => $change['after'] ?? null, 'side' => 'after'])
                        </div>
                    </div>
                </article>
            @empty
                <div class="rounded-xl border border-dashed bg-white p-6 text-center text-slate-500">تغییری برای نمایش ثبت نشده است.</div>
            @endforelse

            @if($submission->version_no > 1)
                <details class="rounded-xl border border-slate-200 bg-white p-4">
                    <summary class="cursor-pointer font-semibold text-indigo-700">مشاهده کل محتوای ارسالی، شامل بخش‌های بدون تغییر</summary>
                    <p class="mt-3 text-sm text-slate-600">تأیید این نسخه، تمام محتوای ارسالی زیر را برای اعمال روی محصول می‌پذیرد.</p>
                    <div class="mt-4 space-y-4">
                        @foreach($fieldLabels as $previewField => $previewLabel)
                            @if(array_key_exists($previewField, $submission->payload_json ?? []))
                                <section class="rounded-lg border bg-slate-50 p-4">
                                    <h2 class="mb-3 font-semibold">{{ $previewLabel }}</h2>
                                    @include('livewire.reviewer.task.partials.review-value', [
                                        'field' => $previewField,
                                        'value' => $submission->payload_json[$previewField],
                                        'change' => ['before' => [], 'after' => $submission->payload_json[$previewField]],
                                        'side' => 'after',
                                    ])
                                </section>
                            @endif
                        @endforeach
                    </div>
                </details>
            @endif
        </section>

        <aside class="space-y-4">
            <section class="rounded-xl border border-indigo-200 bg-white p-4" aria-labelledby="commission-basis-heading">
                <h2 id="commission-basis-heading" class="font-bold text-indigo-900">مبنای خودکار پورسانت</h2>
                <p class="mt-2 text-xs leading-6 text-slate-600">مقایسه با اطلاعات آغاز تسک؛ اصلاح و ارسال مجدد پیمانکار نرخ اطلاعات تازه را کاهش نمی‌دهد.</p>
                <ul class="mt-3 space-y-2 text-sm">
                    @foreach($commissionRateUnits as $contentType => $ratedUnits)
                        @foreach($ratedUnits as $ratedUnit)
                            @php
                                $unitLabel = match ($contentType) {
                                    'attribute' => $attributeNames[$ratedUnit['attribute_id']] ?? 'ویژگی محصول',
                                    'image' => str_ends_with($ratedUnit['key'], ':removed') ? 'حذف تصویر قبلی' : 'تصویر افزوده‌شده '.$loop->iteration,
                                    'description' => 'توضیحات کوتاه و کامل',
                                    'title' => 'عنوان محصول',
                                    default => 'محتوای محصول',
                                };
                            @endphp
                            <li class="flex items-center justify-between gap-3 rounded-lg bg-slate-50 p-2">
                                <span>{{ $unitLabel }}<span class="mt-1 block text-xs text-slate-500">{{ $ratedUnit['kind'] === 'new' ? 'اطلاعات تازه' : 'اصلاح مقدار قبلی' }}</span></span>
                                <strong class="{{ $ratedUnit['kind'] === 'new' ? 'text-emerald-700' : 'text-amber-700' }}">{{ $ratedUnit['kind'] === 'new' ? '۱۰۰٪' : '۳۰٪' }}</strong>
                            </li>
                        @endforeach
                    @endforeach
                </ul>
                <div class="mt-4 border-t pt-3">
                    <p class="text-xs text-slate-500">برآورد پورسانت طبق قانون ثبت‌شده</p>
                    <p class="mt-1 font-bold text-indigo-900">{{ number_format((float) $commissionPreview['total_amount']) }} {{ $task->currency }}</p>
                    @if(data_get($task->commission_rule_snapshot_json, 'max_amount') !== null)
                        <p class="mt-1 text-xs text-slate-500">سقف کل: {{ number_format((float) data_get($task->commission_rule_snapshot_json, 'max_amount')) }} {{ $task->currency }}</p>
                    @endif
                </div>
            </section>
            <div class="rounded-xl border bg-white p-4">
                <label for="review-note" class="mb-1.5 block text-sm font-semibold text-slate-700">یادداشت بررسی</label>
                <textarea id="review-note" wire:model="reviewNote" maxlength="2000" class="mb-3 w-full rounded-lg border-slate-300" rows="4" placeholder="یادداشت بررسی"></textarea>

                @if($hasCorrections)
                    @can('outsourcing.approve_full_correction_rate')
                        <label class="mb-4 flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-950">
                            <input type="checkbox" wire:model="approveFullRate" class="mt-0.5 rounded border-amber-400 text-amber-600">
                            <span><strong>استثنا: نرخ کامل برای مقادیر اصلاح‌شده</strong><br><span class="text-xs">اطلاعات تازه خودکار نرخ کامل می‌گیرد. این گزینه فقط نرخ اصلاح مقدار قبلی را به ۱۰۰٪ تغییر می‌دهد و نیازمند دلیل روشن در یادداشت بررسی است؛ برآورد بالا بدون این استثناست.</span></span>
                        </label>
                    @endcan
                @endif

                <div class="flex flex-wrap gap-2">
                    <button type="button" wire:click="approve" wire:loading.attr="disabled" class="rounded-lg bg-emerald-600 px-4 py-2 text-white disabled:opacity-60">تأیید</button>
                    <button type="button" wire:click="reject" wire:loading.attr="disabled" class="rounded-lg bg-red-600 px-4 py-2 text-white disabled:opacity-60">بازگشت برای اصلاح</button>
                </div>
            </div>

            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                <label for="revision-classification" class="mb-1.5 block text-sm font-semibold text-amber-950">علت درخواست اصلاح</label>
                <select id="revision-classification" wire:model="revisionClassification" class="mb-3 w-full rounded-lg border-amber-300">
                    @foreach($classifications as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                </select>
                <p class="mb-3 text-xs text-amber-900">فقط «تغییر درخواست تأمین‌کننده» می‌تواند پس از رفع، پورسانت اصلاح دریافت کند.</p>

                <p class="mb-3 text-xs text-amber-900">فیلدهای نیازمند اصلاح را انتخاب کنید؛ اگر فیلدی انتخاب نشود، کل محدوده مجاز تسک برای اصلاح برگردانده می‌شود. درج دلیل اصلاح یا یادداشت بررسی الزامی است.</p>

                <label for="revision-reason" class="mb-1.5 block text-sm font-semibold text-amber-950">توضیح دقیق اصلاح</label>
                <textarea id="revision-reason" wire:model="revisionReason" maxlength="2000" class="mb-3 w-full rounded-lg border-amber-300" rows="4" placeholder="دلیل و اقدام موردنیاز"></textarea>
                <button type="button" wire:click="requestRevision" wire:loading.attr="disabled" class="rounded-lg bg-amber-600 px-4 py-2 text-white disabled:opacity-60">درخواست اصلاح</button>
            </div>
        </aside>
    </div>
</div>
