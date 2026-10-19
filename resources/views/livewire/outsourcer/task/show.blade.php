<div>
    <a href="{{ $backUrl }}" class="mb-4 inline-flex min-h-10 items-center text-sm font-semibold text-indigo-700 hover:text-indigo-900">← بازگشت به فهرست تسک‌ها</a>

    <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold">{{ $task->product?->title }}</h1>
            <p class="mt-1 text-sm text-slate-600">{{ $categoryPath ?: 'بدون دسته‌بندی' }} · {{ $task->task_type->label() }}</p>
        </div>
        <span class="rounded-full bg-slate-100 px-3 py-1 text-sm text-slate-700">{{ $task->status->label() }}</span>
    </div>

    @if($isPreview)
        <x-product-completion-guide title="پیش‌نمایش تسک">
            <p>پیش از پذیرش، محدودهٔ کار، اطلاعات مرجع، مهلت و شرایط تسک را بررسی کنید. با پذیرش، تسک به شما اختصاص می‌یابد. مدت انجام، در صورت ثبت، از زمان پذیرش محاسبه می‌شود.</p>
        </x-product-completion-guide>
    @endif

    <section class="mb-5 rounded-xl border bg-white p-5" aria-labelledby="task-terms-heading">
        <h2 id="task-terms-heading" class="mb-4 text-lg font-bold">شرایط و محدودهٔ کار</h2>
        <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div><dt class="text-xs text-slate-500">نوع فعالیت</dt><dd class="mt-1 font-medium">{{ $task->task_type->label() }}</dd></div>
            @if($isPreview)
                <div><dt class="text-xs text-slate-500">مهلت پذیرش</dt><dd class="mt-1 font-medium">{{ $availabilityDeadlineLabel }}</dd></div>
                <div><dt class="text-xs text-slate-500">مدت انجام پس از پذیرش</dt><dd class="mt-1 font-medium">{{ $completionDurationLabel ?? ($task->due_at ? 'مدت جداگانه‌ای ثبت نشده؛ مهلت ثابت تسک ملاک است.' : 'مدت و مهلت انجام ثبت نشده است.') }}</dd></div>
            @else
                <div><dt class="text-xs text-slate-500">مهلت انجام</dt><dd class="mt-1 font-medium">{{ $task->completionDeadlineLabel() }}</dd></div>
            @endif
            <div><dt class="text-xs text-slate-500">تعداد اصلاح {{ $isPreview ? 'مجاز' : 'باقی‌مانده' }}</dt><dd class="mt-1 font-medium">{{ $isPreview ? $task->revision_limit : $task->remainingRevisions() }}</dd></div>
            @if($isPreview)
                <div>
                    <dt class="text-xs text-slate-500">مبنای ثبت‌شدهٔ قیمت‌گذاری</dt>
                    <dd class="mt-1 font-medium"><bdi>{{ \Morilog\Jalali\CalendarUtils::convertNumbers(number_format((float) $task->quoted_base_amount)) }}</bdi> <bdi>{{ $task->currency }}</bdi></dd>
                    <p class="mt-1 text-xs leading-5 text-slate-500">این مبلغ پایهٔ محاسبه است و مبلغ قطعی قابل پرداخت پس از بررسی کار مشخص می‌شود.</p>
                </div>
            @endif
        </dl>

        <div class="mt-5 border-t pt-4">
            <h3 class="mb-2 text-sm font-semibold">موارد مورد انتظار</h3>
            @php
                $scopeLabels = [
                    'title' => 'عنوان محصول',
                    'short_description' => 'توضیح کوتاه',
                    'full_description' => 'توضیح کامل',
                    'attributes' => 'مشخصات فنی',
                    'gallery' => 'تصاویر محصول',
                ];
            @endphp
            <div class="flex flex-wrap gap-2">
                @foreach($scopeLabels as $field => $label)
                    @if(data_get($task->scope_json, $field))
                        <span class="rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-sm text-indigo-800">{{ $label }}</span>
                    @endif
                @endforeach
            </div>
        </div>
    </section>

    @if($canViewProductReference)
    <section class="mb-5 rounded-xl border bg-white p-5" aria-labelledby="product-reference-heading">
        <h2 id="product-reference-heading" class="mb-4 text-lg font-bold">اطلاعات مرجع محصول</h2>
        <dl class="mb-4 grid gap-3 text-sm sm:grid-cols-3">
            <div><dt class="text-xs text-slate-500">دسته‌بندی</dt><dd class="mt-1">{{ $categoryPath ?: 'بدون دسته‌بندی' }}</dd></div>
            <div><dt class="text-xs text-slate-500">شناسهٔ کالا</dt><dd class="mt-1"><bdi>{{ $task->product?->sku ?: 'ثبت نشده' }}</bdi></dd></div>
        </dl>

        @if($images->isNotEmpty())
            <div class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5" aria-label="تصاویر مرجع محصول">
                @foreach($images as $image)
                    <a href="{{ route('outsourcing.gallery.preview', [$task, $image]) }}" target="_blank" rel="noopener noreferrer" class="rounded-lg border bg-white p-2 focus:outline-none focus:ring-2 focus:ring-indigo-500" aria-label="مشاهدهٔ تصویر مرجع {{ $loop->iteration }} در تب جدید">
                        <img src="{{ route('outsourcing.gallery.preview', [$task, $image]) }}" alt="{{ $image->alt_text ?: $task->product?->title }}" loading="lazy" class="h-28 w-full rounded object-contain">
                    </a>
                @endforeach
            </div>
        @endif

        @if($attributeValues->isNotEmpty())
            <dl class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($attributeValues as $values)
                    <div class="rounded-lg bg-slate-50 p-3">
                        <dt class="text-xs text-slate-500">{{ $values->first()->attribute?->name ?? 'مشخصه' }}</dt>
                        <dd class="mt-1 text-sm">{{ $values->pluck('value')->implode('، ') }}</dd>
                    </div>
                @endforeach
            </dl>
        @endif

        @foreach(['short_description' => 'توضیح کوتاه فعلی', 'full_description' => 'توضیح کامل فعلی'] as $field => $label)
            @if($task->product?->{$field})
                <div class="mt-4">
                    <h3 class="mb-2 text-sm font-semibold">{{ $label }}</h3>
                    <p class="max-h-64 overflow-y-auto whitespace-pre-wrap break-words rounded-lg bg-slate-50 p-3 text-sm leading-7">{{ trim(strip_tags((string) $task->product->{$field})) }}</p>
                </div>
            @endif
        @endforeach
    </section>
    @endif

    @if($isPreview)
        <div class="sticky bottom-2 flex flex-wrap justify-end gap-3 rounded-xl border bg-white/95 p-3 shadow-lg">
            <a href="{{ $backUrl }}" class="inline-flex min-h-11 items-center rounded-lg border px-5 py-2 text-slate-700">بازگشت</a>
            @if($canClaim)
                <button type="button" wire:click="claim" wire:loading.attr="disabled" class="min-h-11 rounded-lg bg-emerald-600 px-5 py-2 font-semibold text-white hover:bg-emerald-700 disabled:opacity-60">پذیرش تسک</button>
            @endif
        </div>
    @else
        <div class="flex flex-wrap gap-3">
            <a href="{{ route('outsourcer.tasks.workspace', $task) }}" class="inline-flex min-h-11 items-center rounded-lg bg-indigo-600 px-5 py-2 font-semibold text-white">رفتن به فضای انجام تسک</a>
            <a href="{{ route('outsourcer.tasks.submissions', $task) }}" class="inline-flex min-h-11 items-center rounded-lg border px-5 py-2">نسخه‌های ارسالی</a>
            <a href="{{ route('outsourcer.tasks.revisions', $task) }}" class="inline-flex min-h-11 items-center rounded-lg border px-5 py-2">سوابق اصلاح</a>
        </div>
    @endif
</div>
