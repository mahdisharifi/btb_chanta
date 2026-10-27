
<div class="min-h-screen bg-gray-50 dark:bg-gray-900 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        {{-- Header Section --}}
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100 flex items-center gap-3">
                        <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center shadow-lg">
                            <x-heroicon-o-eye class="w-6 h-6 text-white" />
                        </div>
                        مدیریت دسترسی نمایش قیمت
                    </h1>
                    <p class="mt-2 text-gray-600 dark:text-gray-400">
                        تنظیم دسترسی‌های نمایش قیمت برای هر نقش کاربری
                    </p>
                </div>
                
                <button wire:click="clearAllCache" 
                        class="inline-flex items-center gap-2 px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                    <x-heroicon-o-arrow-path class="w-5 h-5" />
                    <span>پاک کردن کش</span>
                </button>
            </div>
        </div>

        {{-- Info Box --}}
        <div class="mb-6 bg-blue-50 dark:bg-blue-900/20 border-r-4 border-blue-500 rounded-lg p-4">
            <div class="flex items-start gap-3">
                <x-heroicon-o-information-circle class="w-6 h-6 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5" />
                <div class="text-sm text-blue-800 dark:text-blue-300">
                    <p class="font-semibold mb-1">💡 راهنمای استفاده:</p>
                    <ul class="list-disc list-inside space-y-1 mr-4">
                        <li>برای ویرایش هر نقش، روی دکمه "ویرایش" کلیک کنید</li>
                        <li>می‌توانید به سرعت وضعیت فعال/غیرفعال را تغییر دهید</li>
                        <li>تغییرات بلافاصله اعمال می‌شوند و کش خودکار پاک می‌شود</li>
                        <li>برای بازگشت به تنظیمات پیش‌فرض از گزینه "بازگشت به پیش‌فرض" استفاده کنید</li>
                    </ul>
                </div>
            </div>
        </div>

        {{-- Roles Table --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                نقش کاربری
                            </th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                قیمت خرده
                            </th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                قیمت عمده
                            </th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                اطلاعات تامین‌کننده
                            </th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                فیلتر قیمت
                            </th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                ثبت قیمت خرده
                            </th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                وضعیت
                            </th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                عملیات
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($allRoles as $role)
                            @php
                                $setting = $settings[$role] ?? [];
                                $isActive = $setting['is_active'] ?? true;
                            @endphp
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors {{ !$isActive ? 'opacity-60' : '' }}">
                                {{-- Role Name --}}
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center gap-3">
                                        <span class="px-3 py-1 text-xs font-semibold rounded-full
                                            {{ $this->getRoleColor($role) === 'red' ? 'bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-400' : '' }}
                                            {{ $this->getRoleColor($role) === 'blue' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-400' : '' }}
                                            {{ $this->getRoleColor($role) === 'slate' ? 'bg-slate-100 text-slate-800 dark:bg-slate-900/20 dark:text-slate-400' : '' }}
                                            {{ $this->getRoleColor($role) === 'amber' ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/20 dark:text-amber-400' : '' }}
                                            {{ $this->getRoleColor($role) === 'green' ? 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400' : '' }}
                                            {{ $this->getRoleColor($role) === 'gray' ? 'bg-gray-100 text-gray-800 dark:bg-gray-900/20 dark:text-gray-400' : '' }}">
                                            {{ $this->getRoleLabel($role) }}
                                        </span>
                                        @if($setting['description'] ?? null)
                                            <span class="text-xs text-gray-500 dark:text-gray-400">{{ $setting['description'] }}</span>
                                        @endif
                                    </div>
                                </td>

                                {{-- Permissions --}}
                                <td class="px-6 py-4 text-center">
                                    @if($setting['can_see_retail_price'] ?? false)
                                        <x-heroicon-s-check-circle class="w-6 h-6 text-green-500 mx-auto" />
                                    @else
                                        <x-heroicon-s-x-circle class="w-6 h-6 text-red-400 mx-auto" />
                                    @endif
                                </td>

                                <td class="px-6 py-4 text-center">
                                    @if($setting['can_see_wholesale_price'] ?? false)
                                        <x-heroicon-s-check-circle class="w-6 h-6 text-green-500 mx-auto" />
                                    @else
                                        <x-heroicon-s-x-circle class="w-6 h-6 text-red-400 mx-auto" />
                                    @endif
                                </td>

                                <td class="px-6 py-4 text-center">
                                    @if($setting['can_see_supplier_info'] ?? false)
                                        <x-heroicon-s-check-circle class="w-6 h-6 text-green-500 mx-auto" />
                                    @else
                                        <x-heroicon-s-x-circle class="w-6 h-6 text-red-400 mx-auto" />
                                    @endif
                                </td>

                                <td class="px-6 py-4 text-center">
                                    @if($setting['can_filter_by_price'] ?? false)
                                        <x-heroicon-s-check-circle class="w-6 h-6 text-green-500 mx-auto" />
                                    @else
                                        <x-heroicon-s-x-circle class="w-6 h-6 text-red-400 mx-auto" />
                                    @endif
                                </td>

                                <td class="px-6 py-4 text-center">
                                    @if($setting['can_set_retail_price'] ?? false)
                                        <x-heroicon-s-check-circle class="w-6 h-6 text-green-500 mx-auto" />
                                    @else
                                        <x-heroicon-s-x-circle class="w-6 h-6 text-red-400 mx-auto" />
                                    @endif
                                </td>

                                {{-- Status Toggle --}}
                                <td class="px-6 py-4 text-center">
                                    <button wire:click="toggleActive('{{ $role }}')" 
                                            class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2
                                                {{ $isActive ? 'bg-indigo-600' : 'bg-gray-200 dark:bg-gray-700' }}">
                                        <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform
                                            {{ $isActive ? 'translate-x-6 rtl:-translate-x-6' : 'translate-x-1 rtl:-translate-x-1' }}"></span>
                                    </button>
                                </td>

                                {{-- Actions --}}
                                <td class="px-6 py-4 text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        <button wire:click="edit('{{ $role }}')" 
                                                class="inline-flex items-center gap-1 px-3 py-1.5 bg-indigo-100 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-400 rounded-lg hover:bg-indigo-200 dark:hover:bg-indigo-900/40 transition-colors text-sm font-medium">
                                            <x-heroicon-o-pencil class="w-4 h-4" />
                                            ویرایش
                                        </button>
                                        
                                        @if(isset($settings[$role]['id']))
                                        <button wire:click="resetToDefault('{{ $role }}')" 
                                                wire:confirm="آیا مطمئن هستید؟ تنظیمات به حالت پیش‌فرض بازمی‌گردد."
                                                class="inline-flex items-center gap-1 px-3 py-1.5 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors text-sm font-medium">
                                            <x-heroicon-o-arrow-path class="w-4 h-4" />
                                            پیش‌فرض
                                        </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Legend --}}
        <div class="mt-6 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">راهنمای آیکون‌ها:</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                <div class="flex items-center gap-2">
                    <x-heroicon-s-check-circle class="w-5 h-5 text-green-500" />
                    <span class="text-gray-600 dark:text-gray-400">دسترسی دارد</span>
                </div>
                <div class="flex items-center gap-2">
                    <x-heroicon-s-x-circle class="w-5 h-5 text-red-400" />
                    <span class="text-gray-600 dark:text-gray-400">دسترسی ندارد</span>
                </div>
            </div>
        </div>

    </div>

    {{-- Edit Modal --}}
    @if($showEditModal)
    <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            
            {{-- Background overlay --}}
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 dark:bg-gray-900 dark:bg-opacity-80 transition-opacity" 
                 wire:click="closeModal"></div>

            {{-- Modal panel --}}
            <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-2xl text-right overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                
                {{-- Header --}}
                <div class="bg-gradient-to-l from-indigo-500 to-purple-600 px-6 py-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-xl font-bold text-white flex items-center gap-2">
                            <x-heroicon-o-pencil class="w-6 h-6" />
                            ویرایش تنظیمات: {{ $this->getRoleLabel($editingRole) }}
                        </h3>
                        <button wire:click="closeModal" class="text-white hover:text-gray-200 transition-colors">
                            <x-heroicon-o-x-mark class="w-6 h-6" />
                        </button>
                    </div>
                </div>

                {{-- Body --}}
                <div class="px-6 py-6 space-y-6">
                    
                    {{-- Permissions Grid --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        
                        {{-- Can See Retail Price --}}
                        <label class="flex items-start gap-3 p-4 border-2 rounded-xl cursor-pointer transition-all
                            {{ $editForm['can_see_retail_price'] ?? false ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20' : 'border-gray-200 dark:border-gray-700 hover:border-gray-300' }}">
                            <input type="checkbox" wire:model="editForm.can_see_retail_price" class="mt-1 w-5 h-5 text-indigo-600 rounded focus:ring-indigo-500">
                            <div>
                                <div class="font-semibold text-gray-900 dark:text-gray-100">قیمت خرده‌فروشی</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">نمایش قیمت خرده‌فروشی</div>
                            </div>
                        </label>

                        {{-- Can See Wholesale Price --}}
                        <label class="flex items-start gap-3 p-4 border-2 rounded-xl cursor-pointer transition-all
                            {{ $editForm['can_see_wholesale_price'] ?? false ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20' : 'border-gray-200 dark:border-gray-700 hover:border-gray-300' }}">
                            <input type="checkbox" wire:model="editForm.can_see_wholesale_price" class="mt-1 w-5 h-5 text-indigo-600 rounded focus:ring-indigo-500">
                            <div>
                                <div class="font-semibold text-gray-900 dark:text-gray-100">قیمت عمده</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">نمایش قیمت عمده</div>
                            </div>
                        </label>

                        {{-- Can See Supplier Info --}}
                        <label class="flex items-start gap-3 p-4 border-2 rounded-xl cursor-pointer transition-all
                            {{ $editForm['can_see_supplier_info'] ?? false ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20' : 'border-gray-200 dark:border-gray-700 hover:border-gray-300' }}">
                            <input type="checkbox" wire:model="editForm.can_see_supplier_info" class="mt-1 w-5 h-5 text-indigo-600 rounded focus:ring-indigo-500">
                            <div>
                                <div class="font-semibold text-gray-900 dark:text-gray-100">اطلاعات تامین‌کننده</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">نمایش اطلاعات تامین‌کننده</div>
                            </div>
                        </label>

                        {{-- Can Filter By Price --}}
                        <label class="flex items-start gap-3 p-4 border-2 rounded-xl cursor-pointer transition-all
                            {{ $editForm['can_filter_by_price'] ?? false ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20' : 'border-gray-200 dark:border-gray-700 hover:border-gray-300' }}">
                            <input type="checkbox" wire:model="editForm.can_filter_by_price" class="mt-1 w-5 h-5 text-indigo-600 rounded focus:ring-indigo-500">
                            <div>
                                <div class="font-semibold text-gray-900 dark:text-gray-100">فیلتر قیمت</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">استفاده از فیلتر قیمتی</div>
                            </div>
                        </label>

                        {{-- Can Set Retail Price --}}
                        <label class="flex items-start gap-3 p-4 border-2 rounded-xl cursor-pointer transition-all
                            {{ $editForm['can_set_retail_price'] ?? false ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20' : 'border-gray-200 dark:border-gray-700 hover:border-gray-300' }}">
                            <input type="checkbox" wire:model="editForm.can_set_retail_price" class="mt-1 w-5 h-5 text-indigo-600 rounded focus:ring-indigo-500">
                            <div>
                                <div class="font-semibold text-gray-900 dark:text-gray-100">ثبت قیمت خرده</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">ثبت قیمت خرده‌فروشی</div>
                            </div>
                        </label>

                        {{-- Is Active --}}
                        <label class="flex items-start gap-3 p-4 border-2 rounded-xl cursor-pointer transition-all
                            {{ $editForm['is_active'] ?? false ? 'border-green-500 bg-green-50 dark:bg-green-900/20' : 'border-gray-200 dark:border-gray-700 hover:border-gray-300' }}">
                            <input type="checkbox" wire:model="editForm.is_active" class="mt-1 w-5 h-5 text-green-600 rounded focus:ring-green-500">
                            <div>
                                <div class="font-semibold text-gray-900 dark:text-gray-100">فعال</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">فعال/غیرفعال بودن تنظیمات</div>
                            </div>
                        </label>
                    </div>

                    {{-- Description --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            توضیحات (اختیاری)
                        </label>
                        <textarea wire:model="editForm.description" 
                                  rows="3" 
                                  class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent dark:bg-gray-700 dark:text-gray-100"
                                  placeholder="توضیحاتی درباره این نقش و دسترسی‌هایش بنویسید..."></textarea>
                        @error('editForm.description')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                </div>

                {{-- Footer --}}
                <div class="bg-gray-50 dark:bg-gray-900 px-6 py-4 flex items-center justify-end gap-3">
                    <button wire:click="closeModal" 
                            type="button"
                            class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                        انصراف
                    </button>
                    <button wire:click="save" 
                            type="button"
                            class="px-6 py-2 bg-gradient-to-l from-indigo-500 to-purple-600 text-white rounded-lg hover:from-indigo-600 hover:to-purple-700 transition-all shadow-lg hover:shadow-xl">
                        ذخیره تغییرات
                    </button>
                </div>

            </div>
        </div>
    </div>
    @endif

</div>
