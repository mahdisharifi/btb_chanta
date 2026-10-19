<div>
    <h1 class="mb-5 text-2xl font-bold">تسک‌های محتوا</h1>

    <x-product-completion-guide title="راهنمای تسک‌ها">
        <p>از فرصت‌های آزاد، تسکی را انتخاب کنید که در دسته و نوع فعالیت موردنظر شماست. محدوده، زمان انجام و اطلاعات مرجع را پیش از پذیرش در جزئیات تسک ببینید.</p>
    </x-product-completion-guide>

    <div class="mb-5 flex flex-wrap gap-2 border-b border-slate-200" aria-label="نمایش تسک‌ها">
        <button wire:click="$set('tab', 'mine')" type="button" aria-pressed="{{ $tab === 'mine' ? 'true' : 'false' }}"
            class="border-b-2 px-4 py-3 text-sm font-semibold {{ $tab === 'mine' ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-slate-500 hover:text-slate-800' }}">
            تسک‌های من
        </button>
        @if($canClaimAvailableTasks)
            <button wire:click="$set('tab', 'available')" type="button" aria-pressed="{{ $tab === 'available' ? 'true' : 'false' }}"
                class="border-b-2 px-4 py-3 text-sm font-semibold {{ $tab === 'available' ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-slate-500 hover:text-slate-800' }}">
                فرصت‌های آزاد
            </button>
        @endif
    </div>

    <div class="mb-6 grid gap-3 rounded-xl border bg-white p-4 sm:grid-cols-2 lg:grid-cols-4">
        <label class="block text-sm font-medium text-slate-700">جست‌وجوی تسک
            <input wire:model.live.debounce.400ms="search" type="search" maxlength="100" class="mt-1 w-full rounded-lg border-slate-300" placeholder="نام محصول یا شناسه را وارد کنید">
        </label>
        <label class="block text-sm font-medium text-slate-700">نوع فعالیت
            <select wire:model.live="taskType" class="mt-1 w-full rounded-lg border-slate-300">
                <option value="">همهٔ نوع فعالیت‌ها</option>
                @foreach($taskTypes as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="block text-sm font-medium text-slate-700">دسته‌بندی محصول
            <select wire:model.live="categoryId" class="mt-1 w-full rounded-lg border-slate-300" aria-label="فیلتر دسته‌بندی محصول">
                <option value="">همهٔ دسته‌بندی‌ها</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}">{{ $categoryPaths[$category->id] }}</option>
                @endforeach
            </select>
        </label>
        @if($tab === 'mine')
            <label class="block text-sm font-medium text-slate-700">وضعیت
                <select wire:model.live="status" class="mt-1 w-full rounded-lg border-slate-300">
                    <option value="">همهٔ وضعیت‌ها</option>
                    @foreach($statuses as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
        @else
            <div class="flex items-center rounded-lg bg-sky-50 px-3 py-2 text-sm text-sky-800">فقط تسک‌های آمادهٔ پذیرش و دارای مهلت معتبر نمایش داده می‌شوند.</div>
        @endif
    </div>

    <div wire:loading.delay class="mb-4 text-sm text-slate-500" aria-live="polite">در حال به‌روزرسانی نتایج…</div>

    <div class="grid gap-4 md:grid-cols-2">
        @forelse($tasks as $task)
            <article class="rounded-xl border bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <h2 class="font-bold text-slate-900">{{ $task->product?->title }}</h2>
                    <span class="shrink-0 rounded-full bg-slate-100 px-2.5 py-1 text-xs text-slate-700">{{ $task->status->label() }}</span>
                </div>
                <p class="mt-2 text-sm text-slate-500">{{ $categoryPaths[$task->product?->category_id] ?? $task->product?->category?->name ?? 'بدون دسته‌بندی' }} · {{ $task->task_type->label() }}</p>
                <p class="mt-2 text-sm text-slate-700">اصلاح: {{ $task->revision_count }}/{{ $task->revision_limit }}</p>
                @if($tab === 'available')
                    <p class="mt-1 text-sm text-slate-700">مدت انجام پس از پذیرش:
                        @if($task->completion_duration_seconds)
                            @php($durationMinutes = (int) ceil($task->completion_duration_seconds / 60))
                            {{ $durationMinutes % 1440 === 0 ? ($durationMinutes / 1440).' روز' : ($durationMinutes % 60 === 0 ? ($durationMinutes / 60).' ساعت' : $durationMinutes.' دقیقه') }}
                        @elseif($task->due_at)
                            طبق مهلت درج‌شده
                        @else
                            مدت انجام ثبت نشده
                        @endif
                    </p>
                    <p class="mt-1 text-sm text-slate-700">مهلت پذیرش: {{ $task->due_at ? \Morilog\Jalali\CalendarUtils::convertNumbers(jdate($task->due_at->copy()->timezone(config('app.timezone')))->format('Y/m/d H:i')) : 'بدون مهلت پذیرش' }}</p>
                @else
                    <p class="mt-1 text-sm text-slate-700">مهلت انجام: {{ $task->completionDeadlineLabel() }}</p>
                @endif
                <p class="mt-1 text-xs text-slate-400">شناسه: <bdi>{{ $task->public_id }}</bdi></p>

                <div class="mt-4 flex flex-wrap gap-3">
                    @if($tab === 'available')
                        <a href="{{ route('outsourcer.tasks.show', ['task' => $task, 'tab' => $tab, 'status' => $status, 'taskType' => $taskType, 'search' => $search, 'category' => $categoryId]) }}" class="rounded-lg border border-indigo-200 px-4 py-2 text-sm font-semibold text-indigo-700 hover:bg-indigo-50">مشاهدهٔ جزئیات</a>
                    @else
                        <a class="text-indigo-600 hover:text-indigo-800" href="{{ route('outsourcer.tasks.show', $task) }}">جزئیات</a>
                        <a class="font-semibold text-emerald-700 hover:text-emerald-800" href="{{ route('outsourcer.tasks.workspace', $task) }}">ادامهٔ کار</a>
                    @endif
                </div>
            </article>
        @empty
            <div class="rounded-xl border border-dashed bg-white p-8 text-center text-slate-600 md:col-span-2">
                <p>{{ $tab === 'available' ? 'تسکی با این فیلترها برای پذیرش پیدا نشد.' : 'تسکی با این فیلترها در فهرست شما پیدا نشد.' }}</p>
                @if($categoryId !== '' || $taskType !== '' || $search !== '' || $status !== '')
                    <button type="button" wire:click="clearFilters" class="mt-3 font-semibold text-indigo-700 underline">پاک‌کردن فیلترها</button>
                @endif
            </div>
        @endforelse
    </div>

    <div class="mt-4">{{ $tasks->links() }}</div>
</div>
