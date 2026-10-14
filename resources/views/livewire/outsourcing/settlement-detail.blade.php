<div class="space-y-4">
    <h2 class="text-lg font-bold">ریز صورتحساب — {{ $settlement->user?->name }}</h2>
    <p class="text-xs text-slate-500">شناسه: <bdi>{{ $settlement->public_id }}</bdi></p>
    <div class="grid gap-3 rounded-lg bg-slate-50 p-4 text-sm sm:grid-cols-3">
        <p>کارکرد این بازه: <strong>{{ \App\Support\SettlementDisplay::money($settlement->period_amount) }} {{ $settlement->currency }}</strong></p>
        <p>مطالبات قبلی: <strong>{{ \App\Support\SettlementDisplay::money($settlement->previous_amount) }} {{ $settlement->currency }}</strong></p>
        <p>مجموع کارکرد: <strong>{{ \App\Support\SettlementDisplay::money($settlement->gross_amount) }} {{ $settlement->currency }}</strong></p>
        <p>تعدیل صورتحساب: <strong>{{ \App\Support\SettlementDisplay::money($settlement->adjustment_amount) }}</strong></p>
        <p>مبلغ نهایی: <strong>{{ \App\Support\SettlementDisplay::money($settlement->net_amount) }} {{ $settlement->currency }}</strong></p>
    </div>
    @if($settlement->adjustment_reason)<p class="text-sm">دلیل تعدیل: {{ $settlement->adjustment_reason }}</p>@endif
    @if($settlement->cancel_reason)<p class="rounded-lg bg-red-50 p-3 text-sm text-red-800">دلیل لغو: {{ $settlement->cancel_reason }}</p>@endif
    <div class="overflow-x-auto">
        <table class="w-full text-right text-sm"><thead class="bg-slate-50"><tr><th class="p-3">محصول / تسک</th><th class="p-3">بازه کارکرد</th><th class="p-3">مبلغ ثبت‌شده</th></tr></thead>
            <tbody>@foreach($statementItems as $item)
                <tr class="border-t"><td class="p-3">{{ data_get($item->snapshot_json, 'product_title', '—') }}<bdi class="mt-1 block text-xs text-slate-500">{{ data_get($item->snapshot_json, 'task_public_id') }}</bdi></td><td class="p-3">{{ $item->is_previous ? 'مطالبات دوره‌های قبل' : 'کارکرد این بازه' }}</td><td class="p-3">{{ \App\Support\SettlementDisplay::money($item->amount) }} {{ $settlement->currency }}</td></tr>
                @if(data_get($item->snapshot_json, 'breakdown_json'))
                    <tr><td colspan="3" class="px-3 pb-3">
                        <details class="rounded-lg border border-slate-200 p-3">
                            <summary class="cursor-pointer text-xs font-semibold text-indigo-700">جزئیات محاسبه کارکرد در زمان صدور</summary>
                            <div class="mt-3 grid gap-2 text-xs sm:grid-cols-3">
                                @foreach(['base_amount' => 'مبلغ پایه', 'revision_amount' => 'اصلاحات', 'bonus_amount' => 'پاداش', 'penalty_amount' => 'کسر مبلغ', 'adjustment_amount' => 'تعدیل تسک'] as $amountKey => $amountLabel)
                                    <p>{{ $amountLabel }}: {{ \App\Support\SettlementDisplay::money((string) data_get($item->snapshot_json, $amountKey, '0')) }}</p>
                                @endforeach
                            </div>
                            @php($units = data_get($item->snapshot_json, 'breakdown_json.base_units', []))
                            @if(is_array($units) && $units !== [])
                                <table class="mt-3 w-full text-right text-xs"><thead><tr><th class="p-2">نوع محتوا</th><th class="p-2">تعداد قابل محاسبه</th><th class="p-2">مبلغ واحد</th><th class="p-2">مبلغ</th></tr></thead><tbody>
                                @foreach($units as $type => $unit)
                                    <tr class="border-t"><td class="p-2">{{ ['attribute' => 'ویژگی', 'image' => 'تصویر', 'description' => 'توضیحات', 'title' => 'عنوان', 'video' => 'ویدئو'][$type] ?? $type }}</td><td class="p-2">{{ $unit['payable_units'] ?? $unit['actual_units'] ?? 0 }}</td><td class="p-2">{{ \App\Support\SettlementDisplay::money((string) ($unit['unit_amount'] ?? '0')) }}</td><td class="p-2">{{ \App\Support\SettlementDisplay::money((string) ($unit['amount'] ?? '0')) }}</td></tr>
                                @endforeach
                                </tbody></table>
                            @endif
                            <p class="mt-2 text-xs text-slate-500">مبلغ قابل تسویه همین ردیف، پس از اعمال نرخ‌ها، تعدیلات و سقف تسک ثبت شده است.</p>
                        </details>
                    </td></tr>
                @endif
            @endforeach</tbody>
        </table>
    </div>
    {{ $statementItems->links() }}
    @foreach($settlement->payments as $payment)
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-950">
            <h3 class="mb-2 font-bold">رسید پرداخت کامل</h3>
            <p>مبلغ: {{ \App\Support\SettlementDisplay::money($payment->amount) }} {{ $settlement->currency }}</p>
            <p>شماره پیگیری: <bdi>{{ $payment->reference }}</bdi></p>
            <p>تاریخ واقعی پرداخت: <bdi>{{ $payment->paid_on ? jdate($payment->paid_on)->format('Y/m/d') : 'برای این رسید قدیمی ثبت نشده است' }}</bdi></p>
            <p>تاریخ ثبت رسید: <bdi>{{ jdate($payment->created_at->copy()->timezone('Asia/Tehran'))->format('Y/m/d H:i') }}</bdi></p>
        </div>
    @endforeach
</div>
