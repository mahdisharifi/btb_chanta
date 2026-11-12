@if($formStep === 2 && $firstFormStepSaved)
    <div role="status" class="mb-6 rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-900 dark:border-green-800 dark:bg-green-900/20 dark:text-green-200">
        <p>محصول ذخیره شد. مرحلهٔ دوم اختیاری است؛ ویژگی‌های الزامی هم مانع رد کردن این مرحله نمی‌شوند.</p>
        @if($supplierDraftMode || $this instanceof \App\Livewire\Supplier\Products\Edit)
            <p class="mt-1">می‌توانید اکنون قیمت‌ها را ثبت کنید و سپس خروجی کاتالوگ بگیرید.</p>
            <div class="mt-3 flex flex-wrap gap-4">
                <a href="#" wire:click.prevent="goToPricingStep" class="font-medium underline">رد کردن مرحلهٔ دوم و قیمت‌گذاری</a>
                <a href="{{ route('supplier.catalog.index') }}" wire:navigate class="underline">کاتالوگ محصولات</a>
            </div>
        @else
            <a href="{{ route('admin.products.index') }}" wire:navigate class="mt-3 inline-block font-medium underline">رد کردن مرحلهٔ دوم و بازگشت به محصولات</a>
        @endif
    </div>
@endif
