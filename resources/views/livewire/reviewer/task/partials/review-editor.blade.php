@if($showReviewEditor)
    <section id="review-editor" dir="rtl" x-data x-on:review-editor-focus.window="$nextTick(() => requestAnimationFrame(() => $refs.reviewEditorHeading.focus()))" class="min-w-0 space-y-5 rounded-xl border border-indigo-200 bg-white p-4 sm:p-5" aria-labelledby="review-editor-heading">
        @if(data_get($submission->approval_selection_json, 'schema_version') === 2)
            <p role="status" class="break-words rounded-lg border border-indigo-200 bg-indigo-50 p-3 text-sm leading-7 text-indigo-950">برای این ارسال، نسخهٔ بازبینی‌شده و تصمیم‌های ذخیره‌شده وجود دارد. ویرایشگر، متن‌ها و تصمیم‌های ذخیره‌شدهٔ بازبین را بارگذاری کرده است؛ این محتوا ممکن است با ارسال اصلی پیمانکار متفاوت باشد. پیش از تأیید نهایی، آن‌ها را بررسی کنید. تأیید نهایی بر اساس نسخهٔ بازبینی‌شدهٔ ذخیره‌شده انجام می‌شود.</p>
        @endif
        @if($commissionPreviewPendingReview)
            <p role="status" class="break-words rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm leading-7 text-amber-950">این ارسال دارای تأیید انتخابی قدیمی است. پیش‌نمایش پورسانت فعلاً بر پایهٔ ارسال اصلی است و مبلغ نهایی نیست. تصمیم‌های هر مورد را بررسی و ذخیره کنید تا پیش‌نمایش بر اساس تصمیم‌های جدید محاسبه شود.</p>
        @endif
        <div>
            <h2 id="review-editor-heading" x-ref="reviewEditorHeading" tabindex="-1" class="text-lg font-bold text-indigo-950 focus:outline-none focus:ring-2 focus:ring-indigo-500">ویرایش و تصمیم بازبین</h2>
            <p class="mt-2 text-sm leading-7 text-slate-600">محتوای کامل محدودهٔ مجاز را بررسی کنید. رد هر مورد، محتوای فعلی محصول را حفظ می‌کند. تأیید نهایی، ابتدا ویرایش‌های این بخش را ذخیره می‌کند.</p>
            <p class="mt-1 text-sm text-slate-600">بستن ویرایشگر نیز تغییرات را ذخیره می‌کند.</p>
        </div>

        @if($errors->any())
            <div role="alert" class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">
                <ul class="list-inside list-disc space-y-1">
                    @foreach($errors->all() as $message)
                        <li class="break-words">{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @foreach(['title' => 'عنوان محصول', 'short_description' => 'توضیحات کوتاه', 'full_description' => 'توضیحات کامل'] as $textField => $textLabel)
            @if(data_get($task->scope_json, $textField, false) === true)
                <fieldset wire:key="review-editor-text-{{ $textField }}" class="min-w-0 rounded-lg border border-slate-200 p-3">
                    <legend class="px-1 font-semibold text-slate-800">{{ $textLabel }}</legend>
                    <label for="review-editor-{{ $textField }}" class="mb-2 block text-sm">متن {{ $textLabel }}</label>
                    @if($textField === 'title')
                        <input id="review-editor-{{ $textField }}" type="text" wire:model="reviewPayload.{{ $textField }}" maxlength="{{ config('product_completion.validation.title_max', 255) }}" class="min-h-11 w-full min-w-0 rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                    @else
                        <textarea id="review-editor-{{ $textField }}" wire:model="reviewPayload.{{ $textField }}" rows="{{ $textField === 'full_description' ? 8 : 4 }}" maxlength="{{ config('product_completion.validation.'.$textField.'_max', 1000) }}" class="w-full min-w-0 rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                    @endif
                    <label for="review-decision-{{ $textField }}" class="mb-1 mt-3 block text-sm">تصمیم دربارهٔ {{ $textLabel }}</label>
                    <select id="review-decision-{{ $textField }}" wire:model="reviewDecisions.text:{{ $textField }}" class="min-h-11 w-full rounded-lg border-slate-300 sm:w-auto">
                        <option value="approve">تأیید متن</option>
                        <option value="reject">رد متن و حفظ محتوای فعلی</option>
                    </select>
                </fieldset>
            @endif
        @endforeach

        @if(data_get($task->scope_json, 'attributes', false) === true)
            <section class="min-w-0 space-y-4" aria-labelledby="review-editor-attributes-heading">
                <h3 id="review-editor-attributes-heading" class="font-bold text-slate-900">ویژگی‌های دسته‌بندی محصول</h3>
                @foreach($reviewPayload['attributes'] ?? [] as $attributeIndex => $selection)
                    @php
                        $editorAttribute = $editorAttributes->get((int) ($selection['attribute_id'] ?? 0));
                    @endphp
                    @if($editorAttribute)
                        <fieldset wire:key="review-editor-attribute-{{ $editorAttribute->id }}" class="min-w-0 rounded-lg border border-slate-200 p-3">
                            <legend class="max-w-full break-words px-1 font-semibold">{{ $editorAttribute->name }}{{ $editorAttribute->unit ? ' ('.$editorAttribute->unit.')' : '' }}</legend>
                            <p id="review-attribute-help-{{ $editorAttribute->id }}" class="mb-2 text-sm text-slate-600">{{ $editorAttribute->is_multiple ? 'انتخاب چند مقدار مجاز است.' : 'فقط یک مقدار انتخاب یا پیشنهاد کنید.' }} حداکثر ۵۰ مقدار پرکاربرد، به‌علاوهٔ مقدارهای انتخاب‌شده نمایش داده می‌شود.</p>
                            <div class="grid max-h-64 gap-1 overflow-y-auto rounded-lg bg-slate-50 p-2 sm:grid-cols-2" role="group" aria-label="مقدارهای {{ $editorAttribute->name }}" aria-describedby="review-attribute-help-{{ $editorAttribute->id }}">
                                @forelse($editorAttribute->values as $option)
                                    <label wire:key="review-editor-value-{{ $editorAttribute->id }}-{{ $option->id }}" class="flex min-h-11 min-w-0 cursor-pointer items-center gap-2 rounded px-2 focus-within:ring-2 focus-within:ring-indigo-500">
                                        <input type="checkbox" wire:click="toggleReviewAttributeValue({{ $attributeIndex }}, {{ $option->id }})" wire:loading.attr="disabled" @checked(in_array((int) $option->id, array_map('intval', $selection['value_ids'] ?? []), true)) class="h-5 w-5 shrink-0 rounded border-slate-400 text-indigo-600">
                                        <span class="min-w-0 break-words text-sm">{{ $option->value }}</span>
                                    </label>
                                @empty
                                    <p class="p-2 text-sm text-slate-500">مقدار ثبت‌شده‌ای وجود ندارد؛ مقدار مناسب را پیشنهاد کنید.</p>
                                @endforelse
                            </div>
                            <div class="mt-3 space-y-2">
                                @foreach($selection['proposed_values'] ?? [] as $proposalIndex => $proposal)
                                    <div wire:key="review-editor-proposal-{{ $editorAttribute->id }}-{{ $proposalIndex }}" class="flex min-w-0 flex-col gap-2 sm:flex-row sm:items-end">
                                        <div class="min-w-0 flex-1">
                                            <label for="review-proposal-{{ $editorAttribute->id }}-{{ $proposalIndex }}" class="mb-1 block text-sm">مقدار پیشنهادی {{ $loop->iteration }}</label>
                                            <input id="review-proposal-{{ $editorAttribute->id }}-{{ $proposalIndex }}" type="text" wire:model="reviewPayload.attributes.{{ $attributeIndex }}.proposed_values.{{ $proposalIndex }}" maxlength="{{ config('product_completion.validation.attribute_proposed_value_max', 255) }}" class="min-h-11 w-full min-w-0 rounded-lg border-slate-300">
                                        </div>
                                        <button type="button" wire:click="removeReviewProposedValue({{ $attributeIndex }}, {{ $proposalIndex }})" wire:loading.attr="disabled" class="min-h-11 rounded-lg border border-red-300 px-3 text-sm text-red-700 disabled:opacity-50" aria-label="حذف مقدار پیشنهادی {{ $loop->iteration }} برای {{ $editorAttribute->name }}">حذف مقدار</button>
                                    </div>
                                @endforeach
                                <button type="button" wire:click="addReviewProposedValue({{ $attributeIndex }})" wire:loading.attr="disabled" class="min-h-11 rounded-lg border border-indigo-300 px-3 text-sm text-indigo-700 disabled:opacity-50">پیشنهاد مقدار برای {{ $editorAttribute->name }}</button>
                            </div>
                            <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                                <div class="min-w-0">
                                    <label for="review-attribute-decision-{{ $editorAttribute->id }}" class="mb-1 block text-sm">تصمیم دربارهٔ {{ $editorAttribute->name }}</label>
                                    <select id="review-attribute-decision-{{ $editorAttribute->id }}" wire:model="reviewDecisions.attribute:{{ $editorAttribute->id }}" class="min-h-11 w-full rounded-lg border-slate-300">
                                        <option value="approve">تأیید ویژگی</option>
                                        <option value="reject">رد تغییر و حفظ مقدار فعلی</option>
                                    </select>
                                </div>
                                <button type="button" wire:click="removeReviewAttribute({{ $attributeIndex }})" wire:loading.attr="disabled" class="min-h-11 rounded-lg border border-red-300 px-3 text-sm text-red-700 disabled:opacity-50">حذف ردیف {{ $editorAttribute->name }}</button>
                            </div>
                        </fieldset>
                    @endif
                @endforeach
                @foreach($reviewDecisions as $decisionIdentity => $decisionValue)
                    @if(str_starts_with($decisionIdentity, 'attribute:') && ! collect($reviewPayload['attributes'] ?? [])->contains('attribute_id', (int) substr($decisionIdentity, 10)))
                        @php
                            $removedAttributeId = (int) substr($decisionIdentity, 10);
                            $removedAttributeName = $editorAttributes->get($removedAttributeId)?->name ?? 'ویژگی '.$removedAttributeId;
                        @endphp
                        <div wire:key="review-editor-removed-{{ $removedAttributeId }}" class="min-w-0 rounded-lg border border-amber-200 bg-amber-50 p-3">
                            <label for="review-removed-decision-{{ $removedAttributeId }}" class="mb-2 block break-words text-sm font-semibold">تصمیم دربارهٔ ردیف کنارگذاشته‌شده: {{ $removedAttributeName }}</label>
                            <select id="review-removed-decision-{{ $removedAttributeId }}" wire:model="reviewDecisions.{{ $decisionIdentity }}" class="min-h-11 w-full rounded-lg border-amber-300">
                                <option value="approve">تأیید کنارگذاشتن این ویژگی</option>
                                <option value="reject">رد تغییر و حفظ مقدار فعلی محصول</option>
                            </select>
                        </div>
                    @endif
                @endforeach
                <details class="rounded-lg border border-slate-200 p-3">
                    <summary class="min-h-11 cursor-pointer py-2 font-semibold text-indigo-800">افزودن ویژگی از دسته‌بندی محصول</summary>
                    <p class="mb-2 text-sm text-slate-600">حذف ردیف، ویژگی را از نسخهٔ ویرایش‌شده کنار می‌گذارد. برای حفظ مقدار فعلی محصول، تصمیم «رد تغییر» را انتخاب کنید.</p>
                    <div class="grid gap-2 sm:grid-cols-2">
                        @forelse($editorAttributes as $availableAttribute)
                            @if(! collect($reviewPayload['attributes'] ?? [])->contains('attribute_id', $availableAttribute->id))
                                <button type="button" wire:key="review-add-attribute-{{ $availableAttribute->id }}" wire:click="addReviewAttribute({{ $availableAttribute->id }})" wire:loading.attr="disabled" class="min-h-11 min-w-0 break-words rounded-lg border border-indigo-200 px-3 py-2 text-start text-sm text-indigo-800 disabled:opacity-50">افزودن {{ $availableAttribute->name }}</button>
                            @endif
                        @empty
                            <p class="text-sm text-slate-500">این دسته‌بندی ویژگی قابل انتخاب ندارد.</p>
                        @endforelse
                    </div>
                </details>
            </section>
        @endif

        @if($reviewMediaItems->isNotEmpty() && (data_get($task->scope_json, 'gallery') === true || is_array(data_get($task->scope_json, 'gallery'))))
            <section aria-labelledby="review-editor-media-heading" class="min-w-0">
                <h3 id="review-editor-media-heading" class="mb-3 font-bold text-slate-900">تصمیم دربارهٔ تصاویر همین ارسال</h3>
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach($reviewMediaItems as $reviewMediaItem)
                        @php
                            $previewUrl = $reviewMediaItem->media
                                ? route('outsourcing.media.preview', $reviewMediaItem->media)
                                : ($reviewMediaItem->productGallery ? route('outsourcing.gallery.preview', [$task, $reviewMediaItem->productGallery]) : null);
                            $isRemoval = $reviewMediaItem->action === 'remove';
                        @endphp
                        <fieldset wire:key="review-editor-media-{{ $reviewMediaItem->id }}" class="min-w-0 rounded-lg border border-slate-200 p-3">
                            <legend class="px-1 text-sm font-semibold">تصویر {{ $loop->iteration }} — {{ $isRemoval ? 'درخواست حذف' : 'ثبت یا حفظ تصویر' }}</legend>
                            @if($previewUrl)
                                <a href="{{ $previewUrl }}" target="_blank" rel="noopener" class="block rounded focus:outline-none focus:ring-2 focus:ring-indigo-500" aria-label="نمایش تصویر {{ $loop->iteration }} در برگهٔ جدید">
                                    <img src="{{ $previewUrl }}" alt="{{ $reviewMediaItem->alt_text ?: ($reviewMediaItem->title ?: 'تصویر محصول برای بازبینی') }}" loading="lazy" class="h-40 w-full rounded bg-slate-50 object-contain">
                                </a>
                            @else
                                <p class="py-4 text-sm text-slate-500">پیش‌نمایش تصویر در دسترس نیست.</p>
                            @endif
                            @if(!$isRemoval && $reviewMediaItem->is_primary)
                                <p class="mt-2 inline-flex rounded bg-emerald-100 px-2 py-1 text-xs font-semibold text-emerald-800">تصویر اصلی</p>
                            @endif
                            <p class="mt-2 break-words text-xs text-slate-500">شناسهٔ تصویر: {{ $reviewMediaItem->product_gallery_id ?? $reviewMediaItem->media_id }}؛ ردیف ارسال: {{ $reviewMediaItem->id }}</p>
                            <label for="review-media-decision-{{ $reviewMediaItem->id }}" class="mb-1 mt-3 block text-sm">تصمیم دربارهٔ تصویر {{ $loop->iteration }}</label>
                            <select id="review-media-decision-{{ $reviewMediaItem->id }}" wire:model="reviewDecisions.media:{{ $reviewMediaItem->id }}" class="min-h-11 w-full rounded-lg border-slate-300">
                                <option value="approve">{{ $isRemoval ? 'تأیید حذف تصویر' : 'تأیید تصویر' }}</option>
                                <option value="reject">{{ $isRemoval ? 'رد حذف و نگه‌داشتن تصویر' : 'رد تغییر این تصویر' }}</option>
                            </select>
                        </fieldset>
                    @endforeach
                </div>
            </section>
        @endif

        <div class="flex flex-col gap-3 border-t border-slate-200 pt-4 sm:flex-row sm:items-center">
            <button type="button" wire:click="saveReviewEdits" wire:loading.attr="disabled" class="min-h-11 rounded-lg bg-indigo-700 px-4 py-2 font-semibold text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-50">ذخیرهٔ ویرایش و تصمیم‌ها</button>
            <div role="status" aria-live="polite" class="text-sm text-slate-700">
                <p wire:dirty.remove wire:target="reviewPayload,reviewDecisions">{{ $reviewEditsSaved ? 'ویرایش‌ها و تصمیم‌های بازبینی ذخیره شد.' : 'تغییرات با ذخیره یا تأیید نهایی ثبت می‌شوند.' }}</p>
                <p wire:dirty wire:target="reviewPayload,reviewDecisions" style="display: none">تغییرات ذخیره‌نشده دارید؛ برای ثبت، ذخیره یا تأیید نهایی را انتخاب کنید.</p>
            </div>
        </div>
    </section>
@endif
