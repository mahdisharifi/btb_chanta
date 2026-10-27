<div>
    <h1 class="mb-5 text-2xl font-bold">تسک‌های تکمیل محصول</h1>
    <x-product-completion-guide title="راهنمای ایجاد تسک"><p>محصول، نوع فعالیت، قانون پورسانت و مهلت را انتخاب کنید. پیمانکار فقط Scope همان نوع تسک را می‌بیند و به قیمت، موجودی، Tier یا اطلاعات تجاری تأمین‌کننده دسترسی ندارد.</p></x-product-completion-guide>
    <section class="my-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4" aria-label="خلاصه وضعیت تسک‌ها">
        <button type="button" wire:click="resetFilters" class="min-h-20 rounded-xl border bg-white p-4 text-right hover:border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <span class="block text-sm text-slate-600">همهٔ تسک‌ها</span><strong class="mt-1 block text-2xl">{{ number_format((int) $taskMetrics->total_count) }}</strong>
        </button>
        <button type="button" wire:click="resetFilters" class="min-h-20 rounded-xl border border-sky-200 bg-sky-50 p-4 text-right hover:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-500">
            <span class="block text-sm text-sky-900">آمادهٔ پذیرش</span><strong class="mt-1 block text-2xl text-sky-900">{{ number_format((int) $taskMetrics->available_count) }}</strong>
        </button>
        <button type="button" wire:click="setAttentionFilter('review_queue')" class="min-h-20 rounded-xl border border-violet-200 bg-violet-50 p-4 text-right hover:border-violet-400 focus:outline-none focus:ring-2 focus:ring-violet-500">
            <span class="block text-sm text-violet-900">در انتظار بررسی</span><strong class="mt-1 block text-2xl text-violet-900">{{ number_format((int) $taskMetrics->review_count) }}</strong>
        </button>
        <button type="button" wire:click="setAttentionFilter('needs_attention')" class="min-h-20 rounded-xl border border-amber-200 bg-amber-50 p-4 text-right hover:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-500">
            <span class="block text-sm text-amber-900">نیازمند رسیدگی</span><strong class="mt-1 block text-2xl text-amber-900">{{ number_format((int) $taskMetrics->attention_count) }}</strong>
        </button>
    </section>

    @if($canCreateTasks)
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-slate-600">برای بررسی تداخل، خطای نهایی‌سازی یا مهلت‌های گذشته، تسک را باز کنید.</p>
            <button type="button" wire:click="toggleCreateForm" aria-expanded="{{ $showCreateForm ? 'true' : 'false' }}" class="min-h-11 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                {{ $showCreateForm ? 'بستن فرم ایجاد تسک' : 'ایجاد تسک جدید' }}
            </button>
        </div>
    @endif

    @if($showCreateForm)
    <form wire:submit="createTask" class="mb-6 grid gap-4 rounded-xl border bg-white p-4 md:grid-cols-4">
        <p class="rounded-lg border border-indigo-100 bg-indigo-50 p-3 text-sm leading-6 text-indigo-900 md:col-span-4">تسک پس از ایجاد، خودکار برای همه پیمانکاران واجد دسترسی منتشر می‌شود و در فهرست «تسک‌های آزاد» قرار می‌گیرد. اولین پیمانکاری که آن را بپذیرد، مسئول انجام آن خواهد شد.</p>
        <x-outsourcing-product-picker :selected-product="$selectedProduct" />

        <div>
            <label for="outsourcing-task-type" class="mb-1.5 block text-sm font-medium text-slate-700">نوع Task</label>
            <select id="outsourcing-task-type" wire:model.live="taskType" class="w-full rounded-lg border-slate-300">
                @foreach($taskTypes as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="outsourcing-task-commission-rule" class="mb-1.5 block text-sm font-medium text-slate-700">قانون پورسانت</label>
            <select id="outsourcing-task-commission-rule" wire:model="commissionRuleId" class="w-full rounded-lg border-slate-300">
                <option value="">انتخاب قانون پورسانت</option>
                @foreach($commissionRules->filter(fn ($rule) => $rule->task_type?->value === $taskType) as $rule)
                    <option value="{{ $rule->id }}">{{ $rule->name }} - نسخه {{ $rule->version }}{{ (int) $rule->id === (int) $systemDefaultRuleId ? ' (پیش‌فرض سیستم)' : '' }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="outsourcing-task-due-days" class="mb-1.5 block text-sm font-medium text-slate-700">مدت انجام پس از پذیرش (روز)</label>
            <input id="outsourcing-task-due-days" type="number" min="1" max="90" onwheel="this.blur()" wire:model="dueDays" class="w-full rounded-lg border-slate-300">
        </div>

        <div class="flex items-end">
            <button wire:loading.attr="disabled" class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-white disabled:cursor-not-allowed disabled:opacity-60">ایجاد و انتشار تسک</button>
        </div>

        @if($taskType === 'correction')
            <fieldset class="md:col-span-4 rounded-lg border border-amber-200 bg-amber-50 p-4">
                <legend class="px-1 text-sm font-semibold text-amber-900">بخش‌های مجاز برای اصلاح</legend>
                <p class="mb-3 text-xs text-amber-900">حداقل یک بخش را انتخاب کنید. پیمانکار فقط به بخش‌های انتخاب‌شده دسترسی دارد.</p>
                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach($correctionScopeOptions as $key => $label)
                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <input type="checkbox" value="{{ $key }}" wire:model="correctionScope" class="rounded border-slate-300 text-indigo-600">
                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
        @endif

        <div class="md:col-span-4" aria-live="polite">
            @if ($errors->any())
                <div class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">
                    <p class="font-semibold">ایجاد تسک انجام نشد:</p>
                    <ul class="mt-1 list-inside list-disc">
                        @foreach ($errors->all() as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </form>
    @endif
    @if(count($selectedTaskIds) > 0)
        <div class="mb-4 flex flex-wrap items-end gap-3 rounded-xl border border-indigo-200 bg-indigo-50 p-4">
            <div>
                <label for="bulk-deadline-days" class="mb-1.5 block text-sm font-medium text-indigo-900">تمدید مهلت (روز)</label>
                <input id="bulk-deadline-days" type="number" min="1" max="90" wire:model="bulkDeadlineDays" class="min-h-11 w-32 rounded-lg border-indigo-300">
            </div>
            <button type="button" wire:click="bulkExtendDeadlines" wire:loading.attr="disabled" class="min-h-11 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60">تمدید گروهی {{ count($selectedTaskIds) }} تسک</button>
            <button type="button" wire:click="$set('selectedTaskIds', [])" class="min-h-11 rounded-lg border border-indigo-300 px-4 py-2 text-sm text-indigo-900">لغو انتخاب</button>
        </div>
    @endif
    <div class="mb-4 grid gap-3 rounded-xl border bg-white p-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-7">
        <div>
            <label for="outsourcing-task-search" class="mb-1.5 block text-sm font-medium text-slate-700">جست‌وجوی محصول</label>
            <input id="outsourcing-task-search" type="search" wire:model.live.debounce.400ms="search" class="w-full rounded-lg border-slate-300" placeholder="نام محصول را وارد کنید">
        </div>
        <div>
            <label for="outsourcing-task-status-filter" class="mb-1.5 block text-sm font-medium text-slate-700">وضعیت Task</label>
            <select id="outsourcing-task-status-filter" wire:model.live="status" class="w-full rounded-lg border-slate-300">
                <option value="">همه وضعیت‌ها</option>
                @foreach($statuses as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="outsourcing-task-type-filter" class="mb-1.5 block text-sm font-medium text-slate-700">نوع تسک</label>
            <select id="outsourcing-task-type-filter" wire:model.live="taskTypeFilter" class="min-h-11 w-full rounded-lg border-slate-300">
                <option value="">همهٔ انواع</option>
                @foreach($taskTypes as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
            </select>
        </div>
        <div>
            <label for="outsourcing-task-attention-filter" class="mb-1.5 block text-sm font-medium text-slate-700">صف رسیدگی</label>
            <select id="outsourcing-task-attention-filter" wire:model.live="attentionFilter" class="min-h-11 w-full rounded-lg border-slate-300">
                <option value="">همهٔ وضعیت‌ها</option>
                @foreach($attentionOptions as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
            </select>
        </div>
        <div>
            <label for="outsourcing-task-assignee-filter" class="mb-1.5 block text-sm font-medium text-slate-700">پیمانکار</label>
            <select id="outsourcing-task-assignee-filter" wire:model.live="assigneeFilter" class="min-h-11 w-full rounded-lg border-slate-300">
                <option value="">همهٔ پیمانکاران</option>
                @foreach($assignees as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach
            </select>
        </div>
        <div>
            <label for="outsourcing-task-reviewer-filter" class="mb-1.5 block text-sm font-medium text-slate-700">بازبین</label>
            <select id="outsourcing-task-reviewer-filter" wire:model.live="reviewerFilter" class="min-h-11 w-full rounded-lg border-slate-300">
                <option value="">همهٔ بازبینان</option>
                @foreach($reviewers as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach
            </select>
        </div>
        <div>
            <label for="outsourcing-task-category-filter" class="mb-1.5 block text-sm font-medium text-slate-700">دسته‌بندی محصول</label>
            <select id="outsourcing-task-category-filter" wire:model.live="categoryFilter" class="min-h-11 w-full rounded-lg border-slate-300">
                <option value="">همهٔ دسته‌بندی‌ها</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-end">
            <button type="button" wire:click="resetFilters" class="min-h-11 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">پاک‌کردن فیلترها</button>
        </div>
    </div>
    <div class="space-y-3 md:hidden">
        @forelse($tasks as $task)
            <article wire:key="task-card-{{ $task->public_id }}" class="rounded-xl border bg-white p-4 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <label class="inline-flex min-h-11 items-center gap-2"><input type="checkbox" value="{{ $task->id }}" wire:model.live="selectedTaskIds" class="rounded border-slate-300 text-indigo-600"><span class="text-xs text-slate-600">انتخاب</span></label>
                    <div class="min-w-0">
                        <h2 class="break-words font-semibold text-slate-900">{{ $task->product?->title ?? 'محصول حذف یا در دسترس نیست' }}</h2>
                        <p class="mt-1 break-all text-xs text-slate-500">{{ $task->product?->sku ?? 'بدون SKU' }} · {{ $task->public_id }}</p>
                    </div>
                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ $task->status->label() }}</span>
                </div>
                <dl class="mt-3 grid grid-cols-2 gap-3 text-sm">
                    <div><dt class="text-xs text-slate-500">نوع</dt><dd>{{ $task->task_type->label() }}</dd></div>
                    <div><dt class="text-xs text-slate-500">اصلاح</dt><dd>{{ $task->revision_count }}/{{ $task->revision_limit }}</dd></div>
                    <div><dt class="text-xs text-slate-500">پیمانکار</dt><dd>{{ $task->assignee?->name ?? 'تخصیص‌نیافته' }}</dd></div>
                    <div><dt class="text-xs text-slate-500">بازبین</dt><dd>{{ $task->reviewer?->name ?? 'تعیین‌نشده' }}</dd></div>
                    <div class="col-span-2"><dt class="text-xs text-slate-500">مهلت</dt><dd>{{ $task->completionDeadlineLabel() }}</dd></div>
                </dl>
                @if($task->last_error)
                    <p class="mt-3 break-words rounded-lg border border-red-200 bg-red-50 p-3 text-xs leading-5 text-red-800">خطای ثبت‌شده: {{ \Illuminate\Support\Str::limit($task->last_error, 220) }}</p>
                @elseif($task->status === \App\Enums\ProductCompletionTaskStatus::APPROVED && ($task->content_applied_at === null || $task->media_published_at === null))
                    <p class="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900">نهایی‌سازی ناقص است؛ وضعیت اعمال محتوا و انتشار رسانه را بررسی کنید.</p>
                @elseif($task->status === \App\Enums\ProductCompletionTaskStatus::EXPIRED)
                    <p class="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900">تسک منقضی شده است؛ جزئیات را بررسی و در صورت نیاز تسک جایگزین بسازید.</p>
                @elseif($task->due_at?->isPast() && ! $task->status->isTerminal())
                    <p class="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900">مهلت این تسک گذشته است.</p>
                @endif
                @if($task->bulk_creation_batch_id)<p class="mt-2 text-xs text-slate-500">ایجاد گروهی · دستهٔ #{{ $task->bulk_creation_batch_id }}</p>@endif
                <div class="mt-3 flex flex-wrap gap-2">
                    <a class="inline-flex min-h-11 items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white" href="{{ route('admin.outsourcing.tasks.show', $task) }}">جزئیات و رفع مشکل</a>
                    @if($task->status === \App\Enums\ProductCompletionTaskStatus::EXPIRED)
                        <a class="inline-flex min-h-11 items-center rounded-lg border px-4 py-2 text-sm font-medium text-slate-700" href="{{ route('admin.outsourcing.tasks.index', ['productId' => $task->product_id]) }}">ساخت تسک جایگزین</a>
                    @endif
                </div>
            </article>
        @empty
            <div class="rounded-xl border bg-white p-8 text-center text-slate-500">با این فیلترها تسکی یافت نشد.</div>
        @endforelse
    </div>

    <div class="hidden overflow-x-auto rounded-xl border bg-white md:block">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50"><tr>
                <th class="p-3 text-right">انتخاب</th>
                <th class="p-3 text-right">محصول</th><th class="p-3 text-right">تسک</th><th class="p-3 text-right">وضعیت</th>
                <th class="p-3 text-right">تخصیص</th><th class="p-3 text-right">مهلت</th><th class="p-3 text-right">عیب‌یابی</th><th class="p-3 text-right">عملیات</th>
            </tr></thead>
            <tbody>
                @forelse($tasks as $task)
                    <tr wire:key="task-row-{{ $task->public_id }}" class="border-t align-top">
                        <td class="p-3"><input type="checkbox" value="{{ $task->id }}" wire:model.live="selectedTaskIds" aria-label="انتخاب تسک" class="rounded border-slate-300 text-indigo-600"></td>
                        <td class="p-3"><div class="max-w-xs font-medium">{{ $task->product?->title ?? 'محصول حذف یا در دسترس نیست' }}</div><div class="mt-1 text-xs text-slate-500">{{ $task->product?->sku ?? 'بدون SKU' }} · محصول #{{ $task->product_id }}</div></td>
                        <td class="p-3"><div>{{ $task->task_type->label() }}</div><div class="mt-1 break-all text-xs text-slate-500">{{ $task->public_id }}</div>@if($task->bulk_creation_batch_id)<div class="mt-1 text-xs text-indigo-700">دستهٔ گروهی #{{ $task->bulk_creation_batch_id }}</div>@endif</td>
                        <td class="p-3"><span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ $task->status->label() }}</span><div class="mt-2 text-xs text-slate-500">{{ $task->revision_count }}/{{ $task->revision_limit }} اصلاح</div></td>
                        <td class="p-3"><div>{{ $task->assignee?->name ?? 'تخصیص‌نیافته' }}</div><div class="mt-1 text-xs text-slate-500">بازبین: {{ $task->reviewer?->name ?? 'تعیین‌نشده' }}</div>@if($task->assigner)<div class="mt-1 text-xs text-slate-500">تخصیص‌دهنده: {{ $task->assigner->name }}</div>@endif</td>
                        <td class="p-3 whitespace-nowrap">{{ $task->completionDeadlineLabel() }}@if($task->status === \App\Enums\ProductCompletionTaskStatus::ASSIGNED && $task->accepted_at === null && $task->assignment_expires_at)<div class="mt-1 text-xs text-slate-500">انقضای رزرو: {{ \Morilog\Jalali\CalendarUtils::convertNumbers(jdate($task->assignment_expires_at)->format('Y/m/d H:i')) }}</div>@endif</td>
                        <td class="p-3">@if($task->last_error)<span class="block max-w-sm break-words text-xs text-red-700">{{ \Illuminate\Support\Str::limit($task->last_error, 180) }}</span>@elseif($task->status === \App\Enums\ProductCompletionTaskStatus::APPROVED && ($task->content_applied_at === null || $task->media_published_at === null))<span class="text-xs font-semibold text-amber-700">نهایی‌سازی ناقص</span>@elseif($task->status === \App\Enums\ProductCompletionTaskStatus::EXPIRED)<span class="text-xs font-semibold text-amber-700">منقضی‌شده</span>@elseif($task->due_at?->isPast() && ! $task->status->isTerminal())<span class="text-xs font-semibold text-amber-700">مهلت گذشته</span>@else<span class="text-xs text-slate-400">—</span>@endif</td>
                        <td class="p-3"><a class="inline-flex min-h-11 items-center rounded-lg px-3 text-indigo-700 hover:bg-indigo-50" href="{{ route('admin.outsourcing.tasks.show', $task) }}">جزئیات</a>@if($task->status === \App\Enums\ProductCompletionTaskStatus::EXPIRED)<a class="mt-1 block text-xs text-slate-600 underline" href="{{ route('admin.outsourcing.tasks.index', ['productId' => $task->product_id]) }}">ساخت تسک جایگزین</a>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="p-8 text-center text-slate-500">با این فیلترها تسکی یافت نشد.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $tasks->links() }}</div>
</div>
