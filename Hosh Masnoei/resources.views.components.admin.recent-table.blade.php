<div class="overflow-x-auto">
    <table class="w-full min-w-full divide-y divide-gray-200 dark:divide-gray-700">
        <thead class="bg-gray-50 dark:bg-gray-800">
        {{-- محتوای هدر از اسلات head خوانده می‌شود --}}
        {{ $head ?? '' }}
        </thead>
        <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-900 dark:divide-gray-700">
        {{-- محتوای بدنه از اسلات body خوانده می‌شود --}}
        {{ $body }}
        </tbody>
    </table>
</div>
