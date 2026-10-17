<div>
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold">{{ $task->product?->title }}</h1>
            <p class="text-sm text-slate-500">{{ $task->public_id }} · {{ $task->status->label() }}</p>
        </div>
        <a href="{{ route('admin.outsourcing.tasks.index') }}" class="text-indigo-600 hover:text-indigo-800">بازگشت</a>
    </div>

    <x-product-completion-guide title="راهنمای مدیریت تسک">
        <div class="space-y-2">
            <p>تخصیص فقط به پیمانکار و بازبینی فقط به بازبین مجاز انجام می‌شود. Scope و Snapshot حاوی داده قیمت‌گذاری نیستند.</p>
            <p>اگر محصول هم‌زمان با انجام تسک تغییر کند، سیستم Task را در وضعیت «دارای تداخل» متوقف می‌کند. ابتدا جزئیات را بررسی و سپس بررسی مجدد، بازگرداندن برای اصلاح یا لغو را انتخاب کنید.</p>
        </div>
    </x-product-completion-guide>

    @if($errors->any())
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">
            <p class="font-semibold">عملیات انجام نشد:</p>
            <ul class="mt-2 list-inside list-disc">
                @foreach($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if($task->status === \App\Enums\ProductCompletionTaskStatus::CONFLICTED)
        @php
            $conflictLabels = [
                'title' => 'عنوان محصول',
                'short_description' => 'توضیح کوتاه',
                'full_description' => 'توضیحات کامل',
                'attributes' => 'ویژگی‌ها',
                'gallery' => 'گالری تصاویر',
                'category_context' => 'دسته‌بندی محصول',
                'supplier_context' => 'تأمین‌کننده محصول',
            ];
            $hasEditableConflict = collect(array_keys($task->conflict_json ?? []))->contains(function ($field) use ($task) {
                $scope = data_get($task->scope_json, $field, false);

                return $scope === true || is_array($scope);
            });
        @endphp
        <section class="mb-5 rounded-xl border border-amber-300 bg-amber-50 p-5">
            <div class="mb-4 flex items-start gap-3">
                <x-heroicon-o-exclamation-triangle class="mt-0.5 h-6 w-6 shrink-0 text-amber-700" />
                <div>
                    <h2 class="font-bold text-amber-950">تداخل محتوایی نیازمند تصمیم مدیر است</h2>
                    <p class="mt-1 text-sm text-amber-900">مقادیر محصول پس از ایجاد Task تغییر کرده‌اند. هیچ داده‌ای خودکار بازنویسی نشده است.</p>
                </div>
            </div>

            <div class="space-y-3">
                @foreach(($task->conflict_json ?? []) as $field => $details)
                    <details class="rounded-lg border border-amber-200 bg-white p-3">
                        <summary class="cursor-pointer font-semibold text-slate-800">{{ $conflictLabels[$field] ?? $field }}</summary>
                        <dl class="mt-3 grid gap-3 text-xs lg:grid-cols-3">
                            @if(array_key_exists('base', $details) || array_key_exists('before', $details))
                                <div><dt class="mb-1 font-semibold text-slate-500">مقدار مبنا</dt><dd class="max-h-40 overflow-auto whitespace-pre-wrap break-words rounded bg-slate-50 p-2">{{ json_encode($details['base'] ?? $details['before'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</dd></div>
                            @endif
                            <div><dt class="mb-1 font-semibold text-slate-500">مقدار فعلی محصول</dt><dd class="max-h-40 overflow-auto whitespace-pre-wrap break-words rounded bg-sky-50 p-2">{{ json_encode($details['current'] ?? null, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</dd></div>
                            @if(array_key_exists('submitted', $details))
                                <div><dt class="mb-1 font-semibold text-slate-500">مقدار ارسالی پیمانکار</dt><dd class="max-h-40 overflow-auto whitespace-pre-wrap break-words rounded bg-emerald-50 p-2">{{ json_encode($details['submitted'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</dd></div>
                            @endif
                        </dl>
                    </details>
                @endforeach
            </div>

            @can('resolveConflict', $task)
                <div class="mt-5 grid gap-4 lg:grid-cols-2">
                    <div class="rounded-lg border border-emerald-200 bg-white p-4">
                        <h3 class="font-semibold text-emerald-900">بررسی مجدد تداخل</h3>
                        <p class="my-2 text-sm text-slate-600">اگر تغییر هم‌زمان محصول خارج از این صفحه تعیین تکلیف شده است، سیستم دوباره مقایسه می‌کند. تا وقتی اختلاف باقی باشد، هیچ محتوایی اعمال نمی‌شود.</p>
                        <button type="button" wire:click="retryConflict({{ $task->lock_version }})" wire:loading.attr="disabled" wire:target="retryConflict" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60">بررسی مجدد و ادامه</button>
                    </div>
                    @if($hasEditableConflict && $task->remainingRevisions() > 0)
                        <form wire:submit="returnConflictForRevision({{ $task->lock_version }})" class="rounded-lg border border-indigo-200 bg-white p-4">
                            <h3 class="font-semibold text-indigo-900">بازگرداندن برای اصلاح پیمانکار</h3>
                            <p class="my-2 text-sm text-slate-600">فیلدهای متداخل داخل Scope به‌عنوان درخواست اصلاح سیستمی ثبت می‌شوند.</p>
                            <textarea wire:model="conflictResolutionReason" rows="3" maxlength="2000" class="w-full rounded-lg border-slate-300" placeholder="توضیح دقیق اقدام موردنیاز"></textarea>
                            @error('conflictResolutionReason')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            <button wire:loading.attr="disabled" wire:target="returnConflictForRevision" class="mt-3 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60">ثبت درخواست اصلاح</button>
                        </form>
                    @else
                        <div class="rounded-lg border border-slate-200 bg-white p-4 text-sm text-slate-700">
                            @if(! $hasEditableConflict)
                                این تداخل مربوط به دسته‌بندی یا تأمین‌کننده است و توسط پیمانکار قابل اصلاح نیست.
                            @else
                                سقف اصلاحات Task تکمیل شده است؛ ابتدا سقف اصلاحات را افزایش دهید یا Task را لغو کنید.
                            @endif
                        </div>
                    @endif
                </div>
            @endcan
        </section>
    @endif

    @can('retryFinalization', $task)
        <section class="mb-5 rounded-xl border border-sky-200 bg-sky-50 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="font-bold text-sky-950">نهایی‌سازی Task هنوز کامل نشده است</h2>
                    <p class="mt-1 text-sm text-sky-800">اعمال محتوا: {{ $task->content_applied_at ? 'انجام شده' : 'در انتظار' }} · انتشار رسانه: {{ $task->media_published_at ? 'انجام شده' : 'در انتظار' }}</p>
                    @if($task->last_error)<p class="mt-2 rounded bg-white/70 p-2 text-xs text-red-700">آخرین خطا: {{ $task->last_error }}</p>@endif
                </div>
                <button type="button" wire:click="retryFinalization({{ $task->lock_version }})" wire:loading.attr="disabled" wire:target="retryFinalization" class="rounded-lg bg-sky-700 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60">تلاش مجدد نهایی‌سازی</button>
            </div>
        </section>
    @endcan

    <div class="grid gap-5 lg:grid-cols-3">
        <section class="rounded-xl border bg-white p-4 lg:col-span-2">
            <h2 class="mb-3 font-bold">وضعیت و نسخه‌ها</h2>
            <dl class="grid gap-3 sm:grid-cols-2">
                <div><dt class="text-xs text-slate-500">نوع</dt><dd>{{ $task->task_type->label() }}</dd></div>
                <div><dt class="text-xs text-slate-500">اصلاح باقی‌مانده</dt><dd>{{ $task->remainingRevisions() }}</dd></div>
                <div><dt class="text-xs text-slate-500">نسخه Draft</dt><dd>{{ $task->draft_version }}</dd></div>
                <div><dt class="text-xs text-slate-500">نسخه Product مبنا</dt><dd>{{ $task->product_version_at_assignment }}</dd></div>
            </dl>
            <h3 class="mb-2 mt-5 font-semibold">ارسال‌ها</h3>
            @forelse($task->submissions as $submission)
                <p class="border-t py-2 text-sm">نسخه {{ $submission->version_no }} · {{ $submission->status->label() }}</p>
            @empty
                <p class="text-sm text-slate-500">هنوز ارسالی وجود ندارد.</p>
            @endforelse
        </section>

        <aside class="space-y-4">
            @can('extendDeadline', $task)
                <form wire:submit="extendDeadline" class="rounded-xl border border-amber-200 bg-white p-4">
                    <h2 class="mb-2 font-bold">تمدید مهلت تسک</h2>
                    @if($task->accepted_at === null && $task->completion_duration_seconds !== null)
                        <p class="mb-3 text-sm text-amber-800">پیمانکار هنوز تسک را نپذیرفته است. فاصلهٔ زمان ثبت تا تاریخ انتخابی، به‌عنوان مدت انجام ذخیره می‌شود و پس از پذیرش آغاز خواهد شد.</p>
                    @endif
                    <p class="mb-3 text-sm text-slate-600">مهلت فعلی: <bdi>{{ $task->completionDeadlineLabel() }}</bdi> (به وقت تهران). برای ادامه اصلاح پس از پایان مهلت، تاریخ جدید را ثبت کنید.</p>
                    <label class="block text-sm">تاریخ و ساعت جدید (شمسی)
                        <input type="text" wire:model="newDueAt" dir="ltr" placeholder="1405/07/20 18:00" aria-describedby="deadline-format-help" class="mt-1 w-full rounded-lg border-slate-300" required>
                    </label>
                    <p id="deadline-format-help" class="mt-1 text-xs text-slate-500">سال/ماه/روز و ساعت به وقت تهران؛ نمونه: <bdi>۱۴۰۵/۰۷/۲۰ ۱۸:۰۰</bdi></p>
                    @error('newDueAt')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    <button wire:loading.attr="disabled" wire:target="extendDeadline" class="mt-3 w-full rounded-lg bg-amber-600 py-2 text-white disabled:opacity-60">ثبت مهلت جدید</button>
                </form>
            @endcan
            <section class="rounded-xl border bg-white p-4" aria-label="وضعیت انتشار">
                <h2 class="mb-2 font-bold">وضعیت انتشار</h2>
                <p class="font-semibold {{ $task->published_at ? 'text-emerald-700' : 'text-slate-600' }}">{{ $task->published_at ? 'منتشر شده' : 'منتشر نشده' }}</p>
                @if($task->published_at)
                    <p class="mt-2 text-sm text-slate-600">تاریخ انتشار: <bdi>{{ \Morilog\Jalali\CalendarUtils::convertNumbers(jdate($task->published_at)->format('Y/m/d H:i')) }}</bdi></p>
                @endif
                <p class="mt-2 text-sm text-slate-600">وضعیت فعلی تسک: {{ $task->status->label() }}</p>
            </section>
            @if($task->status === \App\Enums\ProductCompletionTaskStatus::DRAFT && $task->published_at === null && $task->assigned_to === null)
            @can('publishTask', $task)
                <form wire:submit="publish" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                    <h2 class="mb-2 font-bold text-emerald-900">انتشار برای کارشناسان</h2>
                    <p class="mb-3 text-sm text-emerald-800">پس از انتشار، Task در «فرصت‌های آزاد» قابل پذیرش خواهد بود.</p>
                    <button wire:loading.attr="disabled" wire:target="publish" class="w-full rounded-lg bg-emerald-600 py-2 text-white disabled:opacity-60">انتشار Task</button>
                </form>
            @endcan

            @endif

            @can('assignTask', $task)
                <form wire:submit="assign" class="rounded-xl border bg-white p-4">
                    <h2 class="mb-3 font-bold">تخصیص</h2>
                    <select wire:model="assignedTo" class="mb-3 w-full rounded-lg border-slate-300"><option value="">پیمانکار</option>@foreach($outsourcers as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach</select>
                    <select wire:model="reviewerId" class="mb-3 w-full rounded-lg border-slate-300"><option value="">بازبین</option>@foreach($reviewers as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach</select>
                    <button class="w-full rounded-lg bg-indigo-600 py-2 text-white">ثبت تخصیص</button>
                </form>
            @endcan

            @can('cancelTask', $task)
                <form wire:submit="cancel" class="rounded-xl border border-red-200 bg-red-50 p-4">
                    <h2 class="mb-2 font-bold text-red-900">لغو Task</h2>
                    @if($task->content_applied_at)
                        <p class="mb-2 text-xs text-red-800">بخشی از محتوا قبلاً اعمال شده است؛ لغو، تغییرات اعمال‌شده محصول را بازگردانی نمی‌کند.</p>
                    @endif
                    <textarea wire:model="cancelReason" maxlength="2000" class="mb-3 w-full rounded-lg border-red-200" placeholder="دلیل لغو"></textarea>
                    <button class="w-full rounded-lg bg-red-600 py-2 text-white">لغو Task</button>
                </form>
            @endcan
        </aside>
    </div>
</div>
