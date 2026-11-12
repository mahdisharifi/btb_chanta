<nav aria-label="مراحل ثبت محصول" class="mb-5 grid grid-cols-1 sm:grid-cols-3 gap-2 rounded-xl border border-gray-200 bg-white p-2 dark:border-gray-700 dark:bg-gray-800">
    @php($steps = [1 => 'رسانه و اطلاعات اصلی', 2 => 'تکمیل اطلاعات محصول'])
    @php($steps = ($supplierDraftMode || $this instanceof \App\Livewire\Supplier\Products\Edit) ? $steps + [3 => 'قیمت‌گذاری'] : $steps)
    @foreach($steps as $step => $label)
        <div @if($formStep === $step) aria-current="step" @endif
             class="flex items-center gap-2 rounded-lg px-3 py-3 text-xs font-medium sm:text-sm {{ $formStep === $step ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-200' : 'text-gray-500 dark:text-gray-400' }}">
            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full {{ $formStep === $step ? 'bg-indigo-600 text-white' : 'bg-gray-100 dark:bg-gray-700' }}">{{ $step }}</span>
            <span>{{ $label }}</span>
        </div>
    @endforeach
</nav>
