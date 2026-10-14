<details class="border-t border-slate-200" wire:key="product-reference-{{ $task->public_id }}">
    <summary class="cursor-pointer px-5 py-4 text-sm font-semibold text-indigo-800">مشاهده اطلاعات و تصاویر ثبت‌شده محصول</summary>
    <div class="space-y-5 px-5 pb-5">
        <p class="rounded-lg bg-blue-50 p-3 text-sm leading-7 text-blue-900">این بخش، اطلاعات فعلی ثبت‌شده روی محصول را نشان می‌دهد. تغییرات پیش‌نویس شما در تب‌های پایین قرار دارند و پس از تأیید بازبین روی محصول اعمال می‌شوند.</p>
        @if($task->product?->quantity_per_packaging)
            <p class="text-sm text-slate-700">بسته‌بندی: <bdi>{{ $task->product->quantity_per_packaging }}</bdi> عدد در هر {{ $task->product->packaging_unit_label }}</p>
        @endif
        <section aria-label="تصاویر ثبت‌شده محصول">
            <h3 class="mb-3 text-sm font-semibold text-slate-800">تصاویر فعلی محصول</h3>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                @forelse($existingImages as $image)
                    <a href="{{ route('outsourcing.gallery.preview', [$task, $image]) }}" target="_blank" rel="noopener noreferrer" class="rounded-lg border border-slate-200 bg-white p-2" aria-label="مشاهده تصویر {{ $loop->iteration }} محصول در اندازه کامل">
                        <img src="{{ route('outsourcing.gallery.preview', [$task, $image]) }}" alt="{{ $image->alt_text ?: $task->product->title }}" loading="lazy" class="h-28 w-full rounded object-contain">
                        @if($image->is_primary)<span class="mt-2 block text-center text-xs text-emerald-700">تصویر اصلی</span>@endif
                    </a>
                @empty
                    <p class="col-span-full text-sm text-slate-500">تصویری برای محصول ثبت نشده است.</p>
                @endforelse
            </div>
        </section>
        <section aria-label="ویژگی‌های ثبت‌شده محصول">
            <h3 class="mb-3 text-sm font-semibold text-slate-800">ویژگی‌های فعلی محصول</h3>
            <dl class="grid gap-3 sm:grid-cols-2">
                @forelse($task->product->attributeValues->groupBy('attribute_id') as $values)
                    <div class="rounded-lg bg-slate-50 p-3">
                        <dt class="text-xs text-slate-500">{{ $values->first()->attribute?->name ?? 'ویژگی' }}</dt>
                        <dd class="mt-1 break-words text-sm text-slate-900">{{ $values->pluck('value')->implode('، ') }}</dd>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">ویژگی‌ای برای محصول ثبت نشده است.</p>
                @endforelse
            </dl>
        </section>
        @foreach(['short_description' => 'توضیحات کوتاه فعلی', 'full_description' => 'توضیحات کامل فعلی'] as $field => $label)
            <section>
                <h3 class="mb-2 text-sm font-semibold text-slate-800">{{ $label }}</h3>
                <div class="max-h-64 overflow-y-auto whitespace-pre-wrap break-words rounded-lg bg-slate-50 p-3 text-sm leading-8 text-slate-700">{{ trim(html_entity_decode(strip_tags((string) $task->product->{$field}), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?: 'توضیحاتی ثبت نشده است.' }}</div>
            </section>
        @endforeach
    </div>
</details>
