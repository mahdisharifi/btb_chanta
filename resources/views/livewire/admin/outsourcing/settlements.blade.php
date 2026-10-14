<div class="space-y-6" dir="rtl">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold">تسویه حساب پیمانکاران</h1>
        <a class="text-indigo-700" href="{{ route('admin.outsourcing.commissions') }}">ریز کارکرد و تأیید مالی تسک‌ها</a>
    </div>
    @if($notice)<div role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-900">{{ $notice }}</div>@endif
    @if($errors->any())
        <div role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-red-800"><ul class="list-inside list-disc">@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>
    @endif

    @can('outsourcing.approve_commission')
        <section class="space-y-4 rounded-xl border bg-white p-5">
            <h2 class="text-lg font-bold">صورتحساب جدید</h2>
            <p class="text-sm leading-7 text-slate-600">فقط کارکردهای «قابل پرداخت» وارد صورتحساب می‌شوند. مبنای بازه، تاریخ محاسبه کارکرد است؛ تاریخ‌ها شمسی و به وقت تهران هستند. هر صورتحساب متعلق به یک پیمانکار و یک واحد پول است.</p>
            <form wire:submit="previewSettlement" class="space-y-4">
                <div class="grid gap-4 md:grid-cols-3">
                    <div>
                        <label for="settlement-search" class="mb-1 block text-sm">جستجوی پیمانکار</label>
                        <input id="settlement-search" wire:model.live.debounce.350ms="contractorSearch" maxlength="100" class="mb-2 w-full rounded-lg border-slate-300" placeholder="نام پیمانکار">
                        <label for="settlement-contractor" class="sr-only">انتخاب پیمانکار</label>
                        <select id="settlement-contractor" wire:model.live="contractorId" class="w-full rounded-lg border-slate-300"><option value="">انتخاب کنید</option>@foreach($contractors as $contractor)<option value="{{ $contractor->id }}">{{ $contractor->name }} — {{ $contractor->id }}</option>@endforeach</select>
                        <p class="mt-1 text-xs text-slate-500">حداکثر ۵۰ نتیجه؛ برای یافتن پیمانکار نام او را جستجو کنید.</p>
                    </div>
                    <div><label for="settlement-from" class="mb-1 block text-sm">از تاریخ شمسی</label><input id="settlement-from" wire:model.live.blur="fromDate" dir="ltr" maxlength="10" placeholder="۱۴۰۵/۰۷/۰۱" class="w-full rounded-lg border-slate-300"></div>
                    <div><label for="settlement-to" class="mb-1 block text-sm">تا تاریخ شمسی (شامل این روز)</label><input id="settlement-to" wire:model.live.blur="toDate" dir="ltr" maxlength="10" placeholder="۱۴۰۵/۰۷/۳۰" class="w-full rounded-lg border-slate-300"></div>
                    <div><label for="settlement-currency" class="mb-1 block text-sm">واحد پول</label><select id="settlement-currency" wire:model.live="currency" class="w-full rounded-lg border-slate-300"><option value="IRR">ریال — IRR</option>@foreach($currencies as $unit)@if($unit !== 'IRR')<option value="{{ $unit }}">{{ $unit }}</option>@endif @endforeach</select></div>
                    <div><label for="settlement-adjustment" class="mb-1 block text-sm">تعدیل کل صورتحساب (+ افزایش، − کاهش)</label><input id="settlement-adjustment" wire:model.live.blur="adjustment" dir="ltr" maxlength="24" class="w-full rounded-lg border-slate-300"></div>
                    <div><label for="settlement-adjustment-reason" class="mb-1 block text-sm">دلیل تعدیل (در صورت تغییر مبلغ الزامی)</label><input id="settlement-adjustment-reason" wire:model.live.blur="adjustmentReason" maxlength="2000" class="w-full rounded-lg border-slate-300"></div>
                </div>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model.live="includePrevious" class="rounded border-slate-300">مطالبات قابل پرداختِ قبل از شروع بازه نیز اضافه شوند</label>
                <button wire:loading.attr="disabled" class="rounded-lg bg-indigo-600 px-5 py-2 text-white disabled:opacity-50">نمایش پیش‌نمایش</button>
            </form>
            @if($preview !== null)
                <div class="space-y-3 rounded-lg border border-indigo-200 bg-indigo-50 p-4">
                    <div class="grid gap-3 text-sm sm:grid-cols-4">
                        <p>کارکرد این بازه: <strong>{{ \App\Support\SettlementDisplay::money($preview['period_amount']) }}</strong></p>
                        <p>مطالبات قبلی: <strong>{{ \App\Support\SettlementDisplay::money($preview['previous_amount']) }}</strong></p>
                        <p>تعداد تسک: <strong>{{ $preview['count'] }}</strong></p>
                        <p>مبلغ نهایی: <strong>{{ \App\Support\SettlementDisplay::money($preview['net_amount']) }} {{ $currency }}</strong></p>
                    </div>
                    <p class="text-xs text-slate-600">مبالغ در زمان ایجاد دوباره بررسی می‌شوند. ریز قطعی کارکرد پس از ایجاد صورتحساب، پیش از تأیید، نمایش داده می‌شود.</p>
                    @if($preview['count'] > 0)<button type="button" wire:click="create" wire:loading.attr="disabled" class="rounded-lg bg-indigo-700 px-5 py-2 text-white disabled:opacity-50">ایجاد پیش‌نویس و رزرو کارکردها</button>@else<p>کارکرد واجد شرایطی یافت نشد.</p>@endif
                </div>
            @endif
        </section>
    @endcan

    <section class="space-y-3">
        <label for="settlement-status" class="block text-sm">وضعیت صورتحساب</label>
        <select id="settlement-status" wire:model.live="status" class="rounded-lg border-slate-300"><option value="">همه</option value="draft">پیش‌نویس</option><option value="approved">تأییدشده / منتظر پرداخت</option><option value="paid">پرداخت‌شده</option><option value="cancelled">لغوشده</option></select>
        @include('livewire.outsourcing.settlement-list', ['showContractor' => true])
    </section>

    @if($selected)
        <section wire:key="settlement-{{ $selected->public_id }}" class="space-y-5 rounded-xl border border-indigo-200 bg-white p-5">
            @include('livewire.outsourcing.settlement-detail', ['settlement' => $selected])
            @if($selected->status === 'paid')
                @can('outsourcing.pay_commission')
                    @can('outsourcing.adjust_commission')
                        <details class="rounded-lg border border-amber-200 p-3">
                            <summary class="cursor-pointer text-sm text-amber-900">تکمیل یا اصلاح تاریخ واقعی پرداخت</summary>
                            <form wire:submit="correctPaymentDate" wire:confirm="تاریخ واقعی پرداخت با ثبت دلیل و سابقه تغییر اصلاح شود؟" class="mt-3 space-y-3">
                                <label for="correct-payment-date" class="block text-sm">تاریخ واقعی پرداخت (شمسی)</label>
                                <input id="correct-payment-date" wire:model="paymentDate" required maxlength="10" dir="ltr" class="rounded-lg border-slate-300">
                                <label for="correct-payment-date-reason" class="block text-sm">دلیل اصلاح یا تکمیل تاریخ</label>
                                <input id="correct-payment-date-reason" wire:model="paymentDateReason" required maxlength="2000" class="w-full rounded-lg border-slate-300">
                                <button wire:loading.attr="disabled" class="rounded-lg bg-amber-700 px-4 py-2 text-white">ثبت اصلاح تاریخ با دلیل</button>
                            </form>
                        </details>
                    @endcan
                @endcan
            @endif
            @if($selected->status === 'draft')
                @can('outsourcing.approve_commission')<button type="button" wire:click="approve" wire:confirm="مبلغ نهایی و ریز کارکرد این صورتحساب را تأیید می‌کنید؟" wire:loading.attr="disabled" class="rounded-lg bg-indigo-600 px-5 py-2 text-white disabled:opacity-50">تأیید صورتحساب</button>@endcan
            @endif
            @if($selected->status === 'approved')
                @can('outsourcing.pay_commission')
                    <form wire:submit="pay" wire:confirm="آیا کل مبلغ این صورتحساب پرداخت شده و شماره پیگیری و تاریخ واقعی پرداخت صحیح هستند؟" class="space-y-3 rounded-lg bg-emerald-50 p-4">
                        <p class="text-sm text-emerald-900">ثبت رسید برای پرداخت کامل {{ \App\Support\SettlementDisplay::money($selected->net_amount) }} {{ $selected->currency }}. این عملیات انتقال بانکی انجام نمی‌دهد.</p>
                        <label for="settlement-reference" class="block text-sm">شماره پیگیری بانکی</label>
                        <input id="settlement-reference" wire:model="reference" required maxlength="191" dir="ltr" class="w-full rounded-lg border-slate-300 sm:w-80">
                        <label for="settlement-payment-date" class="block text-sm">تاریخ واقعی پرداخت (شمسی)</label>
                        <input id="settlement-payment-date" wire:model="paymentDate" required maxlength="10" dir="ltr" placeholder="۱۴۰۵/۰۷/۲۲" class="w-full rounded-lg border-slate-300 sm:w-80">
                        <p class="text-xs text-slate-600">تاریخ واریز را وارد کنید. تاریخ ثبت رسید به‌صورت خودکار ذخیره می‌شود.</p>
                        <button wire:loading.attr="disabled" class="rounded-lg bg-emerald-700 px-5 py-2 text-white disabled:opacity-50">ثبت پرداخت کامل</button>
                    </form>
                @endcan
            @endif
            @if(in_array($selected->status, ['draft', 'approved'], true))
                @can('outsourcing.approve_commission')
                    <details class="rounded-lg border border-red-200 p-3"><summary class="cursor-pointer text-sm text-red-700">لغو صورتحساب و آزادکردن کارکردها</summary>
                        <form wire:submit="cancel" wire:confirm="صورتحساب لغو و کارکردهای آن برای تسویه مجدد آزاد شوند؟" class="mt-3 flex flex-wrap gap-3">
                            <label for="settlement-cancel-reason" class="sr-only">دلیل لغو</label><input id="settlement-cancel-reason" wire:model="cancelReason" required maxlength="2000" placeholder="دلیل لغو" class="grow rounded-lg border-slate-300"><button wire:loading.attr="disabled" class="rounded-lg bg-red-700 px-4 py-2 text-white">لغو صورتحساب</button>
                        </form>
                    </details>
                @endcan
            @endif
        </section>
    @endif
</div>
