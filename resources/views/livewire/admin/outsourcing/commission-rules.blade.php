<div>
    <h1 class="mb-5 text-2xl font-bold">قوانین پورسانت</h1>

    <x-product-completion-guide title="راهنمای ساده قوانین پورسانت">
        <div class="space-y-4 text-sm leading-relaxed">
            <div>
                <p class="font-semibold">این صفحه چه کاری انجام می‌دهد؟</p>
                <p>برای هر نوع Task مشخص می‌کنید هر واحد محتوای تأییدشده چقدر ارزش دارد و حداکثر چند واحد قابل پرداخت است. هر نسخه قانون پس از ایجاد غیرقابل‌ویرایش است؛ برای تغییر نرخ، نسخه جدید بسازید.</p>
            </div>
            <div class="rounded-lg border border-blue-200 bg-white p-3">
                <p class="font-semibold">فرمول مبلغ اصلی</p>
                <code class="mt-1 block text-xs">مبلغ هر نوع = مبلغ واحد × کمینه(تعداد واحد تأییدشده، سقف تعداد)</code>
                <p class="mt-2 text-xs">هر تصویر و مشخصه مستقل محاسبه می‌شود: اطلاعات تازه با نرخ ۱۰۰٪ و اصلاح مقدار قبلی با نرخ ۳۰٪ نسبت به اطلاعات آغاز تسک. توضیحات کوتاه و کامل یک واحد هستند و اگر یکی تازه باشد، نرخ کامل می‌گیرند. اصلاحات پورسانت‌دار و تعدیلات مالی جدا محاسبه می‌شوند. مبلغ‌ها با واحد <strong>ریال (IRR)</strong> ثبت می‌شوند.</p>
            </div>
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-amber-950">
                <p><strong>اصلاح اشتباه پیمانکار:</strong> پورسانت اضافه ندارد.</p>
                <p><strong>تغییر درخواست تأمین‌کننده:</strong> درصد اصلاح این قانون روی واحدهای تغییرکرده اعمال می‌شود.</p>
                <p><strong>تشخیص نرخ:</strong> نوع تسک نرخ را تعیین نمی‌کند؛ مبنا مقایسه محتوای نهایی با اطلاعات آغاز تسک است. بازبینِ مجاز می‌تواند با ثبت دلیل، برای مقادیر اصلاح‌شده نرخ کامل را به‌عنوان استثنا تأیید کند.</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-xs text-slate-700">
                ویدئو نیز در مدل پیش‌بینی شده، اما تا تکمیل Pipeline ویدئو غیرفعال است.
            </div>
        </div>
    </x-product-completion-guide>

    @if($errors->any())
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">
            <p class="font-semibold">قانون ایجاد نشد:</p>
            <ul class="mt-2 list-inside list-disc">@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
        </div>
    @endif

    <form wire:submit="save" class="mb-8 space-y-6 rounded-xl border bg-white p-5">
        <div class="grid gap-4 md:grid-cols-3">
            <div>
                <label for="commission-rule-name" class="mb-1.5 block text-sm font-medium text-slate-700">نام قانون</label>
                <input id="commission-rule-name" type="text" maxlength="255" wire:model="name" class="w-full rounded-lg border-slate-300" placeholder="مثلاً نرخ تکمیل کامل محصولات">
            </div>
            <div>
                <label for="commission-rule-task-type" class="mb-1.5 block text-sm font-medium text-slate-700">نوع Task</label>
                <select id="commission-rule-task-type" wire:model.live="taskType" class="w-full rounded-lg border-slate-300">
                    @foreach($taskTypes as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                </select>
                <p class="mt-1 text-xs text-slate-500">فقط نرخ‌های مرتبط با این نوع Task نمایش داده می‌شوند.</p>
            </div>
            <div>
                <label for="commission-rule-revision-percentage" class="mb-1.5 block text-sm font-medium text-slate-700">درصد اصلاح ناشی از تغییر درخواست</label>
                <div class="relative">
                    <input id="commission-rule-revision-percentage" type="number" min="0" max="100" step="0.01" onwheel="this.blur()" wire:model="revisionPercentage" class="w-full rounded-lg border-slate-300 pl-10">
                    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-500">٪</span>
                </div>
                <p class="mt-1 text-xs text-slate-500">فقط برای Revision تأییدشده با علت «تغییر درخواست تأمین‌کننده»؛ پیش‌فرض ۳۰٪.</p>
            </div>
        </div>

        <fieldset>
            <legend class="mb-3 text-base font-bold text-slate-900">نرخ واحدهای محتوا</legend>
            <div class="grid gap-4 lg:grid-cols-3">
                @foreach($contentTypes as $type => $meta)
                    <section class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <label class="flex items-start gap-2">
                            <input type="checkbox" wire:model.live="contentRates.{{ $type }}.enabled" class="mt-1 rounded border-slate-300 text-indigo-600">
                            <span>
                                <strong class="block text-slate-900">{{ $meta['label'] }}</strong>
                                <span class="text-xs text-slate-600">{{ $meta['unit'] }}</span>
                            </span>
                        </label>
                        <p class="mt-2 min-h-10 text-xs leading-relaxed text-slate-500">{{ $meta['help'] }}</p>
                        <div class="mt-3 grid gap-3 sm:grid-cols-2">
                            <div>
                                <label for="rate-{{ $type }}" class="mb-1 block text-xs font-medium text-slate-700">مبلغ هر واحد (ریال)</label>
                                <input id="rate-{{ $type }}" type="number" min="0" step="0.01" onwheel="this.blur()" wire:model="contentRates.{{ $type }}.unit_amount" @disabled(!($contentRates[$type]['enabled'] ?? false)) class="w-full rounded-lg border-slate-300 disabled:bg-slate-100" placeholder="0">
                            </div>
                            <div>
                                <label for="cap-{{ $type }}" class="mb-1 block text-xs font-medium text-slate-700">سقف تعداد قابل پرداخت</label>
                                <input id="cap-{{ $type }}" type="number" min="1" max="{{ $type === 'image' ? (int) config('product_completion.media.max_images', 10) : ($type === 'description' ? 1 : 100) }}" step="1" onwheel="this.blur()" wire:model="contentRates.{{ $type }}.max_payable_units" @disabled(!($contentRates[$type]['enabled'] ?? false)) @readonly($type === 'description') class="w-full rounded-lg border-slate-300 disabled:bg-slate-100 read-only:bg-slate-100">
                            </div>
                        </div>
                    </section>
                @endforeach
            </div>
        </fieldset>

        <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4 opacity-75">
            <div class="flex items-start gap-2">
                <input type="checkbox" disabled class="mt-1 rounded border-slate-300">
                <div>
                    <p class="font-semibold text-slate-700">ویدئو — فعلاً غیرفعال</p>
                    <p class="text-xs text-slate-500">نرخ و سقف ویدئو پس از تکمیل بارگذاری، بازبینی و انتشار ویدئو فعال خواهد شد.</p>
                </div>
            </div>
        </div>

        <div class="flex justify-end">
            <button wire:loading.attr="disabled" wire:target="save" class="rounded-lg bg-indigo-600 px-6 py-2.5 font-semibold text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60">ایجاد نسخه جدید قانون</button>
        </div>
    </form>

    <section>
        <h2 class="mb-3 text-lg font-bold">نسخه‌های قوانین</h2>
        <div class="space-y-3">
            @forelse($rules as $rule)
                <article class="rounded-xl border bg-white p-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h3 class="font-bold">{{ $rule->name }} <span class="text-sm font-normal text-slate-500">نسخه {{ $rule->version }}</span></h3>
                            <p class="mt-1 text-sm text-slate-600">{{ $rule->task_type->label() }} · {{ $rule->is_active ? 'فعال' : 'غیرفعال' }}</p>
                        </div>
                        <div class="flex items-center gap-3">
                            @if($rule->is_default)
                                <span class="rounded-full bg-indigo-100 px-2.5 py-1 text-xs font-semibold text-indigo-800">پیش‌فرض این نوع تسک</span>
                            @elseif($rule->is_active)
                                <button type="button" wire:click="setDefault('{{ $rule->public_id }}')" wire:loading.attr="disabled" class="text-sm text-indigo-600 hover:text-indigo-800">تعیین به‌عنوان پیش‌فرض</button>
                            @endif
                            <span class="rounded-full px-2.5 py-1 text-xs {{ $rule->pricing_schema_version >= 2 ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ $rule->pricing_schema_version >= 2 ? 'قیمت‌گذاری واحدی' : 'ساختار قدیمی' }}</span>
                            @if($rule->is_active)<button type="button" wire:click="deactivate('{{ $rule->public_id }}')" wire:loading.attr="disabled" class="text-sm text-red-600 hover:text-red-800">غیرفعال‌سازی</button>@endif
                        </div>
                    </div>

                    @if($rule->pricing_schema_version >= 2)
                        <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            @foreach($rule->items as $item)
                                <div class="rounded-lg bg-slate-50 p-3 text-sm {{ $item->is_enabled ? '' : 'opacity-50' }}">
                                    <p class="font-semibold">{{ $item->content_type->label() }}</p>
                                    @if($item->is_enabled)
                                        <p class="mt-1">{{ number_format((float) $item->unit_amount) }} ریال × حداکثر {{ $item->max_payable_units }} واحد</p>
                                    @else
                                        <p class="mt-1 text-slate-500">غیرفعال</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                        <p class="mt-3 text-xs text-slate-500">درصد اصلاح تغییر درخواست: {{ $rule->revision_percentage }}٪</p>
                    @else
                        <p class="mt-3 text-sm text-slate-600">مبلغ پایه: {{ number_format((float) $rule->base_amount) }} {{ $rule->currency }} · برای تغییر، قانون جدید با قیمت‌گذاری واحدی بسازید.</p>
                    @endif
                </article>
            @empty
                <div class="rounded-xl border border-dashed bg-white p-8 text-center text-slate-500">هنوز قانونی تعریف نشده است.</div>
            @endforelse
        </div>
        <div class="mt-4">{{ $rules->links() }}</div>
    </section>
</div>
