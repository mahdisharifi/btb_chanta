@extends('admin.layouts.admin')

@section('title', 'داشبورد مدیریت')

@section('content')
{{-- ویجت‌های آماری --}}
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <x-admin.stat-card
        :title="'محصولات'"
        :value="$stats['total_products']"
        :icon="'package'"
        :color="'blue'"
        :route="route('admin.products.index')"
        :subtitle="'فعال: ' . $stats['active_products'] . ' | در انتظار: ' . $stats['pending_products']"
    />

    <x-admin.stat-card
        :title="'تأمین‌کنندگان'"
        :value="$stats['total_suppliers']"
        :icon="'truck'"
        :color="'green'"
        :route="route('admin.suppliers.index')"
        :subtitle="'تأیید شده: ' . $stats['verified_suppliers']"
    />

    <x-admin.stat-card
        :title="'دسته‌بندی‌ها'"
        :value="$stats['total_categories']"
        :icon="'folder-tree'"
        :color="'purple'"
        :route="route('admin.categories.index')"
    />

    <x-admin.stat-card
        :title="'کاربران'"
        :value="$stats['total_users']"
        :icon="'users'"
        :color="'amber'"
        :route="route('admin.users.index')"
        :subtitle="'خرده‌فروش: ' . $stats['total_retailers']"
    />
</div>

{{-- ردیف دوم: ویجت‌های تکمیلی --}}
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
    <x-admin.stat-card
        :title="'قیمت‌های خرده‌فروشی'"
        :value="$stats['total_retail_prices']"
        :icon="'tag'"
        :color="'rose'"
        :route="route('admin.retail-prices.index')"
    />

    <x-admin.stat-card
        :title="'محصولات فعال'"
        :value="$stats['active_products']"
        :icon="'check-circle'"
        :color="'emerald'"
        :route="route('admin.products.index', ['status' => 'active'])"
    />

    <x-admin.stat-card
        :title="'در انتظار بررسی'"
        :value="$stats['pending_products']"
        :icon="'clock'"
        :color="'orange'"
        :route="route('admin.products.index', ['status' => 'pending_review'])"
        :highlight="true"
    />
</div>

{{-- جداول داده‌های اخیر --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    {{-- محصولات اخیر --}}
    <x-admin.recent-table
        :title="'محصولات اخیر'"
        :headers="['نام محصول', 'دسته‌بندی', 'وضعیت', 'تاریخ']"
        :rows="$recentProducts"
        :empty="'هیچ محصولی یافت نشد.'"
    >
        <x-slot name="row">
            @foreach($recentProducts as $product)
            <tr class="border-b border-gray-100 hover:bg-gray-50 transition-colors">
                <td class="px-4 py-3">
                    <a href="{{ route('admin.products.edit', $product) }}" class="text-blue-600 hover:text-blue-800 font-medium">
                        {{ $product->name }}
                    </a>
                </td>
                <td class="px-4 py-3 text-gray-600">
                    {{ $product->category?->name ?? '—' }}
                </td>
                <td class="px-4 py-3">
                    <x-admin.status-badge :status="$product->status->value" />
                </td>
                <td class="px-4 py-3 text-gray-500 text-sm" dir="ltr">
                    {{ $product->created_at->format('Y/m/d') }}
                </td>
            </tr>
            @endforeach
        </x-slot>
    </x-admin.recent-table>

    {{-- تأمین‌کنندگان اخیر --}}
    <x-admin.recent-table
        :title="'تأمین‌کنندگان اخیر'"
        :headers="['نام', 'ایمیل', 'وضعیت', 'تاریخ']"
        :rows="$recentSuppliers"
        :empty="'هیچ تأمین‌کننده‌ای یافت نشد.'"
    >
        <x-slot name="row">
            @foreach($recentSuppliers as $supplier)
            <tr class="border-b border-gray-100 hover:bg-gray-50 transition-colors">
                <td class="px-4 py-3">

                </td>
                <td class="px-4 py-3 text-gray-600" dir="ltr">
                    {{ $supplier->company_name ?? $supplier->brand_name ?? $supplier->user?->name ?? '—' }}
                </td>
                <td class="px-4 py-3">
                    @if($supplier->is_verified)
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    تأیید شده
                                </span>
                    @else
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                    در انتظار
                                </span>
                    @endif
                </td>
                <td class="px-4 py-3 text-gray-500 text-sm" dir="ltr">
                    {{ $supplier->created_at->format('Y/m/d') }}
                </td>
            </tr>
            @endforeach
        </x-slot>
    </x-admin.recent-table>

    {{-- محصولات در انتظار بررسی - تمام عرض --}}
    <x-admin.recent-table
        :title="'محصولات در انتظار بررسی'"
        :headers="['نام محصول', 'تأمین‌کننده', 'دسته‌بندی', 'قیمت', 'تاریخ ثبت']"
        :rows="$pendingProducts"
        :empty="'محصول در انتظار بررسی وجود ندارد.'"
        class="lg:col-span-2"
    >
        <x-slot name="row">
            @foreach($pendingProducts as $product)
            <tr class="border-b border-gray-100 hover:bg-gray-50 transition-colors">
                <td class="px-4 py-3">
                    <a href="{{ route('admin.products.edit', $product) }}" class="text-blue-600 hover:text-blue-800 font-medium">
                        {{ $product->name }}
                    </a>
                </td>
                <td class="px-4 py-3 text-gray-600">
                    {{ $product->supplier?->user?->name ?? '—' }}
                </td>
                <td class="px-4 py-3 text-gray-600">
                    {{ $product->category?->name ?? '—' }}
                </td>
                <td class="px-4 py-3 text-gray-700 font-medium">
                    {{ number_format($product->base_price) }} تومان
                </td>
                <td class="px-4 py-3 text-gray-500 text-sm" dir="ltr">
                    {{ $product->created_at->format('Y/m/d') }}
                </td>
            </tr>
            @endforeach
        </x-slot>
    </x-admin.recent-table>
</div>
@endsection
