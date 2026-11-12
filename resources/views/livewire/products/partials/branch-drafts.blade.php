<section class="mb-6 rounded-xl border border-amber-200 bg-white p-4" aria-label="پیش‌نویس‌های شعب">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h2 class="font-bold">مدیریت پیش‌نویس‌های تمام شعب</h2>
        <select wire:model.live="draftBranchId" class="rounded-lg border-gray-300 text-sm" aria-label="فیلتر شعبه">
            <option value="">تمام شعب</option>
            @foreach($draftBranches as $draftBranch)
                <option value="{{ $draftBranch->id }}">{{ $draftBranch->branchLabel() }}{{ !$draftBranch->is_active ? ' (غیرفعال)' : '' }}</option>
            @endforeach
        </select>
    </div>
    <p class="mb-3 text-sm text-gray-500">با انتخاب ویرایش یا قیمت‌گذاری، شعبهٔ مالک محصول فعال می‌شود. پیش‌نویس‌های شعب غیرفعال فقط نمایش داده می‌شوند.</p>
    <div class="divide-y divide-gray-100">
        @forelse($draftProducts as $draft)
            <div wire:key="branch-draft-{{ $draft->id }}" class="flex flex-wrap items-center justify-between gap-3 py-3">
                <div><p class="font-medium">{{ $draft->title }}</p><p class="text-xs text-gray-500">پیش‌نویس · {{ $draft->supplier?->branchLabel() }}{{ !$draft->supplier?->is_active ? ' (غیرفعال)' : '' }}</p></div>
                @if($draft->supplier?->is_active)
                    <div class="flex gap-4 text-sm text-indigo-600">
                        <button type="button" wire:click="openDraftProduct({{ $draft->id }}, 1)" wire:loading.attr="disabled">ویرایش</button>
                        <button type="button" wire:click="openDraftProduct({{ $draft->id }}, 3)" wire:loading.attr="disabled">مرحلهٔ ۳: قیمت‌گذاری</button>
                    </div>
                @endif
            </div>
        @empty
            <p class="py-4 text-sm text-gray-500">پیش‌نویسی برای این فیلتر وجود ندارد.</p>
        @endforelse
    </div>
    {{ $draftProducts->links() }}
</section>
