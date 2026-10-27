<div class="space-y-5" dir="rtl">
    <h1 class="text-2xl font-bold">صورتحساب‌ها و تسویه‌های من</h1>
    <p class="text-sm leading-7 text-slate-600">هر صورتحساب شامل کارکرد چند تسک است. مطالبات دوره‌های قبل در ریز صورتحساب مشخص هستند. وضعیت پیش‌نویس به معنی پرداخت نیست؛ پس از ثبت پرداخت، رسید نمایش داده می‌شود.</p>
    <a class="inline-block text-indigo-700" href="{{ route('outsourcer.commissions.index') }}">مشاهده کارکرد تک‌تک تسک‌ها</a>
    @include('livewire.outsourcing.settlement-list', ['showContractor' => false])
    @if($selected)<section wire:key="own-settlement-{{ $selected->public_id }}" class="rounded-xl border bg-white p-5">@include('livewire.outsourcing.settlement-detail', ['settlement' => $selected])</section>@endif
</div>
