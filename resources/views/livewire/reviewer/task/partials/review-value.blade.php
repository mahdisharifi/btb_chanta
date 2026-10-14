@if($field === 'attributes')
    @php
        $selections = collect($value ?? [])->keyBy('attribute_id');
        $otherSelections = collect($change[$side === 'before' ? 'after' : 'before'] ?? [])->keyBy('attribute_id');
        $attributeIds = $selections->keys()->merge($otherSelections->keys())->unique();
    @endphp
    <dl class="space-y-3">
        @forelse($attributeIds as $attributeId)
            @php
                $selection = $selections->get($attributeId, []);
                $values = $selection['value_ids'] ?? [];
                $proposals = $selection['proposed_values'] ?? [];
            @endphp
            <div class="rounded-lg border border-slate-200 bg-white p-3">
                <dt class="mb-2 font-semibold text-slate-800">{{ $attributeNames[$attributeId] ?? 'ویژگی حذف‌شده' }}</dt>
                <dd class="flex flex-wrap gap-2">
                    @foreach($values as $valueId)
                        <span class="rounded-lg bg-slate-100 px-2 py-1 text-slate-800"><bdi>{{ $attributeValues[$valueId] ?? 'مقدار در دسترس نیست' }}</bdi></span>
                    @endforeach
                    @foreach($proposals as $proposal)
                        <span class="rounded-lg border border-amber-300 bg-amber-50 px-2 py-1 text-amber-950">
                            <bdi class="font-semibold">{{ $proposal }}</bdi>
                            <span class="block text-xs text-amber-800">مقدار پیشنهادی · در انتظار تأیید</span>
                        </span>
                    @endforeach
                    @if($values === [] && $proposals === [])
                        <span class="text-slate-500">مقداری ثبت نشده است</span>
                    @endif
                </dd>
            </div>
        @empty
            <p class="text-slate-500">ویژگی‌ای ثبت نشده است.</p>
        @endforelse
    </dl>
@elseif($field === 'gallery')
    <div class="grid gap-3 sm:grid-cols-2 md:grid-cols-1 xl:grid-cols-2">
        @forelse(collect(data_get($value, 'items', []))->sortBy('sort_order') as $item)
            @php
                $uploaded = !empty($item['media_public_id']) ? $uploadedImages->get($item['media_public_id']) : null;
                $existing = !empty($item['product_gallery_id']) ? $existingImages->get($item['product_gallery_id']) : null;
                $published = $uploaded?->published_product_gallery_id ? $existingImages->get($uploaded->published_product_gallery_id) : null;
                $previewUrl = $published
                    ? route('outsourcing.gallery.preview', [$task, $published])
                    : ($uploaded ? route('outsourcing.media.preview', $uploaded)
                    : ($existing ? route('outsourcing.gallery.preview', [$task, $existing]) : null));
                $removed = ($item['action'] ?? 'keep') === 'remove';
            @endphp
            <figure class="overflow-hidden rounded-lg border border-slate-200 bg-white {{ $removed ? 'opacity-60' : '' }}">
                @if($previewUrl)
                    <a href="{{ $previewUrl }}" target="_blank" rel="noopener" class="block" aria-label="مشاهده تصویر {{ $loop->iteration }} در اندازه کامل">
                        <img src="{{ $previewUrl }}" alt="{{ $item['alt_text'] ?? $item['title'] ?? 'تصویر محصول' }}" class="h-44 w-full bg-white object-contain" loading="lazy">
                    </a>
                @else
                    <div class="flex h-44 items-center justify-center bg-slate-100 text-slate-500">تصویر در دسترس نیست</div>
                @endif
                <figcaption class="space-y-2 border-t p-3">
                    <p class="font-medium text-slate-800">{{ ($item['title'] ?? null) ?: 'تصویر محصول' }}</p>
                    <div class="flex flex-wrap gap-1 text-xs">
                        @if($removed)
                            <span class="rounded bg-red-100 px-2 py-1 text-red-800">برای حذف</span>
                        @elseif($uploaded)
                            <span class="rounded bg-indigo-100 px-2 py-1 text-indigo-800">تصویر جدید بارگذاری‌شده</span>
                        @else
                            <span class="rounded bg-slate-100 px-2 py-1 text-slate-600">تصویر قبلی</span>
                        @endif
                        @if(!empty($item['is_primary']) && !$removed)
                            <span class="rounded bg-emerald-100 px-2 py-1 text-emerald-800">تصویر اصلی</span>
                        @endif
                    </div>
                </figcaption>
            </figure>
        @empty
            <p class="text-slate-500">تصویری ثبت نشده است.</p>
        @endforelse
    </div>
@else
    @php
        // Render submitted text as text; never execute contractor-provided markup.
        $readableText = is_scalar($value) ? html_entity_decode(strip_tags(preg_replace('/<\/?(?:p|div|h[1-6]|li|br)\b[^>]*>/i', "\n", (string) $value)), ENT_QUOTES | ENT_HTML5, 'UTF-8') : '';
    @endphp
    @if(trim($readableText) !== '')
        <div class="whitespace-pre-wrap break-words leading-7 text-slate-800">{{ trim($readableText) }}</div>
    @else
        <p class="text-slate-500">محتوایی ثبت نشده است.</p>
    @endif
@endif
