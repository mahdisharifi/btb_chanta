<div>
    @if($errors->any())
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">
            @foreach($errors->all() as $message)<p>{{ $message }}</p>@endforeach
        </div>
    @endif
    <h1 class="mb-5 text-2xl font-bold">صف بررسی محتوا</h1>

    <x-product-completion-guide title="راهنمای بازبینی">
        <div class="space-y-2">
            <p>«صف آزاد بازبینی» Taskهایی را نشان می‌دهد که پیمانکار پذیرفته، اما بازبین آن‌ها هنوز تعیین نشده است.</p>
            <p>تا زمانی که پیمانکار نسخه را ارسال نکرده باشد، فقط وضعیت Task نمایش داده می‌شود. پس از ارسال، بازبین می‌تواند بررسی را انجام دهد؛ نخستین تصمیم بازبینی، Task را به همان بازبین متصل می‌کند.</p>
        </div>
    </x-product-completion-guide>

    <div class="mb-5 flex gap-2 border-b border-slate-200">
        <button wire:click="$set('tab', 'queue')" type="button" class="border-b-2 px-4 py-3 text-sm font-semibold {{ $isQueueTab ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-slate-500 hover:text-slate-800' }}">صف آزاد بازبینی</button>
        <button wire:click="$set('tab', 'mine')" type="button" class="border-b-2 px-4 py-3 text-sm font-semibold {{ ! $isQueueTab ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-slate-500 hover:text-slate-800' }}">در انتظار بررسی من</button>
    </div>

    <div class="space-y-3">
        @forelse($tasks as $task)
            <article class="flex flex-wrap items-center justify-between gap-3 rounded-xl border bg-white p-4">
                <div>
                    <p class="font-bold text-slate-900">{{ $task->product?->title }}</p>
                    <p class="mt-1 text-sm text-slate-500">پیمانکار: {{ $task->assignee?->name }} · {{ $task->task_type->label() }} · {{ $task->status->label() }}</p>
                    <p class="mt-1 text-xs text-slate-400">مهلت: {{ $task->due_at ? jdate($task->due_at)->format('Y/m/d H:i') : 'بدون مهلت' }}</p>
                </div>
                @if(in_array($task->status->value, ['submitted', 'resubmitted'], true))
                    <button type="button" wire:click="beginReview('{{ $task->public_id }}', {{ $task->last_submission_id }})" wire:loading.attr="disabled" class="rounded-lg bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700 disabled:opacity-50">شروع / ادامه بررسی</button>
                @else
                    <span class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800">در انتظار ارسال پیمانکار</span>
                @endif
            </article>
        @empty
            <div class="rounded-xl border border-dashed bg-white p-8 text-center text-slate-500">
                {{ $isQueueTab ? 'در حال حاضر Task آزادی برای بازبینی وجود ندارد.' : 'موردی برای بررسی شما وجود ندارد.' }}
            </div>
        @endforelse
    </div>

    <div class="mt-4">{{ $tasks->links() }}</div>
</div>
