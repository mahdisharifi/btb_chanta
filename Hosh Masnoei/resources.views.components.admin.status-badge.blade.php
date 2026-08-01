@props(['status' => ''])

@php
// نرمال‌سازی: تبدیل Enum به مقدار scalar
if ($status instanceof \App\Enums\ProductStatus) {
$status = $status->value;
}

$statusMap = [
'active'         => ['bg-emerald-100 text-emerald-800', 'فعال'],
'inactive'       => ['bg-red-100 text-red-700', 'غیرفعال'],
'pending_review' => ['bg-amber-100 text-amber-800', 'در انتظار'],
'draft'          => ['bg-slate-100 text-slate-600', 'پیش‌نویس'],
'rejected'       => ['bg-rose-100 text-rose-700', 'رد شده'],
'out_of_stock'   => ['bg-orange-100 text-orange-700', 'ناموجود'],
];

[$class, $label] = $statusMap[$status] ?? ['bg-gray-100 text-gray-500', $status];
@endphp

<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $class }}">
    {{ $label }}
</span>
