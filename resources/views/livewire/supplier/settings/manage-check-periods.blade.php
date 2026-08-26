<div>
    {{-- Notification Toast --}}
    <div x-data="{ show: false, message: '', type: 'success' }" 
         @notify.window="
            show = true; 
            message = $event.detail.message; 
            type = $event.detail.type || 'success';
            setTimeout(() => show = false, 5000)
         "
         x-show="show"
         x-cloak
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 transform translate-y-2"
         x-transition:enter-end="opacity-100 transform translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed top-4 left-1/2 transform -translate-x-1/2 z-50 max-w-md w-full px-4">
        <div :class="{
            'bg-green-50 border-green-400 dark:bg-green-900/40 dark:border-green-600': type === 'success',
            'bg-red-50 border-red-400 dark:bg-red-900/40 dark:border-red-600': type === 'error',
            'bg-blue-50 border-blue-400 dark:bg-blue-900/40 dark:border-blue-600': type === 'info'
        }" class="flex items-center gap-3 p-4 border-2 rounded-lg shadow-xl">
            <div :class="{
                'text-green-600 dark:text-green-300': type === 'success',
                'text-red-600 dark:text-red-300': type === 'error',
                'text-blue-600 dark:text-blue-300': type === 'info'
            }">
                <svg x-show="type === 'success'" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <svg x-show="type === 'error'" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <svg x-show="type === 'info'" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <div class="flex-1">
                <p :class="{
                    'text-green-900 dark:text-green-50': type === 'success',
                    'text-red-900 dark:text-red-50': type === 'error',
                    'text-blue-900 dark:text-blue-50': type === 'info'
                }" class="text-sm font-bold" x-text="message"></p>
            </div>
            <button @click="show = false" :class="{
                'text-green-700 hover:text-green-900 dark:text-green-300 dark:hover:text-green-100': type === 'success',
                'text-red-700 hover:text-red-900 dark:text-red-300 dark:hover:text-red-100': type === 'error',
                'text-blue-700 hover:text-blue-900 dark:text-blue-300 dark:hover:text-blue-100': type === 'info'
            }" class="transition-colors flex-shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
    </div>

    {{-- ═══════════ Page Header ═══════════ --}}
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-200">مدیریت دوره‌های چکی</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
            دوره‌های پرداخت چکی که در قیمت‌گذاری محصولات شما استفاده می‌شود.
        </p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- ستون چپ: فرم --}}
        <div class="lg:col-span-1">
            <x-card>
                <div class="p-6">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4 flex items-center gap-2">
                        <x-heroicon-o-plus-circle class="w-5 h-5 text-indigo-600"/>
                        افزودن دوره جدید
                    </h2>

                    <form wire:submit="save" class="space-y-4">
                        <div>
                            <x-input-label for="name" value="نام دوره *" />
                            <x-text-input wire:model="name" id="name" type="text" class="mt-1 block w-full" placeholder="مثال: چک 45 روزه" />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="days" value="تعداد روز *" />
                            <x-text-input wire:model="days" id="days" type="number" class="mt-1 block w-full" placeholder="45" onwheel="this.blur()" />
                            <x-input-error :messages="$errors->get('days')" class="mt-2" />
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">بین 1 تا 365 روز</p>
                        </div>

                        <div>
                            <label for="is_active" class="flex items-center cursor-pointer">
                                <x-checkbox wire:model="is_active" id="is_active" />
                                <span class="ms-3 text-sm text-gray-600 dark:text-gray-300">فعال است</span>
                            </label>
                        </div>

                        <div class="pt-2">
                            <x-primary-button class="w-full">
                                <x-heroicon-o-check-circle class="w-4 h-4 mr-2"/>
                                افزودن دوره
                            </x-primary-button>
                        </div>
                    </form>
                </div>
            </x-card>
        </div>

        {{-- ستون راست: لیست‌ها --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- دوره‌های پیش‌فرض سیستم --}}
            <x-card>
                <div class="p-6">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4 flex items-center gap-2">
                        <x-heroicon-s-star class="w-5 h-5 text-yellow-500"/>
                        دوره‌های پیش‌فرض سیستم
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                        این دوره‌ها برای همه تامین‌کننده‌ها در دسترس است و قابل ویرایش نیستند.
                    </p>

                    @if(empty($globalPeriods))
                        <div class="text-center py-8 text-gray-400">
                            <x-heroicon-o-inbox class="w-12 h-12 mx-auto mb-2 opacity-50"/>
                            <p class="text-sm">دوره پیش‌فرضی تعریف نشده است.</p>
                        </div>
                    @else
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            @foreach($globalPeriods as $period)
                                <div class="flex items-center justify-between p-3 bg-green-50 dark:bg-green-900/10 border border-green-200 dark:border-green-800 rounded-lg">
                                    <div>
                                        <p class="font-medium text-gray-800 dark:text-gray-200">{{ $period['name'] }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $period['days'] }} روز</p>
                                    </div>
                                    <x-heroicon-s-check-circle class="w-6 h-6 text-green-600 dark:text-green-400"/>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </x-card>

            {{-- دوره‌های اختصاصی تامین‌کننده --}}
            <x-card>
                <div class="p-6">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4 flex items-center gap-2">
                        <x-heroicon-s-cog-6-tooth class="w-5 h-5 text-indigo-600"/>
                        دوره‌های اختصاصی من
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                        دوره‌هایی که خودتان تعریف کرده‌اید و فقط برای شما قابل استفاده است.
                    </p>

                    @if(empty($supplierPeriods))
                        <div class="text-center py-12 text-gray-400">
                            <x-heroicon-o-document-plus class="w-16 h-16 mx-auto mb-3 opacity-50"/>
                            <p class="text-sm">هنوز دوره اختصاصی ایجاد نکرده‌اید.</p>
                            <p class="text-xs mt-1">از فرم سمت چپ برای افزودن استفاده کنید.</p>
                        </div>
                    @else
                        <div class="space-y-3">
                            @foreach($supplierPeriods as $period)
                                @php
                                    $hasUsage = !$this->canDelete($period['id']);
                                    $usageCount = $hasUsage ? \App\Models\CheckPeriod::find($period['id'])->productPrices()->count() : 0;
                                    $isEditing = $editingInline === $period['id'];
                                @endphp
                                
                                @if($isEditing)
                                    {{-- حالت ویرایش Inline --}}
                                    <div class="p-4 bg-blue-50 dark:bg-blue-900/10 border-2 border-blue-300 dark:border-blue-700 rounded-lg" wire:key="edit-{{ $period['id'] }}">
                                        <form wire:submit.prevent="saveInlineEdit" class="space-y-3">
                                            <div class="grid grid-cols-2 gap-3">
                                                <div>
                                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">نام دوره *</label>
                                                    <input type="text" wire:model.live="inlineName" value="{{ $inlineName }}" class="block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm text-sm" placeholder="مثال: چک 45 روزه" />
                                                    <x-input-error :messages="$errors->get('inlineName')" class="mt-1" />
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">تعداد روز *</label>
                                                    <input type="number" wire:model.live="inlineDays" value="{{ $inlineDays }}" onwheel="this.blur()" class="block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm text-sm" placeholder="45" />
                                                    <x-input-error :messages="$errors->get('inlineDays')" class="mt-1" />
                                                </div>
                                            </div>
                                            
                                            <div class="flex items-center justify-between">
                                                <label class="flex items-center cursor-pointer">
                                                    <input type="checkbox" wire:model.live="inlineIsActive" {{ $inlineIsActive ? 'checked' : '' }} class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500" />
                                                    <span class="ms-2 text-sm text-gray-600 dark:text-gray-300">فعال است</span>
                                                </label>
                                                
                                                <div class="flex gap-2">
                                                    <button type="button" wire:click="cancelInlineEdit" class="px-3 py-1.5 text-xs border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-50 dark:hover:bg-gray-800">
                                                        انصراف
                                                    </button>
                                                    <button type="submit" class="px-3 py-1.5 text-xs bg-blue-600 text-white rounded-md hover:bg-blue-700 flex items-center gap-1">
                                                        <x-heroicon-s-check class="w-3 h-3"/>
                                                        ذخیره
                                                    </button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                @else
                                    {{-- حالت نمایش عادی --}}
                                    <div class="flex items-center justify-between p-4 bg-white dark:bg-gray-800 border {{ $hasUsage ? 'border-orange-300 dark:border-orange-700' : 'border-gray-200 dark:border-gray-700' }} rounded-lg hover:shadow-md transition-shadow">
                                        <div class="flex items-center gap-3 flex-1">
                                            <div class="flex-1">
                                                <div class="flex items-center gap-2">
                                                    <p class="font-medium text-gray-800 dark:text-gray-200">{{ $period['name'] }}</p>
                                                    @if($hasUsage)
                                                        <span class="px-2 py-0.5 bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-300 text-xs rounded-full flex items-center gap-1" title="در {{ $usageCount }} محصول استفاده شده">
                                                            <x-heroicon-s-lock-closed class="w-3 h-3"/>
                                                            قفل شده
                                                        </span>
                                                    @endif
                                                </div>
                                                <div class="flex items-center gap-2 mt-1">
                                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $period['days'] }} روز</p>
                                                    @if($hasUsage)
                                                        <span class="text-xs text-orange-600 dark:text-orange-400">• در {{ $usageCount }} محصول</span>
                                                    @endif
                                                </div>
                                            </div>
                                            @if($period['is_active'])
                                                <span class="px-2 py-0.5 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 text-xs rounded-full">فعال</span>
                                            @else
                                                <span class="px-2 py-0.5 bg-gray-100 dark:bg-gray-900/30 text-gray-600 dark:text-gray-400 text-xs rounded-full">غیرفعال</span>
                                            @endif
                                        </div>

                                        <div class="flex items-center gap-2">
                                            <button type="button" 
                                                    wire:click="toggleActive({{ $period['id'] }})"
                                                    class="p-2 text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors"
                                                    title="{{ $period['is_active'] ? 'غیرفعال کردن' : 'فعال کردن' }}">
                                                @if($period['is_active'])
                                                    <x-heroicon-o-eye class="w-5 h-5"/>
                                                @else
                                                    <x-heroicon-o-eye-slash class="w-5 h-5"/>
                                                @endif
                                            </button>

                                            @if($hasUsage)
                                                <button type="button" 
                                                        disabled
                                                        class="p-2 text-gray-400 dark:text-gray-600 cursor-not-allowed rounded-lg opacity-50"
                                                        title="این دوره در {{ $usageCount }} محصول استفاده شده و قابل ویرایش نیست">
                                                    <x-heroicon-o-pencil class="w-5 h-5"/>
                                                </button>
                                            @else
                                                <button type="button" 
                                                        wire:click="startInlineEdit({{ $period['id'] }})"
                                                        class="p-2 text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-lg transition-colors"
                                                        title="ویرایش">
                                                    <x-heroicon-o-pencil class="w-5 h-5"/>
                                                </button>
                                            @endif

                                            @if($hasUsage)
                                                <button type="button" 
                                                        disabled
                                                        class="p-2 text-gray-400 dark:text-gray-600 cursor-not-allowed rounded-lg opacity-50"
                                                        title="این دوره در {{ $usageCount }} محصول استفاده شده و قابل حذف نیست">
                                                    <x-heroicon-o-trash class="w-5 h-5"/>
                                                </button>
                                            @else
                                                <button type="button" 
                                                        wire:click="delete({{ $period['id'] }})"
                                                        wire:confirm="آیا از حذف این دوره اطمینان دارید؟"
                                                        class="p-2 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors"
                                                        title="حذف">
                                                    <x-heroicon-o-trash class="w-5 h-5"/>
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>
            </x-card>
        </div>
    </div>
</div>
