<div>
    <a href="{{ route('admin.outsourcing.settlements') }}" class="mb-4 inline-block rounded-lg bg-indigo-600 px-4 py-2 text-white">تسویه تجمیعی حساب پیمانکاران</a>
    <h1 class="mb-5 text-2xl font-bold">مدیریت پورسانت‌ها</h1>

    <x-product-completion-guide title="راهنمای مالی">
        <div class="space-y-2">
            <p>مبلغ از Snapshot قانون Task محاسبه می‌شود. تأیید، قابل‌پرداخت‌شدن و پرداخت مراحل جدا هستند؛ تأییدکننده نمی‌تواند همان پورسانت را پرداخت کند.</p>
            <p>پیش از تأیید مالی می‌توانید پاداش، جریمه یا تعدیل را با دلیل اجباری ثبت کنید. تمام تغییرات در Audit ذخیره می‌شوند.</p>
        </div>
    </x-product-completion-guide>

    @if($errors->any())
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800" role="alert">
            <ul class="list-inside list-disc">@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
        </div>
    @endif

    @if($canChangeRules)
        <section class="mb-6 rounded-xl border border-indigo-200 bg-white p-4" aria-label="تغییر گروهی قانون پورسانت">
            <h2 class="text-lg font-bold text-indigo-900">تغییر گروهی قانون و محاسبه مجدد پورسانت</h2>
            <p class="mt-2 text-sm text-slate-600">پیمانکار و نوع تسک را انتخاب کنید. همهٔ پورسانت‌های قابل تغییر مطابق این فیلترها، در تمام صفحه‌ها بررسی می‌شوند. فیلتر وضعیت پایین صفحه روی این عملیات اثری ندارد.</p>
            <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                <label class="text-sm" for="bulk-rule-contractor">پیمانکار محتوا
                    <select id="bulk-rule-contractor" wire:model.live="bulkUserId" class="mt-1 w-full rounded-lg border-slate-300">
                        <option value="">انتخاب پیمانکار</option>
                        @foreach($contractors as $contractor)
                            <option value="{{ $contractor->id }}">{{ $contractor->name }} ({{ $contractor->id }})</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-sm" for="bulk-rule-task-type">نوع تسک
                    <select id="bulk-rule-task-type" wire:model.live="bulkTaskType" class="mt-1 w-full rounded-lg border-slate-300">
                        @foreach($taskTypes as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                    </select>
                </label>
                <label class="text-sm" for="bulk-rule-source">قانون فعلی
                    <select id="bulk-rule-source" wire:model.live="bulkSourceRuleId" class="mt-1 w-full rounded-lg border-slate-300">
                        <option value="">همهٔ قوانین این نوع تسک</option>
                        @foreach($sourceRules as $rule)
                            <option value="{{ $rule->id }}">{{ $rule->name }} — نسخه {{ $rule->version }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-sm" for="bulk-rule-target">قانون جدید
                    <select id="bulk-rule-target" wire:model.live="bulkRuleId" class="mt-1 w-full rounded-lg border-slate-300">
                        <option value="">انتخاب قانون جدید</option>
                        @foreach($rules->filter(fn ($rule) => $rule->task_type->value === $bulkTaskType) as $rule)
                            <option value="{{ $rule->id }}">{{ $rule->name }} — نسخه {{ $rule->version }} ({{ $rule->currency }})</option>
                        @endforeach
                    </select>
                </label>
            </div>
            <p class="mt-3 text-xs text-slate-500">فقط تسک‌های تکمیل‌شده دارای پورسانت بررسی می‌شوند. موارد پرداخت‌شده، متصل به صورتحساب تسویه یا دارای ارز متفاوت از قانون جدید از عملیات خارج هستند.</p>
            <button type="button" wire:click="previewBulkRule" wire:loading.attr="disabled" class="mt-3 rounded-lg bg-indigo-600 px-4 py-2 text-sm text-white disabled:opacity-60">پیش‌نمایش گروهی</button>

            @if($bulkPreview !== [])
                <div class="mt-4 border-t pt-4">
                    <p class="text-sm">تعداد قابل تغییر: <strong>{{ count($bulkPreview['rows']) }}</strong> — تعداد خارج از عملیات: <strong>{{ $bulkPreview['excluded_count'] }}</strong></p>
                    @if($bulkPreview['rows'] === [])
                        <p class="mt-3 rounded-lg bg-amber-50 p-3 text-sm text-amber-800">پورسانت قابل تغییری با این فیلترها پیدا نشد.</p>
                    @else
                        <div class="my-3 flex flex-wrap gap-3 text-sm">
                            <button type="button" wire:click="selectAllBulk" class="font-semibold text-indigo-700">انتخاب همهٔ {{ count($bulkPreview['rows']) }} مورد</button>
                            <button type="button" wire:click="clearBulkSelection" class="text-slate-600">لغو انتخاب همه</button>
                        </div>
                        <div class="max-h-96 overflow-auto rounded-lg border">
                            <table class="min-w-full text-right text-sm">
                                <thead class="sticky top-0 bg-slate-100"><tr><th class="p-2">انتخاب</th><th class="p-2">محصول / شناسه تسک</th><th class="p-2">قانون فعلی</th><th class="p-2">مبلغ فعلی</th><th class="p-2">مبلغ جدید</th></tr></thead>
                                <tbody>
                                    @foreach($bulkPreview['rows'] as $publicId => $row)
                                        <tr wire:key="bulk-commission-{{ $publicId }}" class="border-t">
                                            <td class="p-2"><input type="checkbox" wire:model.live="bulkSelected" value="{{ $publicId }}" aria-label="انتخاب تسک {{ $row['task_public_id'] }}" class="rounded border-slate-300 text-indigo-600"></td>
                                            <td class="p-2">{{ $row['product_title'] }}<span class="mt-1 block text-xs text-slate-500">{{ $row['task_public_id'] }}</span></td>
                                            <td class="p-2">{{ $row['old_rule'] }} — نسخه {{ $row['old_rule_version'] }}</td>
                                            <td class="p-2">{{ number_format((float) $row['old_total'], 2) }}</td>
                                            <td class="p-2 font-semibold">{{ number_format((float) $row['new_total'], 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-3 grid gap-3 rounded-lg bg-indigo-50 p-3 text-sm md:grid-cols-4">
                            <p>تعداد منتخب: <strong>{{ $bulkSummary['count'] }}</strong></p>
                            <p>جمع فعلی: <strong>{{ number_format((float) $bulkSummary['old_total'], 2) }}</strong></p>
                            <p>جمع جدید: <strong>{{ number_format((float) $bulkSummary['new_total'], 2) }}</strong></p>
                            <p>اختلاف: <strong>{{ number_format((float) $bulkSummary['difference'], 2) }}</strong> {{ $bulkPreview['currency'] }}</p>
                        </div>
                        <form wire:submit="changeBulkRule" wire:confirm="قانون همهٔ تسک‌های منتخب تغییر کند و پورسانت آن‌ها دوباره محاسبه شود؟ تأیید مالی قبلی لغو خواهد شد." class="mt-3 space-y-3">
                            <label class="block text-sm" for="bulk-rule-reason">دلیل تغییر گروهی
                                <textarea id="bulk-rule-reason" wire:model="bulkReason" maxlength="2000" required rows="2" class="mt-1 w-full rounded-lg border-slate-300"></textarea>
                            </label>
                            <p class="text-xs text-amber-800">پاداش و کسورات دستی حفظ می‌شوند. همهٔ موارد منتخب پس از تغییر به تأیید مجدد مالی نیاز دارند و سابقهٔ هر تغییر ثبت می‌شود.</p>
                            <button type="submit" wire:loading.attr="disabled" @disabled($bulkSummary['count'] === 0) class="rounded-lg bg-indigo-700 px-4 py-2 font-semibold text-white disabled:opacity-50">ثبت گروهی و محاسبه مجدد {{ $bulkSummary['count'] }} تسک</button>
                        </form>
                    @endif
                </div>
            @endif
        </section>
    @endif

    <div class="mb-4">
        <label for="admin-commission-status" class="mb-1 block text-sm font-medium text-slate-700">فیلتر وضعیت</label>
        <select id="admin-commission-status" wire:model.live="status" class="rounded-lg border-slate-300">
            <option value="">همه وضعیت‌ها</option>
            @foreach($statuses as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
        </select>
    </div>

    @if($approvedTasks->isNotEmpty())
        <section class="mb-6 space-y-3" aria-label="تسک‌های تأییدشده در انتظار محاسبه">
            <h2 class="text-lg font-bold">تسک‌های تأییدشده بازبین در انتظار محاسبه</h2>
            <p class="text-sm text-slate-600">این تسک‌ها تأیید محتوایی شده‌اند و هنوز مبلغ پورسانت آن‌ها محاسبه نشده است. پس از آماده‌سازی، مبلغ برای بررسی و تأیید مالی نمایش داده می‌شود.</p>
            @foreach($approvedTasks as $task)
                <article wire:key="pending-commission-{{ $task->public_id }}" class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                    <div class="grid gap-3 md:grid-cols-4">
                        <div><p class="text-xs text-slate-500">محصول</p><p class="font-semibold">{{ $task->product?->title }}</p></div>
                        <div><p class="text-xs text-slate-500">پیمانکار</p><p>{{ $task->assignee?->name }}</p></div>
                        <div><p class="text-xs text-slate-500">وضعیت</p><p>تأییدشده بازبین / در انتظار محاسبه</p></div>
                        @can('outsourcing.approve_commission')
                            <div><button type="button" wire:click="calculate('{{ $task->public_id }}')" wire:loading.attr="disabled" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm text-white disabled:opacity-60">آماده‌سازی برای بررسی مالی</button></div>
                        @endcan
                    </div>
                </article>
            @endforeach
            {{ $approvedTasks->links() }}
        </section>
    @endif

    <div class="space-y-4">
        @forelse($commissions as $commission)
            <article class="rounded-xl border bg-white p-4">
                <div class="grid gap-3 md:grid-cols-4">
                    <div><p class="text-xs text-slate-500">پیمانکار</p><p class="font-semibold">{{ $commission->user?->name }}</p></div>
                    <div><p class="text-xs text-slate-500">محصول</p><p class="font-semibold">{{ $commission->task?->product?->title }}</p></div>
                    <div><p class="text-xs text-slate-500">مبلغ / میانگین نرخ محتوا</p><p class="font-semibold text-emerald-700">{{ number_format((float) $commission->total_amount) }} {{ $commission->currency }}</p><p class="text-xs text-slate-500">{{ $commission->task?->commission_rate_percentage }}٪</p></div>
                    <div><p class="text-xs text-slate-500">وضعیت</p><p class="font-semibold">{{ $commission->status->label() }}</p></div>
                </div>

                @can('changeRule', $commission)
                    @if($commission->settlement_id === null && in_array($commission->status, [\App\Enums\ProductCompletionCommissionStatus::CALCULATED, \App\Enums\ProductCompletionCommissionStatus::UNDER_REVIEW, \App\Enums\ProductCompletionCommissionStatus::APPROVED, \App\Enums\ProductCompletionCommissionStatus::PAYABLE], true))
                    <details class="mt-4 rounded-lg border border-indigo-200 p-3">
                        <summary class="cursor-pointer font-semibold text-indigo-700">تغییر قانون و محاسبه مجدد</summary>
                        <p class="mt-2 text-sm">قانون فعلی: {{ $commission->commissionRule?->name }}، نسخه {{ $commission->commissionRule?->version }}</p>
                        <div class="mt-3 grid gap-3 md:grid-cols-2">
                            <label class="text-sm">قانون جدید
                                <select wire:model="ruleIds.{{ $commission->public_id }}" class="mt-1 w-full rounded-lg border-slate-300">
                                    <option value="">انتخاب قانون</option>
                                    @foreach($rules->filter(fn ($rule) => $rule->task_type === $commission->task?->task_type && $rule->currency === $commission->currency) as $rule)
                                        <option value="{{ $rule->id }}">{{ $rule->name }} — نسخه {{ $rule->version }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="text-sm">دلیل تغییر
                                <input type="text" maxlength="2000" wire:model="ruleReasons.{{ $commission->public_id }}" class="mt-1 w-full rounded-lg border-slate-300">
                            </label>
                        </div>
                        <button type="button" wire:click="previewRule('{{ $commission->public_id }}')" wire:loading.attr="disabled" class="mt-3 rounded-lg border border-indigo-600 px-4 py-2 text-sm text-indigo-700">پیش‌نمایش محاسبه</button>
                        @if(isset($rulePreviews[$commission->public_id]) && (int) ($ruleIds[$commission->public_id] ?? 0) === (int) $rulePreviews[$commission->public_id]['rule_id'])
                            <p class="mt-3 text-sm">مبلغ فعلی: {{ number_format((float) $rulePreviews[$commission->public_id]['old_total'], 2) }} — مبلغ جدید: {{ number_format((float) $rulePreviews[$commission->public_id]['new_total'], 2) }} {{ $commission->currency }}</p>
                            <p class="mt-1 text-xs text-amber-700">پس از ثبت، پورسانت نیازمند تأیید مجدد مالی خواهد بود.</p>
                            <button type="button" wire:click="changeRule('{{ $commission->public_id }}')" wire:confirm="قانون تغییر کند و پورسانت دوباره محاسبه شود؟" wire:loading.attr="disabled" class="mt-3 rounded-lg bg-indigo-600 px-4 py-2 text-sm text-white">ثبت تغییر و محاسبه مجدد</button>
                        @endif
                    </details>
                    @endif
                @endcan

                @php($breakdown = is_array($commission->breakdown_json ?? null) ? $commission->breakdown_json : [])
                @if($breakdown !== [])
                    <details class="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-3">
                        <summary class="cursor-pointer text-sm font-semibold text-slate-800">ریز محاسبهٔ مبلغ</summary>
                        <div class="mt-3 grid gap-3 text-sm md:grid-cols-4">
                            <div><p class="text-xs text-slate-500">مبلغ خام قبل از نرخ محتوا</p><p class="font-semibold">{{ number_format((float) ($breakdown['amount_before_task_rate'] ?? 0)) }} {{ $commission->currency }}</p></div>
                            <div><p class="text-xs text-slate-500">مبلغ پایه پس از نرخ‌ها</p><p class="font-semibold">{{ number_format((float) ($commission->base_amount ?? 0)) }} {{ $commission->currency }}</p></div>
                            <div><p class="text-xs text-slate-500">اصلاحات و تعدیلات</p><p class="font-semibold">{{ number_format((float) (($commission->revision_amount ?? 0) + ($commission->bonus_amount ?? 0) + ($commission->adjustment_amount ?? 0) - ($commission->penalty_amount ?? 0))) }} {{ $commission->currency }}</p></div>
                            <div><p class="text-xs text-slate-500">مبلغ نهایی پس از سقف</p><p class="font-semibold text-emerald-700">{{ number_format((float) ($commission->total_amount ?? 0)) }} {{ $commission->currency }}</p></div>
                        </div>
                        @if(isset($breakdown['fixed_base_rate_percentage']))
                            <p class="mt-3 text-xs text-slate-600">نرخ مبلغ پایه: {{ $breakdown['fixed_base_rate_percentage'] }}٪</p>
                        @endif
                        @if(isset($breakdown['base_units']) && is_array($breakdown['base_units']))
                            <div class="mt-3 overflow-x-auto"><table class="min-w-full text-right text-xs"><thead><tr class="border-b text-slate-500"><th class="px-2 py-1">نوع محتوا</th><th class="px-2 py-1">تعداد</th><th class="px-2 py-1">مبلغ واحد</th><th class="px-2 py-1">جمع</th></tr></thead><tbody>
                                @foreach($breakdown['base_units'] as $type => $unit)
                                    <tr class="border-b last:border-0"><td class="px-2 py-1">{{ ['attribute' => 'ویژگی', 'image' => 'تصویر', 'description' => 'توضیحات', 'title' => 'عنوان', 'video' => 'ویدئو'][$type] ?? $type }}</td><td class="px-2 py-1">{{ $unit['payable_units'] ?? ($unit['actual_units'] ?? 0) }}</td><td class="px-2 py-1">{{ number_format((float) ($unit['unit_amount'] ?? 0)) }}</td><td class="px-2 py-1 font-semibold">{{ number_format((float) ($unit['amount'] ?? 0)) }}</td></tr>
                                @endforeach
                            </tbody></table></div>
                        @elseif(isset($breakdown['content_rates']) && is_array($breakdown['content_rates']))
                            <div class="mt-3 overflow-x-auto"><table class="min-w-full text-right text-xs"><thead><tr class="border-b text-slate-500"><th class="px-2 py-1">نوع محتوا</th><th class="px-2 py-1">تعداد تغییرات</th><th class="px-2 py-1">نرخ</th></tr></thead><tbody>
                                @foreach($breakdown['content_rates'] as $type => $units)
                                    @foreach((array) $units as $unit)
                                        <tr class="border-b last:border-0"><td class="px-2 py-1">{{ ['attribute' => 'ویژگی', 'image' => 'تصویر', 'description' => 'توضیحات', 'title' => 'عنوان', 'video' => 'ویدئو'][$type] ?? $type }}</td><td class="px-2 py-1">۱</td><td class="px-2 py-1">{{ $unit['rate_percentage'] ?? '—' }}٪ ({{ ($unit['kind'] ?? '') === 'correction' ? 'اصلاحی' : 'تازه' }})</td></tr>
                                    @endforeach
                                @endforeach
                            </tbody></table></div>
                        @endif
                        <p class="mt-3 text-xs text-amber-700">مبلغ نهایی با سقف ثبت‌شده در قانون پورسانت محدود می‌شود.</p>
                    </details>
                @endif

                @can('adjustCommission', $commission)
                    <form wire:submit="adjust('{{ $commission->public_id }}')" class="mt-4 grid gap-3 border-t pt-4 md:grid-cols-4">
                        <div><label for="admin-bonus-{{ $commission->public_id }}" class="mb-1 block text-xs font-medium">پاداش</label><input id="admin-bonus-{{ $commission->public_id }}" type="number" min="0" step="0.01" onwheel="this.blur()" wire:model="bonusAmounts.{{ $commission->public_id }}" class="w-full rounded-lg border-slate-300" placeholder="0"></div>
                        <div><label for="admin-penalty-{{ $commission->public_id }}" class="mb-1 block text-xs font-medium">جریمه</label><input id="admin-penalty-{{ $commission->public_id }}" type="number" min="0" step="0.01" onwheel="this.blur()" wire:model="penaltyAmounts.{{ $commission->public_id }}" class="w-full rounded-lg border-slate-300" placeholder="0"></div>
                        <div><label for="admin-adjustment-{{ $commission->public_id }}" class="mb-1 block text-xs font-medium">تعدیل مثبت یا منفی</label><input id="admin-adjustment-{{ $commission->public_id }}" type="number" step="0.01" onwheel="this.blur()" wire:model="adjustmentAmounts.{{ $commission->public_id }}" class="w-full rounded-lg border-slate-300" placeholder="0"></div>
                        <div><label for="admin-reason-{{ $commission->public_id }}" class="mb-1 block text-xs font-medium">دلیل تغییر مبلغ</label><input id="admin-reason-{{ $commission->public_id }}" type="text" maxlength="2000" wire:model="adjustmentReasons.{{ $commission->public_id }}" class="w-full rounded-lg border-slate-300" required></div>
                        <button wire:loading.attr="disabled" class="rounded-lg border border-indigo-600 px-4 py-2 text-sm font-semibold text-indigo-700 md:col-span-4">ثبت تعدیل مبلغ</button>
                    </form>
                @endcan

                <div class="mt-4 flex flex-wrap items-center gap-3 border-t pt-4">
                    @can('approveCommission', $commission)<button wire:click="approve('{{ $commission->public_id }}')" wire:loading.attr="disabled" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm text-white">تأیید مالی</button>@endcan
                    @can('markPayable', $commission)<button wire:click="markPayable('{{ $commission->public_id }}')" wire:loading.attr="disabled" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm text-white">قابل پرداخت</button>@endcan
                    @if($commission->settlement_id)
                        <span class="text-sm text-indigo-700">این کارکرد در صورتحساب تجمیعی ثبت شده است.</span>
                    @elseif($commission->status === \App\Enums\ProductCompletionCommissionStatus::PAYABLE)
                        <a href="{{ route('admin.outsourcing.settlements') }}" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm text-white">افزودن به تسویه تجمیعی</a>
                    @endif
                </div>
            </article>
        @empty
            <div class="rounded-xl border border-dashed bg-white p-8 text-center text-slate-500">رکوردی وجود ندارد.</div>
        @endforelse
    </div>

    <div class="mt-4">{{ $commissions->links() }}</div>
</div>
