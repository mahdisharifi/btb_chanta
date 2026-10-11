<div x-data="{ tab: 'content' }">
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
        <p>فقط بخش‌های فعال Scope قابل ذخیره‌اند. برای مشخصات فنی، هر مورد را با فرمت «نام: مقدار» وارد کنید. مقدارهای جدید ویژگی ابتدا به‌صورت پیشنهاد ذخیره می‌شوند و فقط پس از تأیید نهایی بازرس به فهرست مقادیر سیستم و محصول اضافه خواهند شد.</p>
    </x-product-completion-guide>

    @if($task->status->canEditDraft())
        <div class="mb-4 flex flex-wrap gap-2 rounded-xl border bg-white p-3">
            @foreach(['content' => 'محتوا', 'attributes' => 'ویژگی‌ها', 'gallery' => 'تصاویر'] as $key => $label)
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
                    <label class="block text-sm font-medium">توضیحات کوتاه
                        <textarea wire:model="draft.short_description" rows="4" class="mt-1 w-full rounded-lg border-slate-300"></textarea>
                    </label>
                @endif

                @if(data_get($task->scope_json, 'full_description'))
                    <label class="block text-sm font-medium">توضیحات کامل
                        <textarea wire:model="draft.full_description" rows="12" class="mt-1 w-full rounded-lg border-slate-300"></textarea>
                    </label>
                @endif

                @if(data_get($task->scope_json, 'specifications'))
                    <div>
                        <label for="task-specifications" class="block text-sm font-medium">مشخصات فنی</label>
                        <p class="mt-1 text-xs text-slate-500">هر مشخصه را در یک خط و با فرمت «نام: مقدار» وارد کنید؛ مثال: توان خروجی: 120 وات</p>
                        <textarea id="task-specifications" wire:model="specificationsText" rows="7" class="mt-2 w-full rounded-lg border-slate-300 font-mono text-sm" placeholder="توان خروجی: 120 وات&#10;ظرفیت باتری: 3600 میلی‌آمپرساعت"></textarea>
                        @error('specifications')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                @endif
            </section>

            <section x-cloak x-show="tab === 'attributes'" class="space-y-4 rounded-xl border bg-white p-5">
                @forelse($attributes as $attribute)
                    @php
                        $selection = collect($draft['attributes'] ?? [])->firstWhere('attribute_id', $attribute->id) ?? [];
                        $selectedValueIds = array_map('intval', $selection['value_ids'] ?? []);
                        $proposedValues = $selection['proposed_values'] ?? [];
                    @endphp
                    <div wire:key="task-attribute-{{ $attribute->id }}" class="rounded-lg border border-slate-200 p-4">
                        <p class="mb-2 font-medium">{{ $attribute->name }}@if($attribute->is_required)<span class="mr-1 text-xs text-red-600">(اجباری)</span>@endif</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach($attribute->values as $value)
                                <button type="button" wire:click="toggleAttribute({{ $attribute->id }}, {{ $value->id }}, {{ $attribute->is_multiple ? 'true' : 'false' }})" class="rounded-lg border px-3 py-2 {{ in_array($value->id, $selectedValueIds, true) ? 'border-indigo-500 bg-indigo-50 text-indigo-700' : 'border-slate-200' }}">
                                    {{ $value->value }}
                                </button>
                            @endforeach
                        </div>

                        <div class="mt-4 border-t border-dashed pt-3">
                            <label for="new-attribute-value-{{ $attribute->id }}" class="block text-xs font-medium text-slate-600">پیشنهاد مقدار جدید</label>
                            <div class="mt-1 flex gap-2">
                                <input id="new-attribute-value-{{ $attribute->id }}" type="text" wire:model="newAttributeValues.{{ $attribute->id }}" maxlength="255" placeholder="مقدار جدید را وارد کنید" class="min-w-0 flex-1 rounded-lg border-slate-300 text-sm">
                                <button type="button" wire:click="addProposedAttributeValue({{ $attribute->id }}, {{ $attribute->is_multiple ? 'true' : 'false' }})" class="rounded-lg bg-indigo-600 px-3 py-2 text-sm text-white" wire:loading.attr="disabled">
                                    افزودن
                                </button>
                            </div>
                            @error('newAttributeValues.'.$attribute->id)
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                            @error('attributes.*.proposed_values')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror

                            @if($proposedValues !== [])
                                <div class="mt-3 flex flex-wrap gap-2">
                                    @foreach($proposedValues as $proposedValue)
                                        <span wire:key="proposed-value-{{ $attribute->id }}-{{ md5($proposedValue) }}" class="inline-flex items-center gap-1 rounded-full border border-amber-300 bg-amber-50 px-3 py-1 text-xs text-amber-900">
                                            {{ $proposedValue }}
                                            <span class="text-amber-700">(در انتظار تأیید)</span>
                                            <button type="button" wire:click="removeProposedAttributeValue({{ $attribute->id }}, @js($proposedValue))" class="mr-1 text-amber-700 hover:text-red-700" aria-label="حذف مقدار پیشنهادی">×</button>
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="text-slate-500">این بخش در Scope فعال نیست یا ویژگی‌ای ندارد.</p>
                @endforelse
            </section>

            <section x-cloak x-show="tab === 'gallery'" class="space-y-4 rounded-xl border bg-white p-5">
                @if(data_get($task->scope_json, 'gallery'))
                    <div>
                        <input type="file" wire:model="upload" accept="image/jpeg,image/png,image/webp" class="block w-full text-sm">
                        <button type="button" wire:click="uploadMedia" wire:loading.attr="disabled" class="mt-2 rounded-lg bg-slate-800 px-4 py-2 text-white">بارگذاری تصویر</button>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach(data_get($draft, 'gallery.items', []) as $index => $item)
                            <div class="rounded-lg border p-3">
                                <p class="truncate text-sm">{{ $item['media_public_id'] ?? 'تصویر موجود #'.($item['product_gallery_id'] ?? '-') }}</p>
                                @if(!empty($item['media_public_id']) && isset($media[$item['media_public_id']]))
                                    <img src="{{ route('outsourcing.media.preview', $media[$item['media_public_id']]) }}" class="mt-2 h-32 w-full rounded object-cover" alt="پیش‌نمایش">
                                @endif
                                <div class="mt-2 flex gap-2 text-xs">
                                    <button type="button" wire:click="selectPrimary({{ $index }})" class="{{ !empty($item['is_primary']) ? 'font-bold text-emerald-700' : 'text-slate-500' }}">اصلی</button>
                                    <button type="button" wire:click="selectSeo({{ $index }})" class="{{ !empty($item['is_seo_image']) ? 'font-bold text-indigo-700' : 'text-slate-500' }}">SEO</button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p>تصاویر خارج از Scope این تسک هستند.</p>
                @endif
            </section>

            <div class="sticky bottom-3 flex justify-end gap-3 rounded-xl border bg-white/95 p-3 shadow">
                <button type="submit" wire:loading.attr="disabled" class="rounded-lg bg-slate-800 px-5 py-2 text-white">ذخیره Draft</button>
                <button type="button" wire:click="submit" wire:loading.attr="disabled" class="rounded-lg bg-emerald-600 px-5 py-2 text-white">ارسال برای بررسی</button>
            </div>
        </form>
    @else
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-5 text-amber-900">در وضعیت فعلی امکان ویرایش Draft وجود ندارد.</div>
    @endif
</div>
