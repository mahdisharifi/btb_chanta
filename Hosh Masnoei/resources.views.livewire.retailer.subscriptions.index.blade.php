<div>
    <x-admin.subheader title="تهیه اشتراک" />

    <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">

        @if(session('success'))
        <x-alert type="success" :message="session('success')" class="mb-4" />
        @endif

        @if(session('error'))
        <x-alert type="error" :message="session('error')" class="mb-4" />
        @endif

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            @foreach($plans as $plan)
            <div class="border rounded-2xl p-6 shadow-sm flex flex-col items-center text-center {{ $currentSubscription && $currentSubscription->subscription_plan_id === $plan->id ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200' }}">
                <h3 class="text-xl font-bold text-gray-800 mb-2">{{ $plan->name }}</h3>
                <p class="text-gray-500 text-sm mb-4">{{ $plan->description }}</p>

                <div class="text-3xl font-black text-indigo-600 mb-4 flex items-center gap-1">
                    {{ number_format($plan->price) }} <span class="text-sm text-gray-500 font-normal">تومان</span>
                </div>

                <div class="text-sm font-medium text-gray-600 mb-6 bg-gray-100 px-3 py-1 rounded-full">
                    اعتبار: {{ $plan->duration_days }} روز
                </div>

                @if($currentSubscription && $currentSubscription->subscription_plan_id === $plan->id)
                <button disabled class="w-full py-2 px-4 rounded-lg bg-gray-300 text-gray-700 font-medium cursor-not-allowed">
                    طرح فعلی شما
                </button>
                @else
                <!-- فراخوانی مستقیم متد برای تست موفقیت آمیز -->
                <button wire:click="subscribe({{ $plan->id }})"
                        wire:loading.attr="disabled"
                        class="w-full py-2 px-4 rounded-lg bg-indigo-600 text-white font-medium hover:bg-indigo-700 transition-colors">
                    <span wire:loading.remove wire:target="subscribe({{ $plan->id }})">انتخاب و ارتقا حساب (تست)</span>
                    <span wire:loading wire:target="subscribe({{ $plan->id }})">در حال پردازش...</span>
                </button>
                @endif
            </div>
            @endforeach
        </div>
    </div>
</div>
