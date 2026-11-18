@props(['model', 'label', 'id'])

@php
    $today = jdate(now('Asia/Tehran'));
    $monthStart = $today->getFirstDayOfMonth()->toCarbon()->format('Y-m-d');
@endphp

<div class="relative text-sm" x-data="{
    value: $wire.entangle(@js($model)).live,
    open: false,
    cursor: new Date(@js($monthStart . 'T12:00:00Z')),
    today: @js($today->format('Y/m/d')),
    formatter: new Intl.DateTimeFormat('en-US-u-ca-persian-nu-latn', { year: 'numeric', month: 'numeric', day: 'numeric', timeZone: 'UTC' }),
    months: ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'],
    latin(value) {
        return String(value || '').trim().replace(/[۰-۹٠-٩]/g, digit => '۰۱۲۳۴۵۶۷۸۹'.includes(digit) ? '۰۱۲۳۴۵۶۷۸۹'.indexOf(digit) : '٠١٢٣٤٥٦٧٨٩'.indexOf(digit));
    },
    persian(value) {
        return String(value).replace(/[0-9]/g, digit => '۰۱۲۳۴۵۶۷۸۹'[digit]);
    },
    parts(date = this.cursor) {
        const fields = Object.fromEntries(this.formatter.formatToParts(date).map(part => [part.type, part.value]));
        return { year: Number(fields.year), month: Number(fields.month), day: Number(fields.day) };
    },
    monthDate(direction, date = this.cursor) {
        const next = new Date(date);
        next.setUTCDate(next.getUTCDate() + (direction > 0 ? 32 : -1));
        next.setUTCDate(next.getUTCDate() - this.parts(next).day + 1);
        return next;
    },
    move(direction) {
        const next = this.monthDate(direction);
        const year = this.parts(next).year;
        if (year >= 1300 && year <= 1499) this.cursor = next;
    },
    syncCalendar() {
        const match = this.latin(this.value).match(/^(1[34][0-9]{2})\/([0-9]{2})\/([0-9]{2})$/);
        if (!match || Number(match[2]) < 1 || Number(match[2]) > 12) return;
        const current = this.parts();
        const difference = (Number(match[1]) - current.year) * 12 + Number(match[2]) - current.month;
        for (let index = 0; index < Math.abs(difference); index++) this.move(Math.sign(difference));
    },
    get heading() {
        const current = this.parts();
        return this.months[current.month - 1] + ' ' + this.persian(current.year);
    },
    get days() {
        const current = this.parts();
        const count = Math.round((this.monthDate(1) - this.cursor) / 86400000);
        const offset = (this.cursor.getUTCDay() + 1) % 7;
        return [...Array(offset).fill(null), ...Array.from({ length: count }, (_, index) => ({
            day: index + 1,
            value: current.year + '/' + String(current.month).padStart(2, '0') + '/' + String(index + 1).padStart(2, '0')
        }))];
    },
    choose(value) {
        this.value = this.persian(value);
        this.open = false;
    },
    init() {
        this.syncCalendar();
        this.$watch('value', () => this.syncCalendar());
    }
}" @click.outside="open = false" @keydown.escape.stop="open = false">
    <label for="{{ $id }}" class="block">{{ $label }} (شمسی)</label>
    <div class="mt-1 flex rounded-lg border border-slate-300 bg-white focus-within:ring-2 focus-within:ring-indigo-500">
        <input id="{{ $id }}" type="text" x-model.lazy="value" dir="ltr" inputmode="numeric" maxlength="10" placeholder="۱۴۰۵/۰۸/۱۹" autocomplete="off" class="min-w-0 flex-1 rounded-lg border-0 text-left focus:ring-0" @focus="syncCalendar(); open = true" @keydown.enter.prevent="value = $el.value; open = false">
        <button type="button" @click="syncCalendar(); open = !open" :aria-expanded="open" aria-controls="{{ $id }}-calendar" aria-label="باز کردن تقویم {{ $label }}" class="shrink-0 rounded-lg px-3 text-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 2v4m8-4v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z"/></svg>
        </button>
    </div>
    <div id="{{ $id }}-calendar" x-cloak x-show="open" role="dialog" aria-label="تقویم شمسی {{ $label }}" class="absolute right-0 z-40 mt-2 w-72 max-w-[calc(100vw-3rem)] rounded-xl border border-slate-200 bg-white p-3 shadow-xl" dir="rtl">
        <div class="mb-3 flex items-center justify-between">
            <button type="button" @click="move(-1)" aria-label="ماه قبل" class="rounded-lg px-2 py-1 hover:bg-slate-100">قبلی</button>
            <strong x-text="heading" aria-live="polite"></strong>
            <button type="button" @click="move(1)" aria-label="ماه بعد" class="rounded-lg px-2 py-1 hover:bg-slate-100">بعدی</button>
        </div>
        <div class="grid grid-cols-7 gap-1 text-center">
            @foreach(['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج'] as $weekday)<span class="py-1 text-xs text-slate-500">{{ $weekday }}</span>@endforeach
            <template x-for="(entry, index) in days" :key="index">
                <div>
                    <template x-if="entry">
                        <button type="button" @click="choose(entry.value)" x-text="persian(entry.day)" :aria-label="entry.value" :aria-pressed="latin(value) === entry.value" :class="latin(value) === entry.value ? 'bg-indigo-600 text-white' : (today === entry.value ? 'bg-indigo-50 text-indigo-800' : 'text-slate-700 hover:bg-slate-100')" class="h-8 w-full rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"></button>
                    </template>
                </div>
            </template>
        </div>
        <div class="mt-3 flex justify-between border-t border-slate-100 pt-2">
            <button type="button" @click="choose(today)" class="rounded-lg px-2 py-1 text-indigo-700">امروز</button>
            <button type="button" @click="choose('')" class="rounded-lg px-2 py-1 text-slate-600">پاک کردن تاریخ</button>
        </div>
    </div>
</div>
