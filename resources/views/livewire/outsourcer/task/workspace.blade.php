@php
    $workspaceTabs = [];
    if (data_get($task->scope_json, 'title') || data_get($task->scope_json, 'short_description') || data_get($task->scope_json, 'full_description')) {
        $workspaceTabs['content'] = 'محتوا';
    }
    if (data_get($task->scope_json, 'attributes')) {
        $workspaceTabs['attributes'] = 'ویژگی‌ها';
    }
    if (data_get($task->scope_json, 'gallery')) {
        $workspaceTabs['gallery'] = 'تصاویر';
    }
@endphp
<div x-data="{ tab: '{{ array_key_first($workspaceTabs) ?? 'content' }}' }">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold">فضای کار تکمیل محصول</h1>
            <p class="text-sm text-slate-500">{{ $task->status->label() }} · Draft {{ $draftVersion }} · اصلاح باقی‌مانده {{ $task->remainingRevisions() }}</p>
        </div>
        <div class="flex gap-2">
            @can('acceptTask', $task)
                <button wire:click="accept" class="rounded-lg bg-indigo-600 px-4 py-2 text-white">پذیرش</button>
            @endcan
            @can('startTask', $task)
                <button wire:click="start" class="rounded-lg bg-indigo-600 px-4 py-2 text-white">شروع</button>
            @endcan
            @can('resumeTask', $task)
                <button wire:click="resumeRevision" class="rounded-lg bg-amber-600 px-4 py-2 text-white">شروع اصلاح</button>
            @endcan
        </div>
    </div>

    <x-product-completion-guide title="راهنمای فضای کار">
        <p>فقط بخش‌های مجاز این تسک قابل ذخیره‌اند. پیش‌نویس می‌تواند ناقص باشد؛ برای ارسال، فیلدهای الزامی باید تکمیل شوند. مقدارهای جدید ویژگی ابتدا به‌صورت پیشنهاد ذخیره می‌شوند و فقط پس از تأیید نهایی بازرس به فهرست مقادیر سیستم و محصول اضافه خواهند شد.</p>
    </x-product-completion-guide>

    @include('livewire.outsourcer.task.partials.task-brief')

    @if($openRevision)
        @php
            $revisionFieldLabels = [
                'title' => 'عنوان محصول',
                'short_description' => 'توضیحات کوتاه',
                'full_description' => 'توضیحات کامل',
                'attributes' => 'ویژگی‌های محصول',
                'gallery' => 'تصاویر محصول',
            ];
        @endphp
        <section class="mb-4 rounded-xl border border-amber-300 bg-amber-50 p-5 text-amber-950" aria-labelledby="revision-heading">
            <h2 id="revision-heading" class="text-lg font-bold">درخواست اصلاح بازبین</h2>
            <p class="mt-1 text-sm">{{ $openRevision->requester?->name ?? 'بازبین' }} · {{ $openRevision->classification->label() }}</p>
            <p class="mt-4 text-sm font-semibold">بخش‌های نیازمند اصلاح</p>
            <ul class="mt-2 flex flex-wrap gap-2 text-sm">
                @forelse($openRevision->requested_fields_json ?? [] as $revisionField)
                    <li class="rounded-lg border border-amber-200 bg-white px-3 py-1">{{ $revisionFieldLabels[$revisionField] ?? 'سایر بخش‌های محتوا' }}</li>
                @empty
                    <li>تمام بخش‌های مجاز تسک</li>
                @endforelse
            </ul>
            <p class="mt-4 text-sm font-semibold">توضیح بازبین</p>
            <p class="mt-1 whitespace-pre-wrap break-words text-sm leading-7">{{ $openRevision->reason }}</p>
            <p class="mt-3 text-xs">پس از اعمال اصلاحات خواسته‌شده، محتوا را دوباره برای بررسی ارسال کنید.</p>
        </section>
    @endif

    @if($errors->any())
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">
            <p class="mb-2 font-semibold">ذخیره یا ارسال انجام نشد؛ موارد زیر را بررسی کنید:</p>
            <ul class="list-inside list-disc">@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
        </div>
    @endif

    @if($task->due_at && $task->due_at->isPast() && $task->status->canEditDraft())
        <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900" role="alert">مهلت این تسک پایان یافته است؛ مدیر باید مهلت را تمدید کند تا ذخیره و ارسال ممکن شود.</div>
    @endif

    @if($task->status->canEditDraft())
        <div class="mb-4 flex flex-wrap gap-2 rounded-xl border bg-white p-3">
            @foreach($workspaceTabs as $key => $label)
                <button type="button" @click="tab = '{{ $key }}'" class="rounded-lg px-3 py-2" :class="tab === '{{ $key }}' ? 'bg-indigo-100 text-indigo-700' : 'bg-slate-50'">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        <form wire:submit="saveDraft" class="space-y-4">
            <section x-show="tab === 'content'" class="space-y-4 rounded-xl border bg-white p-5">
                @if(data_get($task->scope_json, 'title'))
                    <label class="block text-sm font-medium">عنوان
                        <input wire:model="draft.title" class="mt-1 w-full rounded-lg border-slate-300">
                    </label>
                @endif

                @if(data_get($task->scope_json, 'short_description'))
                    <label class="block text-sm font-medium">توضیحات کوتاه (برای ارسال الزامی)
                        <textarea wire:model="draft.short_description" rows="4" class="mt-1 w-full rounded-lg border-slate-300"></textarea>
                    </label>
                @endif

                @if(data_get($task->scope_json, 'full_description'))
                    <label class="block text-sm font-medium">توضیحات کامل
                        <textarea wire:model="draft.full_description" rows="12" class="mt-1 w-full rounded-lg border-slate-300"></textarea>
                    </label>
                @endif

            </section>

            <section x-cloak x-show="tab === 'attributes'" class="space-y-4 rounded-xl border bg-white p-5">
                @forelse($attributes as $attribute)
                    @php
                        $selection = collect($draft['attributes'] ?? [])->firstWhere('attribute_id', $attribute->id) ?? [];
                        $selectedValueIds = array_map('intval', $selection['value_ids'] ?? []);
                        $proposedValues = $selection['proposed_values'] ?? [];
                    @endphp
                    <div wire:key="task-attribute-{{ $attribute->id }}" x-data="{ search: '', normalize(text) { return text.toLowerCase().replace(/ي/g, 'ی').replace(/ك/g, 'ک').replace(/\u200C/g, ' ').trim(); } }" class="overflow-hidden rounded-lg border border-gray-200">
                        <div class="flex flex-wrap items-center justify-between gap-2 bg-gray-50 px-4 py-3">
                            <h4 class="font-semibold text-gray-800">{{ $attribute->name }}@if($attribute->is_required)<span class="mr-1 text-xs text-red-600">(اجباری)</span>@endif</h4>
                            <div class="flex gap-2 text-xs"><span class="rounded bg-blue-100 px-2 py-1 text-blue-800">{{ $attribute->is_multiple ? 'چند انتخابی' : 'تک انتخابی' }}</span><span class="rounded bg-[#d4af37]/10 px-2 py-1 text-amber-800">{{ count($selectedValueIds) + count($proposedValues) }} انتخاب شده</span></div>
                        </div>
                        <div class="p-4">
                            @if($selectedValueIds !== [] || $proposedValues !== [])
                                <div class="mb-4 border-b border-gray-200 pb-4">
                                    <p class="mb-2 text-xs font-medium text-gray-500">انتخاب‌های شما:</p>
                                    <div class="flex flex-wrap gap-2">
                                        @foreach($attribute->values as $value)
                                            @if(in_array($value->id, $selectedValueIds, true))
                                                <span class="inline-flex items-center gap-2 rounded-lg bg-[#d4af37] px-3 py-1.5 text-sm font-medium text-white shadow-sm">{{ $value->value }}<button type="button" wire:click="removeAttributeValue({{ $attribute->id }}, {{ $value->id }})" aria-label="حذف انتخاب {{ $value->value }}" class="rounded-full px-1 hover:bg-white/20">×</button></span>
                                            @endif
                                        @endforeach
                                        @foreach($proposedValues as $proposedValue)
                                            <span class="inline-flex items-center gap-2 rounded-lg border border-amber-300 bg-amber-50 px-3 py-1.5 text-sm text-amber-900">{{ $proposedValue }} <span class="text-xs">در انتظار تأیید</span><button type="button" wire:click="removeProposedAttributeValue({{ $attribute->id }}, @js($proposedValue))" aria-label="حذف مقدار پیشنهادی">×</button></span>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                            <label for="attribute-search-{{ $attribute->id }}" class="mb-1 block text-xs text-gray-600">جستجو یا پیشنهاد مقدار جدید برای {{ $attribute->name }}</label>
                            <div class="mb-3 flex gap-2" x-on:attribute-value-proposed.window="if ($event.detail.attributeId === {{ $attribute->id }}) search = ''">
                                <div class="relative min-w-0 flex-1">
                                    <x-heroicon-o-magnifying-glass class="pointer-events-none absolute right-3 top-2.5 h-4 w-4 text-gray-400"/>
                                    <input id="attribute-search-{{ $attribute->id }}" type="text" x-model="search" wire:model="newAttributeValues.{{ $attribute->id }}" @keydown.enter.prevent maxlength="255" placeholder="جستجو یا مقدار جدید..." class="w-full rounded-lg border-gray-300 py-2 pl-3 pr-10 text-sm focus:border-[#d4af37] focus:ring-[#d4af37]">
                                </div>
                                <button type="button" x-cloak x-show="search.trim().length > 0" wire:click="addProposedAttributeValue({{ $attribute->id }}, {{ $attribute->is_multiple ? 'true' : 'false' }})" wire:loading.attr="disabled" class="flex items-center gap-1 whitespace-nowrap rounded-lg bg-[#d4af37] px-4 py-2 text-sm font-medium text-black hover:bg-[#c49d2f]"><x-heroicon-o-plus class="h-4 w-4"/>افزودن</button>
                            </div>
                            <div class="max-h-40 space-y-2 overflow-y-auto" x-ref="options">
                                @foreach($attribute->values as $value)
                                    @if(!in_array($value->id, $selectedValueIds, true))
                                        <label data-value="{{ $value->value }}" x-show="!search || normalize($el.dataset.value).includes(normalize(search))" class="flex cursor-pointer items-center gap-2 rounded p-2 hover:bg-gray-50">
                                            <input type="{{ $attribute->is_multiple ? 'checkbox' : 'radio' }}" name="task-attribute-{{ $attribute->id }}" wire:click="toggleAttribute({{ $attribute->id }}, {{ $value->id }}, {{ $attribute->is_multiple ? 'true' : 'false' }})" class="border-gray-300 text-[#d4af37] focus:ring-[#d4af37]">
                                            <span class="text-sm text-gray-700">{{ $value->value }}</span>
                                        </label>
                                    @endif
                                @endforeach
                            </div>
                            <p x-cloak x-show="search && !Array.from($refs.options.querySelectorAll('[data-value]')).some(el => normalize(el.dataset.value).includes(normalize(search)))" class="mt-2 text-sm text-gray-500">مقداری پیدا نشد؛ می‌توانید مقدار جدید پیشنهاد دهید.</p>

                        <div class="mt-3">
                            @error('newAttributeValues.'.$attribute->id)
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                            @error('attributes.*.proposed_values')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror

                        </div>
                        </div>
                    </div>
                @empty
                    <p class="text-slate-500">این تسک ویژگی قابل تکمیلی ندارد.</p>
                @endforelse
            </section>

            <section x-cloak x-show="tab === 'gallery'" class="space-y-4 rounded-xl border bg-white p-5">
                @if(data_get($task->scope_json, 'gallery'))
                    <div>
                        <input type="file" wire:model="upload" accept="image/jpeg,image/png,image/webp" class="block w-full text-sm">
                        <button type="button" wire:click="uploadMedia" @cannot('manageMedia', $task) disabled @endcannot wire:loading.attr="disabled" class="mt-2 rounded-lg bg-slate-800 px-4 py-2 text-white">بارگذاری تصویر</button>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach(data_get($draft, 'gallery.items', []) as $index => $item)
                            <div wire:key="draft-image-{{ $item['media_public_id'] ?? $item['product_gallery_id'] ?? $index }}" class="rounded-lg border p-3">
                                <p class="truncate text-sm">{{ $item['media_public_id'] ?? 'تصویر موجود #'.($item['product_gallery_id'] ?? '-') }}</p>
                                @if(!empty($item['media_public_id']) && isset($media[$item['media_public_id']]))
                                    <img src="{{ route('outsourcing.media.preview', $media[$item['media_public_id']]) }}" class="mt-2 h-32 w-full rounded object-cover" alt="پیش‌نمایش">
                                @elseif(!empty($item['product_gallery_id']) && isset($existingImages[$item['product_gallery_id']]))
                                    <img src="{{ route('outsourcing.gallery.preview', [$task, $existingImages[$item['product_gallery_id']]]) }}" class="mt-2 h-32 w-full rounded object-contain" alt="تصویر فعلی محصول">
                                @endif
                                <div class="mt-2 flex gap-2 text-xs">
                                    <button type="button" wire:click="selectPrimary({{ $index }})" class="{{ !empty($item['is_primary']) ? 'font-bold text-emerald-700' : 'text-slate-500' }}">اصلی</button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p>تکمیل تصاویر برای این تسک درخواست نشده است.</p>
                @endif
            </section>

            <div class="sticky bottom-3 flex justify-end gap-3 rounded-xl border bg-white/95 p-3 shadow">
                <button type="submit" @cannot('updateDraft', $task) disabled @endcannot wire:loading.attr="disabled" class="rounded-lg bg-slate-800 px-5 py-2 text-white">ذخیره پیش‌نویس</button>
                <button type="button" wire:click="submit" @cannot('submitTask', $task) disabled @endcannot wire:loading.attr="disabled" class="rounded-lg bg-emerald-600 px-5 py-2 text-white">ارسال برای بررسی</button>
            </div>
        </form>
    @elseif(in_array($task->status, [\App\Enums\ProductCompletionTaskStatus::ASSIGNED, \App\Enums\ProductCompletionTaskStatus::ACCEPTED], true))
        <div class="rounded-xl border border-indigo-200 bg-indigo-50 p-4 text-sm text-indigo-900">{{ $task->status === \App\Enums\ProductCompletionTaskStatus::ASSIGNED ? 'پس از پذیرش و شروع تسک، بخش‌های ویرایش در همین صفحه فعال می‌شوند.' : 'با زدن دکمهٔ «شروع» بالای صفحه، بخش‌های ویرایش فعال می‌شوند.' }}</div>
    @else
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-5 text-amber-900">در وضعیت فعلی امکان ویرایش Draft وجود ندارد.</div>
    @endif
</div>
