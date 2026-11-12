<div class="mt-5 space-y-4 border-t border-gray-200 pt-4 dark:border-gray-700">
    @foreach($product->galleries()->where('file_type', 'video')->get() as $video)
        <video controls preload="metadata" class="max-h-64 w-full rounded-lg" @if($video->poster_path) poster="{{ asset('storage/'.$video->poster_path) }}" @endif>
            <source src="{{ asset('storage/'.$video->file_path) }}" type="{{ $video->mime_type }}">
        </video>
    @endforeach
    <div>
        <label for="edit-product-video" class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">افزودن ویدئوی محصول (اختیاری)</label>
        <input id="edit-product-video" type="file" wire:model="product_video" accept="video/mp4,video/webm,video/ogg,video/quicktime" class="block w-full text-sm text-gray-600 dark:text-gray-300">
        <p class="mt-1 text-xs text-gray-500">حداکثر ۵۰ مگابایت</p>
        @error('product_video') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
    </div>
    @if($product_video)
        <div>
            <label for="edit-video-poster" class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">تصویر بندانگشتی ویدئو (اختیاری)</label>
            <input id="edit-video-poster" type="file" wire:model="video_poster" accept="image/jpeg,image/png,image/webp" class="block w-full text-sm text-gray-600 dark:text-gray-300">
            @error('video_poster') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
        </div>
    @endif
    <p wire:loading wire:target="product_video, video_poster" class="text-xs text-indigo-600">در حال بارگذاری…</p>
</div>
