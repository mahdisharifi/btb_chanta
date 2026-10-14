<section class="mb-5 space-y-4 rounded-xl border border-indigo-200 bg-white p-5" @if($task->status->canBeReviewed()) wire:poll.15s @endif aria-label="نسخه ارسال‌شده شما">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 class="text-lg font-bold text-slate-900">نسخهٔ ارسال‌شده شما · نسخه {{ $displaySubmission->version_no }}</h2>
            <p class="mt-2 text-sm text-slate-600">{{ $displaySubmission->status->label() }} · <bdi>{{ jdate($displaySubmission->submitted_at->copy()->timezone(config('app.timezone')))->format('Y/m/d H:i') }}</bdi></p>
        </div>
        @if($canWithdraw)
            <button type="button" wire:click="withdrawSubmission({{ $displaySubmission->id }})" wire:confirm="ارسال این نسخه لغو شود؟ اطلاعات و تصاویر حفظ می‌شوند و تسک دوباره قابل ویرایش خواهد بود." wire:loading.attr="disabled" class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-900 disabled:opacity-50">لغو ارسال و ادامه ویرایش</button>
        @endif
    </div>
    @if($canWithdraw)
        <p class="rounded-lg bg-amber-50 p-3 text-sm leading-7 text-amber-900">تا <bdi>{{ jdate($withdrawalDeadline->copy()->timezone(config('app.timezone')))->format('Y/m/d H:i') }}</bdi> می‌توانید ارسال را لغو کنید، به شرطی که بازبین بررسی را شروع نکرده باشد.</p>
    @elseif($displaySubmission->review_started_at)
        <p class="text-sm text-slate-600">بررسی بازبین شروع شده است؛ لغو ارسال امکان‌پذیر نیست.</p>
    @elseif($displaySubmission->status === \App\Enums\ProductCompletionSubmissionStatus::WITHDRAWN)
        <p class="text-sm text-amber-800">این ارسال لغو شده و برای ثبت تاریخچه نگه داشته شده است. اطلاعات شما در پیش‌نویس قابل ویرایش است.</p>
    @elseif($task->status->canBeReviewed())
        <p class="text-sm text-slate-600">مهلت لغو ارسال پایان یافته است؛ نسخهٔ ارسالی همچنان برای شما قابل مشاهده است.</p>
    @endif
    <details @if(!$task->status->canEditDraft()) open @endif>
        <summary class="cursor-pointer text-sm font-semibold text-indigo-800">مشاهده محتوا، ویژگی‌ها و تصاویر ارسال‌شده</summary>
        <div class="mt-4 space-y-4">
            @foreach(['title' => 'عنوان محصول', 'short_description' => 'توضیحات کوتاه', 'full_description' => 'توضیحات کامل', 'attributes' => 'ویژگی‌ها', 'gallery' => 'تصاویر'] as $field => $label)
                @if(array_key_exists($field, $displaySubmission->payload_json ?? []))
                    <article class="rounded-lg border border-slate-200 p-4">
                        <h3 class="mb-3 font-semibold text-slate-900">{{ $label }}</h3>
                        @include('livewire.reviewer.task.partials.review-value', [
                            'value' => $displaySubmission->payload_json[$field], 'side' => 'after', 'change' => [],
                            'attributeNames' => $submittedAttributeNames, 'attributeValues' => $submittedAttributeValues,
                            'uploadedImages' => $media,
                        ])
                    </article>
                @endif
            @endforeach
        </div>
    </details>
</section>
