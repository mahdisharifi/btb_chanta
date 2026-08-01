<div>
    <x-admin.subheader title="ویرایش ویژگی: {{ $attribute->name }}">
        <x-slot name="actions">
            <x-admin.buttons.secondary wire:navigate href="{{ route('admin.attributes.index') }}">
                بازگشت
            </x-admin.buttons.secondary>
        </x-slot>
    </x-admin.subheader>

    <div class="p-6 bg-white rounded-lg shadow-sm">
        <form wire:submit="update" class="space-y-6 max-w-3xl">

            <!-- Name Field with Live Search -->
            <div class="relative">
                <x-input-label for="name" value="نام ویژگی" required />
                <x-text-input
                    id="name"
                    wire:model.live.debounce.300ms="name"
                    type="text"
                    class="block w-full mt-1"
                    placeholder="مثال: حافظه داخلی"
                />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />

                <!-- باکس نمایش ویژگی‌های مشابه -->
                @if(!empty($this->similarAttributes) && count($this->similarAttributes) > 0)
                <div class="mt-2 p-3 bg-amber-50 border border-amber-200 rounded-md shadow-sm">
                    <div class="flex items-center text-amber-800 mb-2">
                        <x-heroicon-o-exclamation-triangle class="w-4 h-4 ml-1" />
                        <span class="text-sm font-semibold">ویژگی‌های مشابه یافت شد (جهت جلوگیری از ثبت تکراری):</span>
                    </div>
                    <ul class="text-sm text-amber-700 list-disc list-inside">
                        @foreach($this->similarAttributes as $similar)
                        <li>
                            {{ $similar->name }}
                            <span class="text-xs text-amber-500">
                                        (نوع: {{ $similar->type instanceof \App\Enums\AttributeType ? $similar->type->label() : $similar->type }})
                                    </span>
                        </li>
                        @endforeach
                    </ul>
                </div>
                @endif
            </div>

            <!-- Type Field -->
            <!-- Type Field -->
            <div>
                <x-input-label for="type" value="نوع فیلد ورودی" required />

                <select
                    id="type"
                    wire:model="type"
                    class="block w-full mt-1 border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
                    <option value="">انتخاب کنید...</option>
                    @foreach($this->typesList as $typeEnum)
                    <option value="{{ $typeEnum->value }}">{{ $typeEnum->label() }}</option>
                    @endforeach
                </select>

                <x-input-error :messages="$errors->get('type')" class="mt-2" />
            </div>


            <!-- Unit Field -->
            <div>
                <x-input-label for="unit" value="واحد اندازه‌گیری (اختیاری)" />
                <x-text-input
                    id="unit"
                    wire:model="unit"
                    type="text"
                    class="block w-full mt-1"
                    placeholder="مثال: گیگابایت، اینچ، گرم"
                    dir="rtl"
                />
                <p class="text-xs text-gray-500 mt-1">اگر این ویژگی دارای واحد مشخصی است (مانند کیلوگرم)، آن را وارد کنید.</p>
                <x-input-error :messages="$errors->get('unit')" class="mt-2" />
            </div>

            <!-- Is Filterable Checkbox -->
            <div class="flex items-center mt-4">
                <x-checkbox
                    id="is_filterable"
                    wire:model="is_filterable"
                />
                <label for="is_filterable" class="ml-2 mr-2 text-sm text-gray-600 cursor-pointer">
                    آیا این ویژگی در سایدبار فیلترهای فروشگاه نمایش داده شود؟
                </label>
            </div>

            <!-- Submit Button -->
            <div class="flex items-center gap-4 pt-4 border-t">
                <x-admin.buttons.primary type="submit" wire:loading.attr="disabled">
                    ذخیره تغییرات
                </x-admin.buttons.primary>

                <span wire:loading wire:target="update" class="text-sm text-gray-500">
                    در حال ذخیره...
                </span>
            </div>
        </form>
    </div>
</div>
