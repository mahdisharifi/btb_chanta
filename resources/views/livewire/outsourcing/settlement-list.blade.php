<div class="overflow-x-auto rounded-xl border bg-white">
    <table class="w-full text-right text-sm">
        <thead class="bg-slate-50"><tr>@if($showContractor)<th class="p-3">پیمانکار</th>@endif<th class="p-3">بازه شمسی</th><th class="p-3">تعداد تسک</th><th class="p-3">مبلغ نهایی</th><th class="p-3">وضعیت</th><th class="p-3">جزئیات</th></tr></thead>
        <tbody>@forelse($settlements as $statement)
            <tr wire:key="statement-{{ $statement->public_id }}" class="border-t">
                @if($showContractor)<td class="p-3">{{ $statement->user?->name }}</td>@endif
                <td class="whitespace-nowrap p-3"><bdi>{{ jdate($statement->from_date)->format('Y/m/d') }}</bdi> تا <bdi>{{ jdate($statement->to_date)->format('Y/m/d') }}</bdi></td>
                <td class="p-3">{{ $statement->items_count }}</td>
                <td class="whitespace-nowrap p-3">{{ \App\Support\SettlementDisplay::money($statement->net_amount) }} {{ $statement->currency }}</td>
                <td class="p-3">{{ ['draft' => 'پیش‌نویس', 'approved' => 'منتظر پرداخت', 'paid' => 'پرداخت‌شده', 'cancelled' => 'لغوشده'][$statement->status] ?? $statement->status }}</td>
                <td class="p-3"><button type="button" wire:click="select('{{ $statement->public_id }}')" wire:loading.attr="disabled" class="rounded-lg border border-indigo-200 px-3 py-2 text-indigo-700">مشاهده ریز و رسید</button></td>
            </tr>
        @empty<tr><td colspan="6" class="p-8 text-center text-slate-500">صورتحسابی ثبت نشده است.</td></tr>@endforelse</tbody>
    </table>
</div>
<div>{{ $settlements->links() }}</div>
