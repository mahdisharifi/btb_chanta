<div>
    <h1 class="mb-5 text-2xl font-bold">تسک‌های محتوا</h1>

    <x-product-completion-guide title="راهنمای تسک‌ها">
        <div class="space-y-2">
            <p>در «تسک‌های من»، فقط فعالیت‌هایی که به شما تخصیص داده شده‌اند نمایش داده می‌شوند.</p>
            <p>در «فرصت‌های آزاد»، Taskهای آماده واگذاری را می‌بینید و با پذیرش، Task فوراً به شما اختصاص داده می‌شود. اطلاعات قیمت، موجودی، تأمین‌کننده و Tier نمایش داده نمی‌شود.</p>
        </div>
    </x-product-completion-guide>

    <div class="mb-5 flex flex-wrap gap-2 border-b border-slate-200">
        <button wire:click="$set('tab', 'mine')" type="button"
            class="border-b-2 px-4 py-3 text-sm font-semibold {{ $tab === 'mine' ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-slate-500 hover:text-slate-800' }}">
            تسک‌های من
        </button>
        @if($canClaimAvailableTasks)
            <button wire:click="$set('tab', 'available')" type="button"
                class="border-b-2 px-4 py-3 text-sm font-semibold {{ $tab === 'available' ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-slate-500 hover:text-slate-800' }}">
                فرصت‌های آزاد
            </button>
        @endif
    </div>

    <div class="mb-6 grid gap-3 rounded-xl border bg-white p-4 md:grid-cols-3">
        <input wire:model.live.debounce.400ms="search" type="search" maxlength="100" class="rounded-lg border-slate-300" placeholder="جست‌وجو در نام محصول یا شناسه تسک" aria-label="جست‌وجوی تسک">
        <select wire:model.live="taskType" class="rounded-lg border-slate-300" aria-label="فیلتر نوع تسک">
            <option value="">همه نوع فعالیت‌ها</option>
            @foreach($taskTypes as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
        @if($tab === 'mine')
            <select wire:model.live="status" class="rounded-lg border-slate-300" aria-label="فیلتر وضعیت">
                <option value="">همه وضعیت‌ها</option>
                @foreach($statuses as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        @else
            <div class="flex items-center rounded-lg bg-sky-50 px-3 text-sm text-sky-800">فقط Taskهای آماده واگذاری و دارای مهلت معتبر نمایش داده می‌شوند.</div>
        @endif
    </div>

    <div wire:loading.delay class="mb-4 text-sm text-slate-500">در حال به‌روزرسانی فهرست…</div>

    <div class="grid gap-4 md:grid-cols-2">
        @forelse($tasks as $task)
            <article class="rounded-xl border bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <h2 class="font-bold text-slate-900">{{ $task->product?->title }}</h2>
                    <span class="shrink-0 rounded-full bg-slate-100 px-2.5 py-1 text-xs text-slate-700">{{ $task->status->label() }}</span>
                </div>
                <p class="mt-2 text-sm text-slate-500">{{ $task->task_type->label() }} · اصلاح {{ $task->revision_count }}/{{ $task->revision_limit }}</p>
                <p class="mt-2 text-sm text-slate-700">مهلت: {{ $task->completionDeadlineLabel() }}</p>
                <p class="mt-1 text-xs text-slate-400">شناسه: {{ $task->public_id }}</p>

                <div class="mt-4 flex flex-wrap gap-3">
                    @if($tab === 'available')
                        <button wire:click="claim('{{ $task->public_id }}')" wire:loading.attr="disabled" wire:target="claim('{{ $task->public_id }}')" type="button" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60">
                            پذیرش تسک
                        </button>
                    @else
                        <a class="text-indigo-600 hover:text-indigo-800" href="{{ route('outsourcer.tasks.show', $task) }}">جزئیات</a>
                        <a class="font-semibold text-emerald-700 hover:text-emerald-800" href="{{ route('outsourcer.tasks.workspace', $task) }}">فضای کار</a>
                    @endif
                </div>
            </article>
        @empty
            <div class="rounded-xl border border-dashed bg-white p-8 text-center text-slate-500 md:col-span-2">
                {{ $tab === 'available' ? 'در حال حاضر تسک آزادی برای پذیرش وجود ندارد.' : 'تسکی مطابق فیلترهای انتخاب‌شده یافت نشد.' }}
            </div>
        @endforelse
    </div>

    <div class="mt-4">{{ $tasks->links() }}</div>
</div>
