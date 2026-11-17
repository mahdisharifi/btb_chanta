<x-layouts.admin>
    <x-slot:header>استودیوی تصویر محصول</x-slot:header>

    <div class="-mx-2 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm sm:-mx-3">
        <iframe
            title="استودیوی تصویر محصول"
            src="{{ route('admin.ai-image-studio.app') }}"
            class="block w-full border-0"
            style="height: min(1100px, calc(100vh - 170px)); min-height: 760px;"
            referrerpolicy="same-origin"
        ></iframe>
    </div>
</x-layouts.admin>
