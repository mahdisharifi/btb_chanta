{{-- resources/views/livewire/web/advanced-search-filters.blade.php --}}
<div>
    <!-- جستجوی متنی -->
    <div class="mb-6">
        <label for="searchQuery" class="block text-sm font-medium text-gray-700 mb-2">
            جستجوی محصول
        </label>
        <div class="relative">
            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                <x-heroicon-o-magnifying-glass class="h-5 w-5 text-gray-400" />
            </div>
            <input
                type="text"
                id="searchQuery"
                wire:model.live.debounce.500ms="query"
                placeholder="نام محصول، کد SKU یا توضیحات..."
                class="w-full pr-10 border border-gray-300 rounded-md px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            >
        </div>
    </div>

    <!-- فیلتر دسته‌بندی -->
    <div class="mb-6">
        <label for="categoryFilter" class="block text-sm font-medium text-gray-700 mb-2">
            دسته‌بندی
        </label>
        <select
            id="categoryFilter"
            wire:model.live="categoryId"
            class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
        >
            <option value="">همه دسته‌بندی‌ها</option>
            @foreach($categories as $category)
            <option value="{{ $category->id }}">{{ $category->name }}</option>
            @endforeach
        </select>
    </div>

    <!-- فیلتر ویژگی‌ها -->
    @if(count($filterAttributes) > 0)
    <div class="mb-6">
        <h3 class="text-sm font-medium text-gray-700 mb-3">ویژگی‌ها</h3>
        <div class="space-y-4">
            @foreach($filterAttributes as $attribute)
            <div>
                <h4 class="text-sm font-medium text-gray-600 mb-2">{{ $attribute->name }}</h4>
                <div class="space-y-2">
                    @if($attribute->type === 'select' || $attribute->type === 'multiselect')
                    @foreach($attribute->values as $value)
                    <div class="flex items-center">
                        <input
                            type="{{ $attribute->type === 'multiselect' ? 'checkbox' : 'radio' }}"
                            id="attr_{{ $attribute->id }}_{{ $value->value }}"
                            wire:model.live="{{ $attribute->type === 'multiselect' ? 'selectedAttributes.' . $attribute->id : 'selectedAttributes.' . $attribute->id }}"
                            value="{{ $value->value }}"
                            class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                        >
                        <label
                            for="attr_{{ $attribute->id }}_{{ $value->value }}"
                            class="mr-2 text-sm text-gray-700"
                        >
                            {{ $value->value }}
                        </label>
                    </div>
                    @endforeach
                    @elseif($attribute->type === 'range')
                    <div class="flex items-center space-x-4 space-x-reverse">
                        <input
                            type="number"
                            wire:model.live.debounce.500ms="selectedAttributes.{{ $attribute->id }}.min"
                            placeholder="حداقل"
                            class="w-full border border-gray-300 rounded-md px-3 py-1 text-sm"
                        >
                        <span class="text-gray-500">تا</span>
                        <input
                            type="number"
                            wire:model.live.debounce.500ms="selectedAttributes.{{ $attribute->id }}.max"
                            placeholder="حداکثر"
                            class="w-full border border-gray-300 rounded-md px-3 py-1 text-sm"
                        >
                    </div>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    <!-- فیلتر تامین‌کننده -->
    @if(count($suppliers) > 0 && auth()->check() && in_array(auth()->user()->role, ['retailer', 'supplier', 'super_admin']))
    <div class="mb-6">
        <label for="supplierFilter" class="block text-sm font-medium text-gray-700 mb-2">
            تامین‌کننده
        </label>
        <select
            id="supplierFilter"
            wire:model.live="supplierId"
            class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
        >
            <option value="">همه تامین‌کنندگان</option>
            @foreach($suppliers as $supplier)
            <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
            @endforeach
        </select>
    </div>
    @endif

    <!-- فیلتر نوع قیمت -->
    <div class="mb-6">
        <label for="priceTypeFilter" class="block text-sm font-medium text-gray-700 mb-2">
            نوع قیمت
        </label>
        <select
            id="priceTypeFilter"
            wire:model.live="priceType"
            class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
        >
            <option value="">همه انواع قیمت</option>
            @foreach($priceTypes as $type)
            <option value="{{ $type->value }}">{{ $type->label() }}</option>
            @endforeach
        </select>
    </div>

    <!-- فیلتر محدوده قیمت -->
    <div class="mb-6">
        <h3 class="text-sm font-medium text-gray-700 mb-3">محدوده قیمت</h3>
        <div class="space-y-3">
            <div class="flex items-center space-x-4 space-x-reverse">
                <input
                    type="number"
                    wire:model.live.debounce.500ms="minPrice"
                    placeholder="حداقل قیمت"
                    class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm"
                >
                <span class="text-gray-500">تا</span>
                <input
                    type="number"
                    wire:model.live.debounce.500ms="maxPrice"
                    placeholder="حداکثر قیمت"
                    class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm"
                >
            </div>
            @if(isset($priceRange['min']) && isset($priceRange['max']))
            <div class="text-xs text-gray-500">
                بازه قیمت: {{ number_format($priceRange['min']) }} تا {{ number_format($priceRange['max']) }} تومان
            </div>
            @endif
        </div>
    </div>

    <!-- دکمه‌های اقدام -->
    <div class="space-y-3">
        <button
            wire:click="clearFilters"
            class="w-full flex justify-center items-center px-4 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2"
        >
            <x-heroicon-o-x-mark class="w-5 h-5 ml-2" />
            حذف فیلترها
        </button>

        <button
            onclick="window.scrollTo({top: 0, behavior: 'smooth'})"
            class="w-full flex justify-center items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
        >
            <x-heroicon-o-arrow-up class="w-5 h-5 ml-2" />
            مشاهده نتایج
        </button>
    </div>
</div>
