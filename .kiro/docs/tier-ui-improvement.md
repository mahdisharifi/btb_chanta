# بهبود UI برای درک بهتر تیراژها

## 🎯 هدف
کاربران (خریداران و تأمین‌کنندگان) باید **به راحتی بفهمند** که:
1. تیراژها چیستند؟
2. برای چه quantity هایی استفاده می‌شوند؟
3. چطور می‌توانند مقایسه کنند؟

---

## 1️⃣ صفحه قیمت‌گذاری تأمین‌کننده

### نمایش توضیح تیراژها (Tooltip + Info Box)

```blade
{{-- قبل از ماتریس قیمت --}}
<div class="mb-6 bg-blue-50 dark:bg-blue-900/20 border-l-4 border-blue-500 p-4 rounded">
    <div class="flex items-start gap-3">
        <x-heroicon-s-information-circle class="w-6 h-6 text-blue-600 dark:text-blue-400 flex-shrink-0"/>
        <div>
            <h4 class="text-sm font-semibold text-blue-800 dark:text-blue-200">
                💡 تیراژها چیستند؟
            </h4>
            <p class="text-sm text-blue-700 dark:text-blue-300 mt-1">
                تیراژها نقاط مرجع برای مقایسه قیمت با سایر تأمین‌کنندگان هستند.
                شما باید برای <strong>هر تیراژ</strong> قیمت خود را وارد کنید تا خریداران بتوانند
                قیمت شما را با دیگران مقایسه کنند.
            </p>
            <div class="mt-3 bg-blue-100 dark:bg-blue-800/40 rounded p-3">
                <p class="text-xs text-blue-800 dark:text-blue-200 font-medium mb-2">
                    📊 تیراژهای این محصول:
                </p>
                <ul class="text-xs text-blue-700 dark:text-blue-300 space-y-1">
                    @foreach($quantityTiers as $index => $tier)
                    <li class="flex items-center gap-2">
                        <span class="inline-flex items-center justify-center w-5 h-5 bg-blue-600 text-white rounded-full text-[10px] font-bold">
                            {{ $index + 1 }}
                        </span>
                        <strong>{{ $tier['name'] }}:</strong>
                        <span>
                            {{ number_format($tier['min_quantity']) }} 
                            تا 
                            {{ $tier['max_quantity'] ? number_format($tier['max_quantity']) : '∞' }} 
                            عدد
                        </span>
                    </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</div>
```

---

## 2️⃣ صفحه محصول (نمایش برای خریدار)

### نمایش قیمت‌ها با تیراژها

```blade
<div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
    <h3 class="text-xl font-bold text-gray-800 dark:text-gray-200 mb-4">
        📊 مقایسه قیمت تأمین‌کنندگان
    </h3>
    
    {{-- توضیح تیراژها --}}
    <div class="mb-6 bg-gradient-to-r from-indigo-50 to-purple-50 dark:from-indigo-900/20 dark:to-purple-900/20 rounded-lg p-4 border border-indigo-200 dark:border-indigo-800">
        <div class="flex items-start gap-3">
            <x-heroicon-s-light-bulb class="w-6 h-6 text-indigo-600 dark:text-indigo-400 flex-shrink-0"/>
            <div>
                <h4 class="text-sm font-semibold text-indigo-800 dark:text-indigo-200 mb-2">
                    💡 چطور قیمت‌ها را مقایسه کنم؟
                </h4>
                <p class="text-sm text-indigo-700 dark:text-indigo-300">
                    قیمت‌ها بر اساس <strong>تعداد خرید</strong> متفاوت است. 
                    ما تعدادهای مشخصی را به عنوان <strong>نقطه مرجع</strong> تعریف کرده‌ایم تا بتوانید 
                    به راحتی قیمت تأمین‌کنندگان را با هم مقایسه کنید.
                </p>
            </div>
        </div>
    </div>
    
    {{-- جدول مقایسه --}}
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 dark:bg-gray-900">
                <tr>
                    <th class="px-4 py-3 text-right text-sm font-semibold text-gray-700 dark:text-gray-300">
                        تأمین‌کننده
                    </th>
                    @foreach($product->quantity_tiers as $index => $tier)
                    <th class="px-4 py-3 text-center text-sm font-semibold text-gray-700 dark:text-gray-300">
                        <div class="flex flex-col items-center gap-1">
                            <span class="inline-flex items-center justify-center w-6 h-6 bg-indigo-600 text-white rounded-full text-xs font-bold">
                                {{ $index + 1 }}
                            </span>
                            <span class="font-bold">{{ $tier['name'] }}</span>
                            <span class="text-xs text-gray-500 dark:text-gray-400 font-normal">
                                {{ number_format($tier['min_quantity']) }} 
                                - 
                                {{ $tier['max_quantity'] ? number_format($tier['max_quantity']) : '∞' }} 
                                عدد
                            </span>
                        </div>
                    </th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                @foreach($suppliers as $supplier)
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                    <td class="px-4 py-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-full flex items-center justify-center text-white font-bold">
                                {{ mb_substr($supplier->user->name, 0, 1) }}
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">
                                    {{ $supplier->user->name }}
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    ⭐ {{ $supplier->rating ?? '۴.۵' }}
                                </p>
                            </div>
                        </div>
                    </td>
                    @foreach($product->quantity_tiers as $index => $tier)
                    <td class="px-4 py-4 text-center">
                        @php
                            $price = $supplier->getPriceForTier($index, $priceTypeId);
                        @endphp
                        @if($price)
                            <div class="flex flex-col items-center gap-1">
                                <span class="text-lg font-bold text-gray-800 dark:text-gray-200">
                                    {{ number_format($price) }}
                                </span>
                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                    تومان
                                </span>
                                {{-- نمایش تعداد واقعی برای درک بهتر --}}
                                <span class="text-[10px] text-gray-400 dark:text-gray-500">
                                    برای هر عدد در این بازه
                                </span>
                            </div>
                        @else
                            <span class="text-sm text-gray-400 dark:text-gray-500">
                                قیمت ندارد
                            </span>
                        @endif
                    </td>
                    @endforeach
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    
    {{-- توضیح اضافی --}}
    <div class="mt-6 bg-amber-50 dark:bg-amber-900/20 rounded-lg p-4 border border-amber-200 dark:border-amber-800">
        <div class="flex items-start gap-3">
            <x-heroicon-s-exclamation-triangle class="w-5 h-5 text-amber-600 dark:text-amber-400 flex-shrink-0 mt-0.5"/>
            <div class="text-sm text-amber-800 dark:text-amber-200">
                <p class="font-semibold mb-1">
                    🤝 آیا تعداد دقیق شما در جدول نیست؟
                </p>
                <p>
                    این تیراژها فقط برای مقایسه هستند. اگر می‌خواهید تعداد دقیقی (مثلاً ۱۵۰ عدد) بخرید،
                    می‌توانید با تأمین‌کننده انتخابی خود تماس بگیرید و قیمت دقیق را بپرسید.
                </p>
                <button class="mt-2 text-amber-700 dark:text-amber-300 font-semibold hover:underline">
                    📞 مشاهده اطلاعات تماس تأمین‌کنندگان
                </button>
            </div>
        </div>
    </div>
</div>
```

---

## 3️⃣ قسمت ماتریس قیمت (تأمین‌کننده)

### نمایش بهتر هر تیراژ با مثال

```blade
@foreach($quantityTiers as $tierIndex => $tier)
<div class="border-2 border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden hover:border-indigo-400 transition-colors">
    {{-- Header تیراژ --}}
    <div class="bg-gradient-to-r from-indigo-50 to-purple-50 dark:from-indigo-900/40 dark:to-purple-900/40 px-6 py-4 border-b border-gray-200 dark:border-gray-700">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                <div class="flex items-center justify-center w-12 h-12 bg-indigo-600 text-white rounded-full text-xl font-bold shadow-lg">
                    {{ $tierIndex + 1 }}
                </div>
                <div>
                    <h4 class="text-lg font-bold text-gray-800 dark:text-gray-200">
                        {{ $tier['name'] }}
                    </h4>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        <span class="inline-flex items-center gap-1">
                            <x-heroicon-s-chart-bar class="w-4 h-4 text-indigo-600 dark:text-indigo-400"/>
                            برای خرید 
                            <strong class="text-indigo-600 dark:text-indigo-400">
                                {{ number_format($tier['min_quantity']) }}
                            </strong>
                            تا
                            <strong class="text-indigo-600 dark:text-indigo-400">
                                {{ $tier['max_quantity'] ? number_format($tier['max_quantity']) : '∞' }}
                            </strong>
                            عدد
                        </span>
                    </p>
                </div>
            </div>
            
            {{-- نمایش مثال --}}
            <div class="hidden md:block bg-white dark:bg-gray-800 rounded-lg px-4 py-2 border border-indigo-200 dark:border-indigo-800">
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">مثال:</p>
                <p class="text-sm text-gray-700 dark:text-gray-300">
                    اگر خریدار <strong class="text-indigo-600">{{ number_format($tier['min_quantity'] + 10) }} عدد</strong> بخواهد،
                    قیمت این تیراژ نمایش داده می‌شود
                </p>
            </div>
        </div>
    </div>
    
    {{-- محتوای قیمت‌ها --}}
    <div class="p-6 space-y-4">
        @foreach($priceTypes as $priceType)
        <div class="bg-gray-50 dark:bg-gray-900/50 rounded-lg p-4">
            {{-- ... فرم قیمت‌گذاری --}}
        </div>
        @endforeach
    </div>
</div>
@endforeach
```

---

## 4️⃣ قسمت جستجوی محصول (با فیلتر quantity)

```blade
<div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 mb-6">
    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">
        🔍 جستجو بر اساس تعداد
    </h3>
    
    <div class="flex items-center gap-4">
        <div class="flex-1">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                چند عدد می‌خواهید بخرید؟
            </label>
            <input 
                type="number" 
                wire:model.live="searchQuantity"
                placeholder="مثلاً: 150"
                class="w-full px-4 py-3 border-2 border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
            >
        </div>
        <button class="px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-semibold transition-colors">
            مقایسه قیمت‌ها
        </button>
    </div>
    
    @if($searchQuantity)
    <div class="mt-4 bg-indigo-50 dark:bg-indigo-900/20 rounded-lg p-4 border border-indigo-200 dark:border-indigo-800">
        <p class="text-sm text-indigo-700 dark:text-indigo-300">
            💡 برای خرید <strong>{{ number_format($searchQuantity) }} عدد</strong>، 
            قیمت تیراژ <strong>"{{ $this->getMatchingTier($searchQuantity)['name'] }}"</strong> 
            نمایش داده می‌شود.
        </p>
    </div>
    @endif
</div>
```

---

## 5️⃣ Tooltip برای هر تیراژ

```blade
<div x-data="{ showTooltip: false }" class="relative inline-block">
    <button 
        @mouseenter="showTooltip = true"
        @mouseleave="showTooltip = false"
        class="text-gray-500 hover:text-indigo-600 transition-colors"
    >
        <x-heroicon-s-question-mark-circle class="w-5 h-5"/>
    </button>
    
    <div 
        x-show="showTooltip"
        x-transition
        class="absolute z-10 bottom-full left-1/2 -translate-x-1/2 mb-2 w-64 bg-gray-900 text-white text-xs rounded-lg shadow-xl p-3"
    >
        <div class="absolute -bottom-1 left-1/2 -translate-x-1/2 w-2 h-2 bg-gray-900 rotate-45"></div>
        <p class="font-semibold mb-1">{{ $tier['name'] }}</p>
        <p class="text-gray-300">
            این تیراژ برای خریدهای بین {{ number_format($tier['min_quantity']) }} 
            تا {{ $tier['max_quantity'] ? number_format($tier['max_quantity']) : 'نامحدود' }} 
            عدد استفاده می‌شود.
        </p>
        <p class="text-gray-400 mt-2 text-[10px]">
            💡 برای تعداد دقیق خود، با تأمین‌کننده تماس بگیرید
        </p>
    </div>
</div>
```

---

## 📱 نمای موبایل

```blade
<div class="md:hidden space-y-4">
    @foreach($quantityTiers as $index => $tier)
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden">
        <div class="bg-indigo-600 text-white px-4 py-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 bg-white text-indigo-600 rounded-full flex items-center justify-center font-bold text-sm">
                        {{ $index + 1 }}
                    </div>
                    <div>
                        <p class="font-bold text-sm">{{ $tier['name'] }}</p>
                        <p class="text-xs opacity-90">
                            {{ number_format($tier['min_quantity']) }} - {{ $tier['max_quantity'] ? number_format($tier['max_quantity']) : '∞' }} عدد
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="p-4 space-y-3">
            @foreach($suppliers as $supplier)
            <div class="flex items-center justify-between py-2 border-b border-gray-200 dark:border-gray-700 last:border-0">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-full flex items-center justify-center text-white text-xs font-bold">
                        {{ mb_substr($supplier->user->name, 0, 1) }}
                    </div>
                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ $supplier->user->name }}</span>
                </div>
                <span class="text-sm font-bold text-gray-800 dark:text-gray-200">
                    {{ number_format($supplier->getPriceForTier($index, $priceTypeId)) }} تومان
                </span>
            </div>
            @endforeach
        </div>
    </div>
    @endforeach
</div>
```

---

## ✅ خلاصه بهبودها

| بخش | بهبود |
|-----|-------|
| **Info Box** | توضیح واضح درباره تیراژها با مثال |
| **Visual Design** | استفاده از نمادها، رنگ‌ها، و آیکون‌ها |
| **مثال‌های واقعی** | نمایش quantity دقیق برای درک بهتر |
| **Tooltip** | راهنمای سریع روی هر تیراژ |
| **Mobile-First** | طراحی ریسپانسیو برای موبایل |
| **CTA واضح** | دکمه "تماس با تأمین‌کننده" برای quantity های خاص |

این UI به کاربران کمک می‌کند تا:
1. ✅ بفهمند تیراژها چیستند
2. ✅ ببینند قیمت‌ها برای چه quantity هایی هستند
3. ✅ به راحتی مقایسه کنند
4. ✅ بدانند برای تعداد دقیق باید چکار کنند
