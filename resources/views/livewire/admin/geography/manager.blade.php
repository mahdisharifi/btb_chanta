<div class="space-y-6" dir="rtl">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
                <x-heroicon-o-map class="w-7 h-7 text-indigo-600" />
                مدیریت مناطق جغرافیایی
            </h1>
            <p class="mt-1 text-sm text-gray-500">مدیریت استان‌ها و شهرهای زیرمجموعه</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        {{-- ============ Master: Provinces ============ --}}
        <div class="lg:col-span-4 bg-white rounded-2xl shadow-sm border border-gray-100 flex flex-col">
            <div class="p-4 border-b border-gray-100 space-y-3">
                <div class="flex items-center justify-between">
                    <h2 class="font-semibold text-gray-700 flex items-center gap-2">
                        <x-heroicon-o-building-library class="w-5 h-5 text-indigo-500" />
                        استان‌ها
                    </h2>
                    <button type="button" wire:click="createProvince"
                            class="inline-flex items-center gap-1 rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700 transition">
                        <x-heroicon-o-plus class="w-4 h-4" />
                        افزودن
                    </button>
                </div>
                <div class="relative">
                    <x-heroicon-o-magnifying-glass class="w-5 h-5 text-gray-400 absolute right-3 top-1/2 -translate-y-1/2" />
                    <input type="text" wire:model.live.debounce.300ms="provinceSearch"
                           placeholder="جستجوی استان..."
                           class="w-full rounded-lg border-gray-200 pr-10 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                </div>
            </div>

            <ul class="divide-y divide-gray-100 max-h-[60vh] overflow-y-auto">
                @forelse ($provinces as $province)
                <li wire:key="province-{{ $province->id }}"
                    class="flex items-center justify-between px-4 py-3 cursor-pointer transition
                            {{ $selectedProvinceId === $province->id ? 'bg-indigo-50 border-r-4 border-indigo-600' : 'hover:bg-gray-50' }}">
                    <button type="button" wire:click="selectProvince({{ $province->id }})"
                            class="flex-1 text-right">
                        <div class="flex items-center gap-2">
                            <span class="font-medium text-gray-800">{{ $province->name }}</span>
                            @unless($province->is_active)
                            <span class="text-xs text-red-500">(غیرفعال)</span>
                            @endunless
                        </div>
                        <span class="text-xs text-gray-400">{{ $province->cities_count }} شهر</span>
                    </button>
                    <div class="flex items-center gap-1">
                        <button type="button" wire:click="editProvince({{ $province->id }})"
                                class="p-1.5 text-gray-400 hover:text-indigo-600 rounded-md hover:bg-gray-100" title="ویرایش">
                            <x-heroicon-o-pencil-square class="w-4 h-4" />
                        </button>
                        <button type="button" wire:click="deleteProvince({{ $province->id }})"
                                wire:confirm="حذف استان «{{ $province->name }}»؟ شهرهای آن باقی می‌مانند."
                                class="p-1.5 text-gray-400 hover:text-red-600 rounded-md hover:bg-gray-100" title="حذف">
                            <x-heroicon-o-trash class="w-4 h-4" />
                        </button>
                    </div>
                </li>
                @empty
                <li class="px-4 py-10 text-center text-sm text-gray-400">استانی یافت نشد.</li>
                @endforelse
            </ul>
        </div>

        {{-- ============ Detail: Cities ============ --}}
        <div class="lg:col-span-8 bg-white rounded-2xl shadow-sm border border-gray-100 flex flex-col">
            @if ($selectedProvinceId && $cities)
            <div class="p-4 border-b border-gray-100 space-y-3">
                <div class="flex items-center justify-between">
                    <h2 class="font-semibold text-gray-700 flex items-center gap-2">
                        <x-heroicon-o-map-pin class="w-5 h-5 text-indigo-500" />
                        شهرهای استان
                    </h2>
                    <button type="button" wire:click="createCity"
                            class="inline-flex items-center gap-1 rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700 transition">
                        <x-heroicon-o-plus class="w-4 h-4" />
                        افزودن شهر
                    </button>
                </div>
                <div class="relative">
                    <x-heroicon-o-magnifying-glass class="w-5 h-5 text-gray-400 absolute right-3 top-1/2 -translate-y-1/2" />
                    <input type="text" wire:model.live.debounce.300ms="citySearch"
                           placeholder="جستجوی شهر..."
                           class="w-full rounded-lg border-gray-200 pr-10 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 text-sm">
                    <thead class="bg-gray-50 text-gray-500">
                    <tr>
                        <th class="px-4 py-3 text-right font-medium">نام شهر</th>
                        <th class="px-4 py-3 text-right font-medium">الگوی کد پستی</th>
                        <th class="px-4 py-3 text-right font-medium">جمعیت</th>
                        <th class="px-4 py-3 text-center font-medium">وضعیت</th>
                        <th class="px-4 py-3 text-center font-medium">عملیات</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                    @forelse ($cities as $city)
                    <tr wire:key="city-{{ $city->id }}" class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-800">{{ $city->name }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $city->postal_code_pattern ?: '—' }}</td>
                        <td class="px-4 py-3 text-gray-500">
                            {{ $city->population ? number_format($city->population) : '—' }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if ($city->is_active)
                            <span class="inline-flex items-center gap-1 rounded-full bg-green-50 px-2 py-0.5 text-xs text-green-700">
                                                <x-heroicon-s-check-circle class="w-3.5 h-3.5" /> فعال
                                            </span>
                            @else
                            <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-500">
                                                <x-heroicon-s-x-circle class="w-3.5 h-3.5" /> غیرفعال
                                            </span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-center gap-1">
                                <button type="button" wire:click="editCity({{ $city->id }})"
                                        class="p-1.5 text-gray-400 hover:text-indigo-600 rounded-md hover:bg-gray-100" title="ویرایش">
                                    <x-heroicon-o-pencil-square class="w-4 h-4" />
                                </button>
                                <button type="button" wire:click="deleteCity({{ $city->id }})"
                                        wire:confirm="حذف شهر «{{ $city->name }}»؟"
                                        class="p-1.5 text-gray-400 hover:text-red-600 rounded-md hover:bg-gray-100" title="حذف">
                                    <x-heroicon-o-trash class="w-4 h-4" />
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-4 py-10 text-center text-sm text-gray-400">
                            شهری برای این استان ثبت نشده است.
                        </td>
                    </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <div class="p-4 border-t border-gray-100">
                {{ $cities->links() }}
            </div>
            @else
            <div class="flex flex-col items-center justify-center py-24 text-center text-gray-400">
                <x-heroicon-o-cursor-arrow-rays class="w-12 h-12 mb-3" />
                <p class="text-sm">برای مشاهده و مدیریت شهرها، یک استان را از فهرست انتخاب کنید.</p>
            </div>
            @endif
        </div>
    </div>

    {{-- ============ Province Modal ============ --}}
    @if ($showProvinceModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" wire:key="province-modal">
        <div class="w-full max-w-lg rounded-2xl bg-white shadow-xl">
            <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                <h3 class="font-semibold text-gray-800">
                    {{ $editingProvinceId ? 'ویرایش استان' : 'افزودن استان' }}
                </h3>
                <button type="button" wire:click="$set('showProvinceModal', false)"
                        class="p-1 text-gray-400 hover:text-gray-600">
                    <x-heroicon-o-x-mark class="w-5 h-5" />
                </button>
            </div>

            <form wire:submit="saveProvince" class="px-6 py-5 space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">کشور</label>
                        <select wire:model="provinceForm.country_id"
                                class="w-full rounded-lg border-gray-200 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">انتخاب کنید...</option>
                            @foreach ($countries as $country)
                            <option value="{{ $country->id }}">{{ $country->name }}</option>
                            @endforeach
                        </select>
                        @error('provinceForm.country_id') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">نام استان</label>
                        <input type="text" wire:model="provinceForm.name"
                               class="w-full rounded-lg border-gray-200 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        @error('provinceForm.name') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">نام بومی</label>
                        <input type="text" wire:model="provinceForm.native_name"
                               class="w-full rounded-lg border-gray-200 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        @error('provinceForm.native_name') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">کد ISO</label>
                        <input type="text" wire:model="provinceForm.iso_code"
                               class="w-full rounded-lg border-gray-200 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        @error('provinceForm.iso_code') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">مرکز استان</label>
                        <input type="text" wire:model="provinceForm.capital"
                               class="w-full rounded-lg border-gray-200 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        @error('provinceForm.capital') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">عرض جغرافیایی</label>
                        <input type="number" step="any" wire:model="provinceForm.latitude"
                               class="w-full rounded-lg border-gray-200 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        @error('provinceForm.latitude') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">طول جغرافیایی</label>
                        <input type="number" step="any" wire:model="provinceForm.longitude"
                               class="w-full rounded-lg border-gray-200 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        @error('provinceForm.longitude') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">ترتیب نمایش</label>
                        <input type="number" wire:model="provinceForm.sort_order"
                               class="w-full rounded-lg border-gray-200 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        @error('provinceForm.sort_order') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex items-center gap-2 pt-6">
                        <input type="checkbox" id="province_active" wire:model="provinceForm.is_active"
                               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                        <label for="province_active" class="text-sm text-gray-700">فعال</label>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-gray-100 pt-4">
                    <button type="button" wire:click="$set('showProvinceModal', false)"
                            class="rounded-lg border border-gray-200 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">
                        انصراف
                    </button>
                    <button type="submit"
                            class="inline-flex items-center gap-1 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                        <x-heroicon-o-check class="w-4 h-4" />
                        ذخیره
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif

    {{-- ============ City Modal ============ --}}
    @if ($showCityModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" wire:key="city-modal">
        <div class="w-full max-w-lg rounded-2xl bg-white shadow-xl">
            <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                <h3 class="font-semibold text-gray-800">
                    {{ $editingCityId ? 'ویرایش شهر' : 'افزودن شهر' }}
                </h3>
                <button type="button" wire:click="$set('showCityModal', false)"
                        class="p-1 text-gray-400 hover:text-gray-600">
                    <x-heroicon-o-x-mark class="w-5 h-5" />
                </button>
            </div>

            <form wire:submit="saveCity" class="px-6 py-5 space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">نام شهر</label>
                        <input type="text" wire:model="cityForm.name"
                               class="w-full rounded-lg border-gray-200 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        @error('cityForm.name') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">نام بومی</label>
                        <input type="text" wire:model="cityForm.native_name"
                               class="w-full rounded-lg border-gray-200 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        @error('cityForm.native_name') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">الگوی کد پستی</label>
                        <input type="text" wire:model="cityForm.postal_code_pattern"
                               class="w-full rounded-lg border-gray-200 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        @error('cityForm.postal_code_pattern') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">جمعیت</label>
                        <input type="number" wire:model="cityForm.population"
                               class="w-full rounded-lg border-gray-200 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        @error('cityForm.population') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">عرض جغرافیایی</label>
                        <input type="number" step="any" wire:model="cityForm.latitude"
                               class="w-full rounded-lg border-gray-200 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        @error('cityForm.latitude') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">طول جغرافیایی</label>
                        <input type="number" step="any" wire:model="cityForm.longitude"
                               class="w-full rounded-lg border-gray-200 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        @error('cityForm.longitude') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">ترتیب نمایش</label>
                        <input type="number" wire:model="cityForm.sort_order"
                               class="w-full rounded-lg border-gray-200 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        @error('cityForm.sort_order') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex items-center gap-2 pt-6">
                        <input type="checkbox" id="city_active" wire:model="cityForm.is_active"
                               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                        <label for="city_active" class="text-sm text-gray-700">فعال</label>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-gray-100 pt-4">
                    <button type="button" wire:click="$set('showCityModal', false)"
                            class="rounded-lg border border-gray-200 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">
                        انصراف
                    </button>
                    <button type="submit"
                            class="inline-flex items-center gap-1 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                        <x-heroicon-o-check class="w-4 h-4" />
                        ذخیره
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif
</div>
