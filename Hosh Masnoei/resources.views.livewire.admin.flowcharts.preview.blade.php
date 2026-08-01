<x-modal name="previewModal" title="پیش‌نمایش فلوچارت" wire:model="showModal">
    <div class="p-4 bg-white dark:bg-gray-800 rounded-lg overflow-auto flex justify-center min-h-[300px]"
         x-data="{ code: @entangle('code') }"
         x-init="$watch('code', value => window.renderMermaid('flowSvg', value)); if(code) window.renderMermaid('flowSvg', code);">

        <div id="flowSvg" class="w-full max-w-full flex justify-center items-center">
            <!-- SVG اینجا رندر می‌شود -->
        </div>
    </div>

    <div class="mt-6 flex justify-end">
        <x-secondary-button x-on:click="$dispatch('close-modal', 'previewModal')">بستن</x-secondary-button>
    </div>

</x-modal>
