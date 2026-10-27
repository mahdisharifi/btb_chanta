<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ProductCompletionMediaStatus;
use App\Enums\ProductCompletionSubmissionStatus;
use App\Enums\ProductCompletionTaskStatus;
use App\Exceptions\ProductCompletionConflictException;
use App\Exceptions\ProductCompletionMediaException;
use App\Models\Product;
use App\Models\ProductCompletionMedia;
use App\Models\ProductCompletionSubmission;
use App\Models\ProductCompletionSubmissionMedia;
use App\Models\ProductCompletionTask;
use App\Models\ProductGallery;
use App\Models\User;
use App\Support\ProductCompletionPayload;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Throwable;

class ProductCompletionMediaService
{
    public function __construct(
        private readonly ProductImageService $imageService,
        private readonly ProductCompletionConflictService $conflictService,
        private readonly ProductCompletionInputSanitizer $sanitizer,
        private readonly ProductCompletionAuditService $auditService,
    ) {
    }

    public function stage(
        User $actor,
        ProductCompletionTask $task,
        UploadedFile $file,
    ): ProductCompletionMedia {
        Gate::forUser($actor)->authorize('manageMedia', $task);
        $rateLimitKey = "product-completion:media:{$actor->getKey()}:{$task->getKey()}";
        $maxAttempts = (int) config('product_completion.rate_limits.media_uploads_per_minute', 20);

        if (RateLimiter::tooManyAttempts($rateLimitKey, $maxAttempts)) {
            throw ValidationException::withMessages([
                'file' => 'تعداد بارگذاری‌ها بیش از حد مجاز است؛ یک دقیقه بعد دوباره تلاش کنید.',
            ]);
        }

        RateLimiter::hit($rateLimitKey, 60);
        $metadata = $this->inspectUpload($file);
        $disk = (string) config('product_completion.media.staging_disk', 'local');
        $directory = trim((string) config('product_completion.media.staging_directory', 'product-completion'), '/');
        $safeName = Str::ulid().'.'.$metadata['extension'];
        $temporaryPath = "{$directory}/{$task->public_id}/originals/{$safeName}";
        $contents = file_get_contents($file->getRealPath());

        if ($contents === false || ! Storage::disk($disk)->put($temporaryPath, $contents)) {
            throw new ProductCompletionMediaException('ذخیره فایل در فضای موقت ناموفق بود.');
        }

        try {
            return DB::transaction(function () use (
                $actor,
                $task,
                $file,
                $metadata,
                $disk,
                $safeName,
                $temporaryPath,
            ): ProductCompletionMedia {
                $lockedTask = ProductCompletionTask::query()->lockForUpdate()->findOrFail($task->getKey());
                Gate::forUser($actor)->authorize('manageMedia', $lockedTask);

                $count = ProductCompletionMedia::query()
                    ->where('task_id', $lockedTask->getKey())
                    ->whereNotIn('status', [
                        ProductCompletionMediaStatus::REJECTED->value,
                        ProductCompletionMediaStatus::DELETED->value,
                    ])
                    ->count();

                if ($count >= (int) config('product_completion.media.max_images', 10)) {
                    throw ValidationException::withMessages([
                        'file' => 'حداکثر تعداد تصویر مجاز برای این تسک تکمیل شده است.',
                    ]);
                }

                if (ProductCompletionMedia::query()
                    ->where('task_id', $lockedTask->getKey())
                    ->where('checksum', $metadata['checksum'])
                    ->withTrashed()
                    ->exists()) {
                    throw ValidationException::withMessages([
                        'file' => 'این تصویر قبلاً در تسک بارگذاری شده است.',
                    ]);
                }

                $media = ProductCompletionMedia::query()->create([
                    'task_id' => $lockedTask->getKey(),
                    'uploaded_by' => $actor->getKey(),
                    'disk' => $disk,
                    'temporary_path' => $temporaryPath,
                    'original_name' => $this->sanitizer->plainText(
                        basename($file->getClientOriginalName()),
                        255
                    ) ?? 'image.'.$metadata['extension'],
                    'safe_name' => $safeName,
                    'mime_type' => $metadata['mime_type'],
                    'extension' => $metadata['extension'],
                    'file_size' => $metadata['file_size'],
                    'width' => $metadata['width'],
                    'height' => $metadata['height'],
                    'checksum' => $metadata['checksum'],
                    'metadata_json' => [
                        'verified_mime_type' => $metadata['mime_type'],
                        'client_extension' => mb_strtolower($file->getClientOriginalExtension()),
                    ],
                    'status' => ProductCompletionMediaStatus::TEMPORARY,
                ]);

                $this->auditService->record(
                    action: 'completion_media_staged',
                    task: $lockedTask,
                    actor: $actor,
                    metadata: [
                        'media_id' => $media->getKey(),
                        'mime_type' => $media->mime_type,
                        'file_size' => $media->file_size,
                        'width' => $media->width,
                        'height' => $media->height,
                    ],
                    entity: $media,
                );

                return $media;
            }, 3);
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete($temporaryPath);
            throw $exception;
        }
    }

    public function freezeManifest(
        ProductCompletionSubmission $submission,
        ?array $manifest,
    ): void {
        $task = $submission->task()->firstOrFail();
        $galleryScope = data_get($task->scope_json, 'gallery', false);

        if ($galleryScope === false) {
            return;
        }

        $items = $manifest['items'] ?? null;

        if (! is_array($items)) {
            throw ValidationException::withMessages([
                'gallery.items' => 'فهرست تصاویر برای ارسال نهایی الزامی است.',
            ]);
        }

        $normalized = $this->validateManifest($task, $items, (array) $galleryScope);

        foreach ($normalized as $item) {
            ProductCompletionSubmissionMedia::query()->create([
                'submission_id' => $submission->getKey(),
                'media_id' => $item['media_id'],
                'product_gallery_id' => $item['product_gallery_id'],
                'action' => $item['action'],
                'sort_order' => $item['sort_order'],
                'is_primary' => $item['is_primary'],
                'title' => $item['title'],
                'alt_text' => $item['alt_text'],
                'review_status' => 'pending',
            ]);

            if ($item['media_id'] !== null) {
                ProductCompletionMedia::query()
                    ->whereKey($item['media_id'])
                    ->where('status', ProductCompletionMediaStatus::TEMPORARY)
                    ->update(['status' => ProductCompletionMediaStatus::UNDER_REVIEW]);
            }
        }
    }

    public function prepareApprovedMedia(ProductCompletionTask $task): void
    {
        $task = ProductCompletionTask::query()->findOrFail($task->getKey());

        if ($task->media_published_at !== null) {
            return;
        }

        if ($task->status !== ProductCompletionTaskStatus::APPROVED) {
            throw new ProductCompletionMediaException('رسانه فقط پس از تأیید Submission قابل پردازش است.');
        }

        $submission = ProductCompletionSubmission::query()->findOrFail($task->last_submission_id);

        $approvedFields = data_get($submission->approval_selection_json, 'approved_fields');
        if (is_array($approvedFields) && $approvedFields !== [] && ! in_array('gallery', $approvedFields, true)) {
            return;
        }

        if ($submission->status !== ProductCompletionSubmissionStatus::APPROVED) {
            throw new ProductCompletionMediaException('Submission نهایی تأیید نشده است.');
        }

        $mediaItems = $this->reviewedMediaItems($submission)->where('action', 'add');

        foreach ($mediaItems as $item) {
            $media = $item->media;

            if ($media === null || in_array($media->status, [
                ProductCompletionMediaStatus::ACCEPTED,
                ProductCompletionMediaStatus::PUBLISHED,
            ], true)) {
                continue;
            }

            $sourcePath = Storage::disk($media->disk)->path($media->temporary_path);
            $processed = null;

            try {
                $processed = $this->imageService->process(
                    sourcePath: $sourcePath,
                    slug: 'completion',
                    index: (int) $media->getKey(),
                    withPdf: true,
                    baseName: pathinfo($media->safe_name, PATHINFO_FILENAME),
                );

                $media->forceFill([
                    'prepared_path' => $processed['file_path'],
                    'final_path' => $processed['file_path'],
                    'file_size' => $processed['file_size'],
                    'width' => $processed['width'],
                    'height' => $processed['height'],
                    'versions_json' => $processed['versions'],
                    'status' => ProductCompletionMediaStatus::ACCEPTED,
                    'prepared_at' => now(),
                ])->save();
            } catch (Throwable $exception) {
                if (is_array($processed)) {
                    $this->imageService->deleteProcessed($processed);
                }

                report($exception);
                throw new ProductCompletionMediaException('پردازش تصویر تأییدشده ناموفق بود.', 0, $exception);
            }
        }
    }

    public function publishApprovedMedia(ProductCompletionTask $task): void
    {
        $result = DB::transaction(function () use ($task): array {
            $lockedTask = ProductCompletionTask::query()->lockForUpdate()->findOrFail($task->getKey());
            $product = Product::query()->lockForUpdate()->findOrFail($lockedTask->product_id);

            if ($lockedTask->media_published_at !== null) {
                return ['conflicts' => []];
            }

            if ($lockedTask->status !== ProductCompletionTaskStatus::APPROVED
                || $lockedTask->content_applied_at === null) {
                throw new ProductCompletionMediaException('ابتدا محتوای تأییدشده باید با موفقیت اعمال شود.');
            }

            $submission = ProductCompletionSubmission::query()
                ->lockForUpdate()
                ->findOrFail($lockedTask->last_submission_id);
            $approvedFields = data_get($submission->approval_selection_json, 'approved_fields');
            if (is_array($approvedFields) && $approvedFields !== [] && ! in_array('gallery', $approvedFields, true)) {
                $lockedTask->forceFill(['media_published_at' => now(), 'lock_version' => $lockedTask->lock_version + 1])->save();
                return ['conflicts' => []];
            }
            $conflict = $this->conflictService->detect($lockedTask, $product, $submission->publicationPayload());

            if ($conflict->hasConflicts()) {
                $submission->forceFill(['status' => ProductCompletionSubmissionStatus::CONFLICTED])->save();
                $lockedTask->forceFill([
                    'status' => ProductCompletionTaskStatus::CONFLICTED,
                    'conflict_json' => $conflict->conflicts,
                    'lock_version' => $lockedTask->lock_version + 1,
                ])->save();
                $this->auditService->record(
                    action: 'media_publication_conflict_detected',
                    task: $lockedTask,
                    metadata: ['fields' => array_keys($conflict->conflicts)],
                    entity: $submission,
                );

                return ['conflicts' => $conflict->conflicts];
            }

            $publicationPayload = $submission->publicationPayload();
            $galleryAlreadyCurrent = data_get($submission->approval_selection_json, 'schema_version') === 2
                && ($conflict->mergedPayload['gallery'] ?? null) != ($publicationPayload['gallery'] ?? null);
            if (data_get($lockedTask->scope_json, 'gallery', false) !== false && ! $galleryAlreadyCurrent) {
                $this->applyManifest($lockedTask, $product, $submission);
                $product->forceFill([
                    'content_version' => (int) $product->content_version + 1,
                    'content_updated_at' => now(),
                ])->save();
            }

            $lockedTask->forceFill([
                'media_published_at' => now(),
                'applied_product_version' => $product->content_version,
                'lock_version' => $lockedTask->lock_version + 1,
            ])->save();
            $this->auditService->record(
                action: 'approved_media_published',
                task: $lockedTask,
                metadata: [
                    'submission_id' => $submission->getKey(),
                    'product_version' => $product->content_version,
                ],
                entity: $product,
            );

            return ['conflicts' => []];
        }, 3);

        if ($result['conflicts'] !== []) {
            throw new ProductCompletionConflictException($result['conflicts']);
        }
    }

    public function deleteTemporary(User $actor, ProductCompletionMedia $media): void
    {
        Gate::forUser($actor)->authorize('delete', $media);

        $file = DB::transaction(function () use ($actor, $media): array {
            $locked = ProductCompletionMedia::query()->lockForUpdate()->findOrFail($media->getKey());
            Gate::forUser($actor)->authorize('delete', $locked);
            $file = ['disk' => $locked->disk, 'path' => $locked->temporary_path];
            $locked->forceFill(['status' => ProductCompletionMediaStatus::DELETED])->save();
            $locked->delete();
            $this->auditService->record(
                action: 'completion_media_deleted',
                task: $locked->task,
                actor: $actor,
                metadata: ['media_id' => $locked->getKey()],
                entity: $locked,
            );

            return $file;
        }, 3);

        if ($file['path'] !== null) {
            Storage::disk($file['disk'])->delete($file['path']);
        }
    }

    public function cleanup(ProductCompletionMedia $media): void
    {
        if ($media->status === ProductCompletionMediaStatus::PUBLISHED) {
            return;
        }

        if ($media->temporary_path !== null) {
            Storage::disk($media->disk)->delete($media->temporary_path);
        }

        if ($media->final_path !== null) {
            $this->imageService->deleteProcessed([
                'file_path' => $media->final_path,
                'versions' => $media->versions_json ?? [],
            ]);
        }

        $media->forceFill(['status' => ProductCompletionMediaStatus::DELETED])->save();
        $media->delete();
    }

    private function inspectUpload(UploadedFile $file): array
    {
        $originalName = basename((string) $file->getClientOriginalName()) ?: 'نامشخص';
        $allowedExtensions = implode('، ', array_map('strtoupper', config('product_completion.media.allowed_extensions', ['jpg', 'jpeg', 'png', 'webp'])));
        $maxKilobytes = (int) config('product_completion.media.max_file_size_kb', 2048);
        $maxMegabytes = rtrim(rtrim(number_format($maxKilobytes / 1024, 2, '.', ''), '0'), '.');

        if (! $file->isValid()) {
            throw ValidationException::withMessages([
                'upload' => "فایل «{$originalName}» کامل بارگذاری نشده یا در انتقال آن خطا رخ داده است. دوباره تلاش کنید.",
            ]);
        }

        $realPath = $file->getRealPath();
        $fileSize = (int) $file->getSize();
        $maxBytes = $maxKilobytes * 1024;

        if ($fileSize <= 0) {
            throw ValidationException::withMessages([
                'upload' => "فایل «{$originalName}» خالی است و محتوای آن خوانده نمی‌شود.",
            ]);
        }

        if ($fileSize > $maxBytes) {
            throw ValidationException::withMessages([
                'upload' => "فایل «{$originalName}» با حجم ".number_format($fileSize / 1024 / 1024, 2, '.', '')." مگابایت (".number_format($fileSize)." بایت) است؛ حداکثر حجم مجاز {$maxMegabytes} مگابایت (".number_format($maxBytes)." بایت) است.",
            ]);
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = (string) $finfo->file($realPath);
        $allowedMimeTypes = config('product_completion.media.allowed_mime_types', []);
        $actualMime = $mimeType ?: 'نامشخص';

        if (! in_array($mimeType, $allowedMimeTypes, true)) {
            throw ValidationException::withMessages([
                'upload' => "فایل «{$originalName}» از نوع واقعی «{$actualMime}» است؛ فقط فایل‌های تصویری با فرمت‌های {$allowedExtensions} مجاز هستند.",
            ]);
        }

        $dimensions = @getimagesize($realPath);
        if ($dimensions === false) {
            throw ValidationException::withMessages([
                'upload' => "فایل «{$originalName}» تصویر قابل خواندن نیست؛ یک فایل سالم با فرمت‌های {$allowedExtensions} انتخاب کنید.",
            ]);
        }

        $width = (int) $dimensions[0];
        $height = (int) $dimensions[1];
        $pixels = $width * $height;
        $minWidth = (int) config('product_completion.media.min_width', 800);
        $minHeight = (int) config('product_completion.media.min_height', 800);
        $maxWidth = (int) config('product_completion.media.max_width', 8000);
        $maxHeight = (int) config('product_completion.media.max_height', 8000);
        $maxPixels = (int) config('product_completion.media.max_pixels', 40000000);

        $violatedRules = [];
        if ($width < $minWidth || $height < $minHeight) {
            $violatedRules[] = "حداقل ابعاد مجاز {$minWidth}×{$minHeight} پیکسل (عرض×ارتفاع) است";
        }
        if ($width > $maxWidth || $height > $maxHeight) {
            $violatedRules[] = "حداکثر ابعاد مجاز {$maxWidth}×{$maxHeight} پیکسل (عرض×ارتفاع) است";
        }
        if ($pixels > $maxPixels) {
            $violatedRules[] = "حداکثر مجموع پیکسل‌ها ".number_format($maxPixels)." پیکسل است";
        }

        if ($violatedRules !== []) {
            throw ValidationException::withMessages([
                'upload' => "قانون تصویر رعایت نشده است: ".implode('؛ ', $violatedRules).". مشخصات فایل «{$originalName}»: {$width}×{$height} پیکسل، ".number_format($pixels)." پیکسل در مجموع.",
            ]);
        }

        $extension = match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => throw new ProductCompletionMediaException('فرمت تصویر پشتیبانی نمی‌شود.'),
        };

        return [
            'mime_type' => $mimeType,
            'extension' => $extension,
            'file_size' => $fileSize,
            'width' => $width,
            'height' => $height,
            'checksum' => hash_file('sha256', $realPath),
        ];
    }

    private function validateManifest(
        ProductCompletionTask $task,
        array $items,
        array $galleryScope,
    ): array {
        $normalized = [];
        $finalItems = [];
        $errors = [];
        $automaticPrimary = app(ProductCompletionPrimaryImage::class)->apply($task, ['items' => array_values($items)]);
        $allowedKeys = [
            'media_public_id', 'product_gallery_id', 'action', 'sort_order',
            'is_primary', 'title', 'alt_text',
        ];

        foreach (array_values(ProductCompletionPayload::withoutExcludedFields($items)) as $index => $item) {
            if (! is_array($item) || array_diff(array_keys($item), $allowedKeys) !== []) {
                $errors["gallery.items.{$index}"] = 'ساختار اطلاعات تصویر نامعتبر است.';
                continue;
            }

            $action = (string) ($item['action'] ?? '');
            if (! in_array($action, ['add', 'keep', 'remove'], true)) {
                $errors["gallery.items.{$index}.action"] = 'عملیات تصویر نامعتبر است.';
                continue;
            }

            if ($action === 'add' && ! ($galleryScope['add'] ?? false)) {
                $errors["gallery.items.{$index}.action"] = 'افزودن تصویر خارج از محدوده تسک است.';
                continue;
            }

            if ($action === 'remove' && ! ($galleryScope['propose_removal'] ?? false)) {
                $errors["gallery.items.{$index}.action"] = 'حذف تصویر خارج از محدوده تسک است.';
                continue;
            }

            $media = null;
            $gallery = null;

            if ($action === 'add') {
                $media = ProductCompletionMedia::query()
                    ->where('task_id', $task->getKey())
                    ->where('public_id', $item['media_public_id'] ?? '')
                    ->whereIn('status', [
                        ProductCompletionMediaStatus::TEMPORARY,
                        ProductCompletionMediaStatus::UNDER_REVIEW,
                        ProductCompletionMediaStatus::ACCEPTED,
                    ])
                    ->first();

                if ($media === null) {
                    $errors["gallery.items.{$index}.media_public_id"] = 'تصویر موقت معتبر نیست.';
                    continue;
                }
            } else {
                $gallery = ProductGallery::query()
                    ->where('product_id', $task->product_id)
                    ->where('file_type', 'image')
                    ->find((int) ($item['product_gallery_id'] ?? 0));

                if ($gallery === null) {
                    $errors["gallery.items.{$index}.product_gallery_id"] = 'تصویر محصول معتبر نیست.';
                    continue;
                }
            }

            $requestedOrder = $action === 'remove' ? null : (int) ($item['sort_order'] ?? 0);
            $requestedPrimary = $action !== 'remove' && (bool) ($item['is_primary'] ?? false);

            if ($action === 'keep' && $gallery !== null) {
                if ($requestedOrder !== (int) $gallery->sort_order && ! ($galleryScope['reorder'] ?? false)) {
                    $errors["gallery.items.{$index}.sort_order"] = 'تغییر ترتیب تصاویر خارج از محدوده تسک است.';
                }

            }

            if ($requestedPrimary !== (bool) data_get($automaticPrimary, "items.{$index}.is_primary", false)) {
                $errors["gallery.items.{$index}.is_primary"] = 'تصویر اصلی به‌صورت خودکار تعیین می‌شود و قابل انتخاب نیست.';
            }

            $normalizedItem = [
                'media_id' => $media?->getKey(),
                'product_gallery_id' => $gallery?->getKey(),
                'action' => $action,
                'sort_order' => $requestedOrder,
                'is_primary' => $requestedPrimary,
                'title' => $this->sanitizer->plainText($item['title'] ?? null, 255),
                'alt_text' => $this->sanitizer->plainText($item['alt_text'] ?? null, 255),
            ];
            $normalized[] = $normalizedItem;

            if ($action !== 'remove') {
                $finalItems[] = $normalizedItem;
            }
        }

        $maxImages = (int) config('product_completion.media.max_images', 10);
        if (count($finalItems) < 1 || count($finalItems) > $maxImages) {
            $errors['gallery.items'] = "تعداد تصاویر نهایی باید بین یک تا {$maxImages} باشد.";
        }

        $orders = array_column($finalItems, 'sort_order');
        sort($orders);
        if ($orders !== range(1, count($finalItems))) {
            $errors['gallery.sort_order'] = 'ترتیب تصاویر باید یکتا و پیوسته باشد.';
        }

        if (count(array_filter($finalItems, static fn (array $item): bool => $item['is_primary'])) !== 1) {
            $errors['gallery.is_primary'] = 'دقیقاً یک تصویر اصلی باید انتخاب شود.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $normalized;
    }

    private function applyManifest(
        ProductCompletionTask $task,
        Product $product,
        ProductCompletionSubmission $submission,
    ): void {
        $items = $this->reviewedMediaItems($submission);
        $product->galleries()->where('file_type', 'image')->update([
            'is_primary' => false,
        ]);

        foreach ($items as $item) {
            if ($item->action === 'remove') {
                $item->productGallery?->delete();
                continue;
            }

            if ($item->action === 'keep') {
                $item->productGallery?->update([
                    'sort_order' => $item->sort_order,
                    'is_primary' => $item->is_primary,
                    'title' => $item->title,
                    'alt_text' => $item->alt_text,
                ]);
                continue;
            }

            $media = $item->media;
            if ($media === null || $media->status !== ProductCompletionMediaStatus::ACCEPTED) {
                throw new ProductCompletionMediaException('حداقل یک تصویر جدید هنوز آماده انتشار نیست.');
            }

            $gallery = $product->galleries()->create([
                'base_name' => pathinfo($media->safe_name, PATHINFO_FILENAME),
                'file_name' => basename($media->final_path),
                'file_path' => $media->final_path,
                'file_type' => 'image',
                'title' => $item->title,
                'alt_text' => $item->alt_text,
                'sort_order' => $item->sort_order,
                'is_primary' => $item->is_primary,
                'file_size' => $media->file_size,
                'mime_type' => 'image/webp',
                'width' => $media->width,
                'height' => $media->height,
                'versions' => $media->versions_json,
            ]);
            $media->forceFill([
                'published_product_gallery_id' => $gallery->getKey(),
                'status' => ProductCompletionMediaStatus::PUBLISHED,
                'published_at' => now(),
            ])->save();
        }
    }

    private function reviewedMediaItems(ProductCompletionSubmission $submission): \Illuminate\Support\Collection
    {
        $items = $submission->mediaItems()->with(['media', 'productGallery'])->get();
        $snapshot = $submission->approval_selection_json;

        if (($snapshot['schema_version'] ?? null) !== 2) {
            return $items;
        }

        $submission->publicationPayload();
        if (! is_array($snapshot['media_items'] ?? null)) {
            throw ValidationException::withMessages(['submission' => 'تصمیم‌های بررسی تصاویر ناقص است.']);
        }

        $originals = $items->keyBy('id');
        $resolved = collect();
        $seen = [];
        foreach ($snapshot['media_items'] as $overlay) {
            $identity = (int) ($overlay['id'] ?? 0);
            $original = $originals->get($identity);
            if ($original === null || isset($seen[$identity])
                || ! in_array($overlay['action'] ?? null, [$original->action, 'keep'], true)
                || ($original->action === 'add' && ($overlay['action'] ?? null) !== 'add')) {
                throw ValidationException::withMessages(['submission' => 'تصمیم بررسی تصویر معتبر نیست.']);
            }
            $seen[$identity] = true;
            $item = clone $original;
            $item->forceFill(array_intersect_key($overlay, array_flip([
                'action', 'sort_order', 'is_primary', 'title', 'alt_text',
            ])));
            $resolved->push($item);
        }

        foreach ($items as $original) {
            if ($original->action !== 'add' && ! isset($seen[$original->getKey()])) {
                throw ValidationException::withMessages(['submission' => 'تصویر موجود نباید از نتیجه بررسی حذف شود.']);
            }
        }

        return $resolved;
    }
}
