    @php
        $briefImage = $existingImages->first();
        $scopeLabels = ['title' => 'عنوان محصول', 'short_description' => 'توضیحات کوتاه', 'full_description' => 'توضیحات کامل', 'attributes' => 'ویژگی‌های محصول', 'gallery' => 'تصاویر محصول'];
    @endphp
    <section class="mb-5 overflow-hidden rounded-xl border border-slate-200 bg-white" aria-label="جزئیات تسک پیش از شروع">
        <div class="grid gap-5 p-5 md:grid-cols-[160px_1fr]">
            <div class="flex h-40 items-center justify-center rounded-lg bg-slate-50">
                @if($briefImage)
                    <img src="{{ route('outsourcing.gallery.preview', [$task, $briefImage]) }}" alt="{{ $task->product?->title }}" class="h-full w-full rounded-lg object-contain">
                @else
                    <x-heroicon-o-photo class="h-12 w-12 text-slate-300" />
                @endif
            </div>
            <div>
                <h2 class="text-xl font-bold text-slate-900">{{ $task->product?->title }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ $task->product?->category?->name }} · {{ $task->task_type->label() }}</p>
                <div class="mt-3 flex flex-wrap gap-x-5 gap-y-2 text-sm text-slate-600">
                    <span>شناسه محصول: <bdi>{{ $task->product_id }}</bdi></span>
                    <span>کد کالا: <bdi>{{ $task->product?->sku ?: 'ثبت نشده' }}</bdi></span>
                    <span>برند: <bdi>{{ $task->product?->brand ?: 'ثبت نشده' }}</bdi></span>
                </div>
                <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                    <div><dt class="text-xs text-slate-500">مهلت انجام</dt><dd class="mt-1 font-medium"><bdi>{{ $task->completionDeadlineLabel() }}</bdi></dd></div>
                    <div><dt class="text-xs text-slate-500">مهلت پذیرش</dt><dd class="mt-1"><bdi>{{ $task->assignment_expires_at ? \Morilog\Jalali\CalendarUtils::convertNumbers(jdate($task->assignment_expires_at->copy()->timezone(config('app.timezone')))->format('Y/m/d H:i')) : 'بدون محدودیت' }}</bdi></dd></div>
                    <div><dt class="text-xs text-slate-500">بازبین</dt><dd class="mt-1">{{ $task->reviewer?->name ?? 'صف بررسی بازبین‌ها' }}</dd></div>
                    <div><dt class="text-xs text-slate-500">تعداد اصلاح مجاز</dt><dd class="mt-1">{{ $task->remainingRevisions() }}</dd></div>
                </dl>
            </div>
        </div>
        @include('livewire.outsourcer.task.partials.product-reference')
        <div class="border-t bg-slate-50 p-5">
            <h3 class="mb-3 font-semibold text-slate-800">چه بخش‌هایی باید تکمیل شوند؟</h3>
            <div class="flex flex-wrap gap-2">
                @foreach($scopeLabels as $field => $label)
                    @if(data_get($task->scope_json, $field))
                        <span class="rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-sm text-indigo-800">{{ $label }}</span>
                    @endif
                @endforeach
            </div>
            @if(in_array($task->status, [\App\Enums\ProductCompletionTaskStatus::ASSIGNED, \App\Enums\ProductCompletionTaskStatus::ACCEPTED], true) && $attributes->isNotEmpty())
                <p class="mt-3 text-sm leading-7 text-slate-600">ویژگی‌ها: {{ $attributes->map(fn ($attribute) => $attribute->name.($attribute->is_required ? ' (اجباری)' : ''))->implode('، ') }}</p>
            @endif
            @if(in_array($task->status, [\App\Enums\ProductCompletionTaskStatus::ASSIGNED, \App\Enums\ProductCompletionTaskStatus::ACCEPTED], true))
            <p class="mt-4 text-sm leading-7 text-slate-600">{{ $task->status === \App\Enums\ProductCompletionTaskStatus::ACCEPTED ? 'تسک را پذیرفته‌اید؛ برای ورود به ویرایش، «شروع» را بزنید.' : 'ابتدا تسک را بپذیرید و «شروع» را بزنید.' }} سپس بخش‌های مشخص‌شده را تکمیل کنید، پیش‌نویس را ذخیره کنید یا مستقیم برای بررسی بفرستید.</p>
            @endif
            @if(($task->due_at && $task->due_at->isPast()) || ($task->status === \App\Enums\ProductCompletionTaskStatus::ASSIGNED && $task->assignment_expires_at && $task->assignment_expires_at->isPast()))
                <p class="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">مهلت انجام یا پذیرش پایان یافته است؛ برای تمدید مهلت یا تخصیص مجدد با مدیر هماهنگ کنید.</p>
            @endif
        </div>
    </section>
