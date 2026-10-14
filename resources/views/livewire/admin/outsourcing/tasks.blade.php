<div>
    <h1 class="mb-5 text-2xl font-bold">تسک‌های تکمیل محصول</h1>
    <x-product-completion-guide title="راهنمای ایجاد تسک"><p>محصول، نوع فعالیت، قانون پورسانت و مهلت را انتخاب کنید. پیمانکار فقط Scope همان نوع تسک را می‌بیند و به قیمت، موجودی، Tier یا اطلاعات تجاری تأمین‌کننده دسترسی ندارد.</p></x-product-completion-guide>
    <form wire:submit="createTask" class="mb-6 grid gap-4 rounded-xl border bg-white p-4 md:grid-cols-4">
        <x-outsourcing-product-picker />

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
                    <option value="{{ $rule->id }}">{{ $rule->name }} - نسخه {{ $rule->version }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="outsourcing-task-due-days" class="mb-1.5 block text-sm font-medium text-slate-700">مهلت انجام (روز)</label>
            <input id="outsourcing-task-due-days" type="number" min="1" max="90" onwheel="this.blur()" wire:model="dueDays" class="w-full rounded-lg border-slate-300">
        </div>

        <div class="flex items-end">
            <button wire:loading.attr="disabled" class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-white disabled:cursor-not-allowed disabled:opacity-60">ایجاد Task</button>
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
    <div class="mb-4 grid gap-3 rounded-xl border bg-white p-4 sm:grid-cols-2">
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
    </div>
    <div class="overflow-x-auto rounded-xl border bg-white"><table class="min-w-full text-sm"><thead class="bg-slate-50"><tr><th class="p-3 text-right">محصول</th><th>نوع</th><th>وضعیت</th><th>پیمانکار</th><th>اصلاح</th><th>مهلت</th><th></th></tr></thead><tbody>@forelse($tasks as $task)<tr class="border-t"><td class="p-3">{{ $task->product?->title }}</td><td>{{ $task->task_type->label() }}</td><td>{{ $task->status->label() }}</td><td>{{ $task->assignee?->name ?? 'تخصیص‌نیافته' }}</td><td>{{ $task->revision_count }}/{{ $task->revision_limit }}</td><td>{{ $task->due_at ? jdate($task->due_at)->format('Y/m/d H:i') : '-' }}</td><td><a class="text-indigo-600" href="{{ route('admin.outsourcing.tasks.show', $task) }}">جزئیات</a></td></tr>@empty<tr><td colspan="7" class="p-8 text-center text-slate-500">تسکی یافت نشد.</td></tr>@endforelse</tbody></table></div>
    <div class="mt-4">{{ $tasks->links() }}</div>
</div>
