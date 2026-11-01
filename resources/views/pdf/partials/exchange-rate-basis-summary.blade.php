@if(!empty($exchangeRateBasis))
<div dir="rtl" style="margin: 12px 0; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 6px; background: #f9fafb; color: #111827; font-size: 11px; line-height: 1.7;">
    <strong>نرخ {{ $exchangeRateBasis['currency_label'] }}:</strong>
    @if(!empty($exchangeRateBasis['rate_display']))
        {{ $exchangeRateBasis['rate_display'] }} تومان
        @if(!empty($exchangeRateBasis['last_fetched_at']))
            (آخرین دریافت موفق: {{ $exchangeRateBasis['last_fetched_at'] }})
        @endif
    @else
        {{ $exchangeRateBasis['status'] }}
    @endif
    @if(!empty($currencySnapshotWarning))
        <div role="status" style="margin-top: 4px; color: #92400e;">
            نرخ ذخیره‌شدهٔ بعضی کالاها با نرخ تنظیم‌شده یکسان نیست یا معتبر نیست؛ مبلغ فقط با نرخ ذخیره‌شدهٔ معتبر و هم‌ارز نمایش داده می‌شود و در غیر این صورت ناموجود است.
        </div>
    @endif
</div>
@endif
