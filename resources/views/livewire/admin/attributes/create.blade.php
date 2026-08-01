<div>
    <x-admin.subheader :title="$isEditMode ? 'ویرایش ویژگی: ' . $attribute->name : 'ایجاد ویژگی جدید'" :breadcrumbs="[
        ['title' => 'داشبورد', 'url' => route('admin.dashboard')],
        ['title' => 'ویژگی‌ها', 'url' => route('admin.attributes.index')],
        ['title' => $isEditMode ? 'ویرایش ویژگی' : 'ایجاد ویژگی', 'url' => '']
    ]" />

    <div class="p-4 sm:p-6">
        <x-admin.card>
            <form wire:submit.prevent="save" class="space-y-6">
                <div class="mb-4">
                    <x-input-label for="name" value="نام ویژگی *" />
                    <x-text-input
                        wire:model.live.debounce.500ms="name"
                        id="name"
                        class="block mt-1 w-full"
                        type="text"
                        required
                        autofocus
                    />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />

                    @if(count($this->similarAttributes) > 0)
                    <div class="mt-2 bg-yellow-50 dark:bg-yellow-900/30 border border-yellow-200 dark:border-yellow-700 rounded-md p-3">
                        <div class="flex items-center text-yellow-800 dark:text-yellow-300 text-sm font-medium mb-2">
                            <x-heroicon-o-exclamation-triangle class="w-4 h-4 ml-1" />
                            ویژگی‌های مشابهی از قبل وجود دارند (می‌توانید نادیده بگیرید):
                        </div>

                        <ul class="space-y-1">
                            @foreach($this->similarAttributes as $similar)
                            <li
                                wire:key="similar-{{ $similar->id }}"
                                class="flex justify-between items-center text-sm text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 p-2 rounded border border-gray-100 dark:border-gray-700"
                            >
                                <span>{{ $similar->name }}</span>
                                <span class="text-xs px-2 py-1 bg-gray-100 dark:bg-gray-700 rounded text-gray-500 dark:text-gray-400">
                                            {{ $similar->type instanceof \App\Enums\AttributeType ? $similar->type->label() : $similar->type }}
                                        </span>
                            </li>
                            @endforeach
                        </ul>
                    </div>
                    @endif
                </div>

                <div>
                    <x-input-label for="type" value="نوع ویژگی *" />
                    <x-select
                        id="type"
                        wire:model.live="type"
                        class="block w-full mt-1"
                        :options="$typesList"
                        required
                    />
                    <x-input-error :messages="$errors->get('type')" class="mt-2" />
                </div>

                <div class="block">
                    <label for="is_multiple" class="inline-flex items-start cursor-pointer">
                        <x-checkbox id="is_multiple" wire:model="is_multiple" class="mt-1" />
                        <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">
                            این ویژگی می‌تواند چند مقدار داشته باشد
                        </span>
                    </label>
                    <x-input-error :messages="$errors->get('is_multiple')" class="mt-2" />
                </div>

                @if($type !== 'boolean')
                <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4 bg-gray-50/70 dark:bg-gray-800/40">
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">مقادیر از پیش‌تعریف‌شده (اختیاری)</h3>
                        <button
                            type="button"
                            wire:click="addOption"
                            class="inline-flex items-center rounded-lg border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-sm font-medium text-amber-700 hover:bg-amber-500/20 dark:text-amber-300"
                        >
                            افزودن مقدار
                        </button>
                    </div>

                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
                        این مقادیر به‌عنوان پیشنهاد در ثبت محصول و گزینه‌های فیلتر استفاده می‌شوند. اگر تعداد مقادیر زیاد باشد، لیست به‌صورت اسکرولی نمایش داده می‌شود.
                    </p>

                    <x-input-error :messages="$errors->get('options')" class="mb-3" />

                    <div class="max-h-80 overflow-y-auto pe-1">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            @foreach($options as $index => $option)
                            <div
                                wire:key="option-{{ $option['id'] ?? 'new' }}-{{ $index }}"
                                class="rounded-lg border {{ !empty($option['is_confirmed']) ? 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900/40' : 'border-amber-300 bg-amber-50/70 dark:bg-amber-900/10' }} p-3"
                            >
                                @if(!empty($option['is_confirmed']))
                                <div class="flex items-center justify-between gap-2">
                                    <div class="min-w-0">
                                        <div class="text-sm font-medium text-gray-800 dark:text-gray-200 ">
                                            {{ $option['value'] }}
                                        </div>

                                        @if(!empty($option['in_use']))
                                        <div class="mt-1 text-xs text-amber-600 dark:text-amber-300">
                                            این مقدار به محصول تخصیص داده شده و قابل حذف نیست.
                                        </div>
                                        @endif
                                    </div>

                                    <div class="flex items-center gap-2 shrink-0">
                                        @if(empty($option['in_use']))
                                        <button
                                            type="button"
                                            wire:click="editOption({{ $index }})"
                                            class="inline-flex items-center justify-center rounded-md border border-blue-200 px-2 py-1 text-xs text-blue-700 hover:bg-blue-50 dark:border-blue-700 dark:text-blue-300"
                                        >
                                            ویرایش
                                        </button>

                                        <button
                                            type="button"
                                            wire:click="removeOption({{ $index }})"
                                            class="inline-flex items-center justify-center rounded-md border border-red-200 px-2 py-1 text-xs text-red-700 hover:bg-red-50 dark:border-red-700 dark:text-red-300"
                                        >
                                            حذف
                                        </button>
                                        @else
                                        <span class="inline-flex items-center rounded-md border border-gray-200 px-2 py-1 text-xs text-gray-500 dark:border-gray-700 dark:text-gray-400">
                                                            تایید شده
                                                        </span>
                                        @endif
                                    </div>
                                </div>
                                @else
                                <div class="flex flex-col sm:flex-row gap-2">
                                    <div class="flex-1">
                                        <x-text-input
                                            id="option-input-{{ $index }}"
                                            wire:model.defer="options.{{ $index }}.value"
                                            wire:keydown.enter.prevent="confirmOption({{ $index }})"
                                            type="text"
                                            class="block w-full"
                                            placeholder="مثال: قرمز"
                                        />
                                        <x-input-error :messages="$errors->get('options.' . $index . '.value')" class="mt-2" />
                                    </div>

                                    <div class="flex items-start gap-2 shrink-0">
                                        <button
                                            type="button"
                                            wire:click="confirmOption({{ $index }})"
                                            class="inline-flex items-center justify-center rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-sm font-medium text-green-700 hover:bg-green-100 dark:border-green-700 dark:bg-green-900/20 dark:text-green-300"
                                        >
                                            تایید
                                        </button>

                                        @if(empty($option['in_use']))
                                        <button
                                            type="button"
                                            wire:click="removeOption({{ $index }})"
                                            class="inline-flex items-center justify-center rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-100 dark:border-red-700 dark:bg-red-900/20 dark:text-red-300"
                                        >
                                            حذف
                                        </button>
                                        @endif
                                    </div>
                                </div>
                                @endif
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                @endif

                <div>
                    <x-input-label for="unit" value="واحد اندازه‌گیری (اختیاری)" />
                    <x-text-input id="unit" wire:model="unit" class="block w-full mt-1" type="text" />
                    <x-input-error :messages="$errors->get('unit')" class="mt-2" />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="is_filterable" class="inline-flex items-start cursor-pointer border border-gray-200 dark:border-gray-700 p-4 rounded-lg w-full">
                            <x-checkbox id="is_filterable" wire:model="is_filterable" class="mt-1" />
                            <span class="ms-2 text-sm text-gray-600">استفاده به عنوان فیلتر</span>
                        </label>
                        <x-input-error :messages="$errors->get('is_filterable')" class="mt-2" />
                    </div>

                    <div>
                        <label for="is_visible" class="inline-flex items-start cursor-pointer border border-gray-200 dark:border-gray-700 p-4 rounded-lg w-full">
                            <x-checkbox id="is_visible" wire:model="is_visible" class="mt-1" />
                            <span class="ms-2 text-sm text-gray-600">نمایش در صفحه محصول</span>
                        </label>
                        <x-input-error :messages="$errors->get('is_visible')" class="mt-2" />
                    </div>
                </div>

                <div class="flex items-center gap-4 mt-6">
                    <x-admin.buttons.primary type="submit" wire:loading.attr="disabled" wire:target="save">
                        {{ $isEditMode ? 'بروزرسانی ویژگی' : 'ذخیره ویژگی' }}
                    </x-admin.buttons.primary>
                    <x-admin.buttons.secondary wire:navigate href="{{ route('admin.attributes.index') }}">انصراف</x-admin.buttons.secondary>
                </div>
            </form>
        </x-admin.card>
    </div>

    <script>
        window.addEventListener('focus-option-input', function (event) {
            const input = document.getElementById('option-input-' + event.detail.index);

            if (!input) {
                return;
            }

            input.focus();
            input.classList.add('ring-2', 'ring-amber-400');

            setTimeout(() => {
                input.classList.remove('ring-2', 'ring-amber-400');
            }, 1200);
        });
    </script>
</div>
