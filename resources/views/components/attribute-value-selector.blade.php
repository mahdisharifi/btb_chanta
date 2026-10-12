@php
    $workspace = $workspace ?? false;
    $selectorOptions = $attribute->values->map(fn ($value) => ['id' => $value->id, 'value' => $value->value])->values()->all();
@endphp
<div wire:key="{{ $workspace ? 'task' : 'admin' }}-attribute-selector-{{ $attribute->id }}" wire:ignore
     class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700"
     x-data="{
         attributeId: {{ $attribute->id }},
         workspace: @js($workspace),
         multiple: @js((bool) $attribute->is_multiple),
         options: @js($selectorOptions),
         search: '',
         error: '',
         rejectedSearch: null,
         busy: false,
         warning: false,
         normalize(text) { return String(text ?? '').toLowerCase().replace(/\u064a/g, '\u06cc').replace(/\u0643/g, '\u06a9').replace(/\u200c/g, ' ').replace(/\s+/g, ' ').trim(); },
         get selection() { return this.workspace ? ($wire.draft.attributes || []).find(item => Number(item.attribute_id) === this.attributeId) : null; },
         get proposals() { return this.selection?.proposed_values || []; },
         get selectedIds() {
             const selected = this.workspace ? (this.selection?.value_ids || []) : $wire.selectedAttributeValues[this.attributeId];
             return (Array.isArray(selected) ? selected : [selected]).filter(value => value !== null && value !== undefined && value !== '').map(String);
         },
         get selectedOptions() { return this.options.filter(option => this.selectedIds.includes(String(option.id))); },
         get normalizedSearch() { return this.normalize(this.search); },
         get matchingOptions() {
             return this.options.filter(option => !this.selectedIds.includes(String(option.id)) && this.normalize(option.value).includes(this.normalizedSearch));
         },
         get existingOption() { return this.options.find(option => this.normalize(option.value) === this.normalizedSearch); },
         get existingProposal() { return this.proposals.some(value => this.normalize(value) === this.normalizedSearch); },
         get canAdd() { return this.normalizedSearch.length > 0 && !this.existingOption && !this.existingProposal && this.normalizedSearch !== this.rejectedSearch; },
         async choose(option) {
             if (this.busy) return;
             this.busy = true;
             const replacing = !this.multiple && (this.selectedIds.length > 0 || this.proposals.length > 0);
             try {
                 if (this.workspace) await $wire.toggleAttribute(this.attributeId, option.id, this.multiple);
                 else await $wire.toggleAttributeValue(this.attributeId, option.id);
                 this.error = '';
                 this.warning = replacing;
             } catch (failure) { this.error = 'ثبت انتخاب انجام نشد؛ دوباره تلاش کنید.'; }
             finally { this.busy = false; }
         },
         async remove(option) {
             if (this.busy) return;
             this.busy = true;
             try { await $wire.removeAttributeValue(this.attributeId, option.id); this.error = ''; this.warning = false; }
             catch (failure) { this.error = 'حذف انتخاب انجام نشد؛ دوباره تلاش کنید.'; }
             finally { this.busy = false; }
         },
         async removeProposal(value) {
             if (this.busy) return;
             this.busy = true;
             try { await $wire.removeProposedAttributeValue(this.attributeId, value); this.error = ''; this.warning = false; }
             catch (failure) { this.error = 'حذف پیشنهاد انجام نشد؛ دوباره تلاش کنید.'; }
             finally { this.busy = false; }
         },
         async add() {
             if (this.busy || !this.canAdd) return;
             const submittedSearch = this.search;
             this.busy = true;
             this.error = '';
             try {
                 const result = this.workspace
                     ? await $wire.proposeAttributeValue(this.attributeId, this.multiple, submittedSearch)
                     : await $wire.saveNewAttributeValue(this.attributeId, submittedSearch);
                 if (result?.status === 'added') {
                     if (result.option && !this.options.some(option => String(option.id) === String(result.option.id))) this.options.push(result.option);
                     if (this.search === submittedSearch) this.search = '';
                     this.rejectedSearch = null;
                 } else {
                     if (result?.values) this.options = result.values;
                     if (result?.status === 'duplicate') this.rejectedSearch = this.normalize(submittedSearch);
                     this.error = result?.message || 'افزودن مقدار انجام نشد؛ دوباره تلاش کنید.';
                 }
             } catch (failure) { this.error = 'افزودن مقدار انجام نشد؛ دوباره تلاش کنید.'; }
             finally { this.busy = false; }
         }
     }"
     x-on:attribute-values-refreshed.window="const refreshed = $event.detail.attributes.find(item => Number(item.id) === attributeId); if (refreshed) options = refreshed.values">
    <div class="bg-gray-50 px-4 py-3 dark:bg-gray-900">
        <div class="flex items-center justify-between">
            <h4 class="font-semibold text-gray-800 dark:text-gray-200">{{ $attribute->name }}@if($workspace && $attribute->is_required)<span class="mr-1 text-xs text-red-600">(اجباری)</span>@endif</h4>
            <div class="flex items-center gap-2">
                <span class="rounded bg-blue-100 px-2 py-0.5 text-xs text-blue-800 dark:bg-blue-900 dark:text-blue-200">{{ $attribute->is_multiple ? 'چند انتخابی' : 'تک انتخابی' }}</span>
                <span x-cloak x-show="selectedIds.length + proposals.length" class="rounded bg-[#d4af37]/10 px-2 py-1 text-xs font-medium text-[#d4af37]" x-text="(selectedIds.length + proposals.length) + ' انتخاب شده'"></span>
            </div>
        </div>
    </div>
    <div class="p-4">
        <p x-cloak x-show="warning" class="mb-3 text-sm text-amber-700">انتخاب قبلی جایگزین شد.</p>
        <div x-cloak x-show="selectedOptions.length + proposals.length" class="mb-4 border-b border-gray-200 pb-4 dark:border-gray-700">
            <p class="mb-2 text-xs font-medium text-gray-500 dark:text-gray-400">انتخاب‌های شما:</p>
            <div class="flex flex-wrap gap-2">
                <template x-for="option in selectedOptions" :key="option.id">
                    <span class="inline-flex items-center gap-1.5 rounded-lg bg-[#d4af37] px-3 py-1.5 text-sm font-medium text-white shadow-sm">
                        <span x-text="option.value"></span>
                        <button type="button" :disabled="busy" x-on:click="remove(option)" :aria-label="'حذف ' + option.value" class="rounded-full p-0.5 hover:bg-white/20"><x-heroicon-s-x-mark class="h-3.5 w-3.5" /></button>
                    </span>
                </template>
                <template x-for="value in proposals" :key="value">
                    <span class="inline-flex items-center gap-2 rounded-lg border border-amber-300 bg-amber-50 px-3 py-1.5 text-sm text-amber-900">
                        <span x-text="value"></span><span class="text-xs">در انتظار تأیید</span>
                        <button type="button" :disabled="busy" x-on:click="removeProposal(value)" aria-label="حذف مقدار پیشنهادی"><x-heroicon-s-x-mark class="h-3.5 w-3.5" /></button>
                    </span>
                </template>
            </div>
        </div>
        <div class="mb-3 flex gap-2">
            <div class="relative flex-1">
                <x-heroicon-o-magnifying-glass class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                @if($workspace)<label class="mb-1 block text-xs text-gray-600">جستجو یا پیشنهاد مقدار جدید برای {{ $attribute->name }}</label>@endif
                <input type="text" x-model="search" x-on:input="error = ''; rejectedSearch = null" x-on:keydown.enter.prevent maxlength="{{ $workspace ? config('product_completion.validation.attribute_proposed_value_max', 255) : 255 }}"
                       aria-label="جستجو یا مقدار جدید برای {{ $attribute->name }}" placeholder="جستجو یا مقدار جدید..."
                       class="w-full rounded-lg border-gray-300 py-2 pl-3 pr-10 text-sm focus:border-[#d4af37] focus:ring-[#d4af37] dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
            </div>
            <button type="button" x-cloak x-show="canAdd" :disabled="busy" x-on:click="add()"
                    class="flex items-center gap-1 whitespace-nowrap rounded-lg bg-[#d4af37] px-4 py-2 text-sm font-medium text-black hover:bg-[#c49d2f] disabled:opacity-50">
                <x-heroicon-o-plus class="h-4 w-4" />افزودن
            </button>
        </div>
        <p x-cloak x-show="normalizedSearch && existingOption" class="mb-2 text-xs text-gray-500" x-text="existingOption && selectedIds.includes(String(existingOption.id)) ? 'این مقدار قبلاً انتخاب شده است.' : 'این مقدار موجود است؛ آن را از فهرست انتخاب کنید.'"></p>
        <p x-cloak x-show="normalizedSearch && existingProposal" class="mb-2 text-xs text-gray-500">این مقدار قبلاً پیشنهاد شده است.</p>
        <div class="max-h-40 space-y-2 overflow-y-auto pr-1">
            <template x-for="option in matchingOptions" :key="option.id">
                <label class="flex cursor-pointer items-center gap-2 rounded p-2 hover:bg-gray-50 dark:hover:bg-gray-700" :data-search-value="normalize(option.value)">
                    <input :type="multiple ? 'checkbox' : 'radio'" :name="'attr_' + attributeId" :disabled="busy" x-on:change="choose(option)" :checked="selectedIds.includes(String(option.id))" class="rounded border-gray-300 text-[#d4af37] focus:ring-[#d4af37] dark:border-gray-600">
                    <span class="text-sm text-gray-700 dark:text-gray-300" x-text="option.value"></span>
                </label>
            </template>
        </div>
        <p x-cloak x-show="normalizedSearch && !matchingOptions.length && !existingOption" class="mt-2 text-xs text-gray-500">مقداری پیدا نشد؛ می‌توانید مقدار جدید اضافه کنید.</p>
        <p x-cloak x-show="error" x-text="error" role="alert" class="mt-2 text-xs text-red-500"></p>
    </div>
</div>
