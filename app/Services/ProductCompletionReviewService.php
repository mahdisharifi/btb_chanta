<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ProductCompletionMediaStatus;
use App\Enums\ProductCompletionSubmissionStatus;
use App\Models\Product;
use App\Models\ProductCompletionSubmission;
use App\Models\ProductCompletionTask;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ProductCompletionReviewService
{
    public function __construct(
        private readonly ProductCompletionDraftValidator $draftValidator,
        private readonly ProductCompletionSnapshotService $snapshotService,
        private readonly ProductCompletionStorageGuard $storageGuard,
        private readonly ProductCompletionAuditService $auditService,
        private readonly ProductCompletionPrimaryImage $primaryImage,
    ) {
    }

    public function initialize(ProductCompletionSubmission $submission): array
    {
        $payload = $submission->publicationPayload();
        $decisions = $this->defaultDecisions($submission, $submission->mediaItems()->orderBy('id')->get(), $payload);
        $stored = $submission->approval_selection_json;
        if (($stored['schema_version'] ?? null) === 2) {
            $payload = $stored['editor_payload'];
            $decisions = array_replace($decisions, $stored['decisions']);
        }

        return ['payload' => $payload, 'decisions' => $decisions];
    }

    public function save(
        User $actor,
        ProductCompletionSubmission $submission,
        int $expectedLockVersion,
        array $editedPayload,
        array $decisions,
    ): ProductCompletionSubmission {
        $this->assertSize($editedPayload, $decisions);
        $this->storageGuard->assertTransactional();

        return DB::transaction(function () use ($actor, $submission, $expectedLockVersion, $editedPayload, $decisions): ProductCompletionSubmission {
            $task = ProductCompletionTask::query()->lockForUpdate()->findOrFail($submission->task_id);
            $locked = ProductCompletionSubmission::query()->lockForUpdate()->findOrFail($submission->getKey());
            $product = Product::query()->lockForUpdate()->findOrFail($task->product_id);
            $locked->setRelation('task', $task);

            Gate::forUser($actor)->authorize('approveSubmission', $locked);
            abort_unless($task->reviewer_id === $actor->getKey(), 403);
            if ($locked->task_id !== $task->getKey() || $task->last_submission_id !== $locked->getKey()
                || ! $task->status->canBeReviewed() || $locked->status !== ProductCompletionSubmissionStatus::UNDER_REVIEW
                || $locked->review_started_at === null || $task->approved_at !== null
                || $task->content_applied_at !== null || $task->media_published_at !== null) {
                $this->invalid('submission');
            }
            if ($task->lock_version !== $expectedLockVersion) {
                $this->invalid('lock_version');
            }
            if ((int) data_get($task->initial_snapshot_json, 'category_id') !== (int) $product->category_id
                || ($task->supplier_id !== null && (int) $task->supplier_id !== (int) $product->supplier_id)) {
                $this->invalid('submission');
            }

            $editor = $this->initialize($locked)['payload'];
            $original = $locked->payload_json ?? [];
            if (($editedPayload['schema_version'] ?? null) !== ($original['schema_version'] ?? null)) {
                $this->invalid('schema_version');
            }
            if (array_key_exists('gallery', $editedPayload)
                && $editedPayload['gallery'] !== ($editor['gallery'] ?? null)
                && $editedPayload['gallery'] !== ($original['gallery'] ?? null)) {
                $this->invalid('gallery');
            }
            if (array_key_exists('gallery', $editor) && ! array_key_exists('gallery', $editedPayload)) {
                $this->invalid('gallery');
            }

            $edited = $this->draftValidator->validate($task, $editedPayload, $actor)->toArray();
            $rows = $locked->mediaItems()->orderBy('id')->lockForUpdate()->with(['media', 'productGallery'])->get();
            $allowed = $this->defaultDecisions($locked, $rows, $edited);
            $required = $this->defaultDecisions($locked, $rows, $editor);
            foreach (array_diff_key($allowed, $required) as $identity => $default) {
                $decisions[$identity] ??= $default;
            }
            if (array_diff_key($decisions, $allowed) !== [] || array_diff_key($allowed, $decisions) !== []) {
                $this->invalid('decisions');
            }
            foreach ($decisions as $decision) {
                if (! in_array($decision, ['approve', 'reject'], true)) {
                    $this->invalid('decisions');
                }
            }

            $baseline = $this->snapshotService->draftFromSnapshot($task->initial_snapshot_json ?? []);
            $pricingBaseline = $this->snapshotService->draftFromSnapshot($task->commission_content_snapshot_json ?? $task->initial_snapshot_json ?? []);
            $reviewed = $this->resolveContent($baseline, $edited, $decisions);
            $pricing = $this->resolveContent($pricingBaseline, $original, $decisions);
            $mediaItems = [];
            if ($this->scopeAllows($task, 'gallery')) {
                [$gallery, $mediaItems] = $this->resolveMedia($task, $rows, $decisions);
                $reviewed['gallery'] = $gallery;
                $pricing['gallery'] = $gallery;
            } elseif ($rows->isNotEmpty()) {
                $this->invalid('gallery');
            }

            $reviewed = $this->validatePublication($task, $locked, $reviewed, $actor, $decisions);
            $reviewed['schema_version'] = $original['schema_version'];
            $pricing['schema_version'] = $original['schema_version'];
            $before = $locked->approval_selection_json;
            $locked->approval_selection_json = ProductCompletionSubmission::signReviewSnapshot([
                'schema_version' => 2,
                'mode' => 'item_review',
                'editor_payload' => $edited,
                'reviewed_payload' => $reviewed,
                'pricing_payload' => $pricing,
                'media_items' => $mediaItems,
                'decisions' => $decisions,
                'reviewer_id' => $actor->getKey(),
                'reviewed_at' => now()->toAtomString(),
                'source_payload_hash' => $locked->payload_hash,
                'source_task_id' => (int) $locked->task_id,
                'source_submission_id' => (int) $locked->getKey(),
                'product_version' => (int) $product->content_version,
                'source_lock_version' => $expectedLockVersion,
            ]);
            $locked->publicationPayload();
            $locked->save();
            $task->forceFill(['lock_version' => $task->lock_version + 1])->save();
            $this->auditService->record(
                action: 'submission_review_saved',
                task: $task,
                actor: $actor,
                metadata: [
                    'submission_id' => $locked->getKey(),
                    'payload_hash' => $locked->payload_hash,
                    'original_payload' => $original,
                    'product_version' => $product->content_version,
                    'lock_version' => $task->lock_version,
                ],
                before: $before,
                after: $locked->approval_selection_json,
                entity: $locked,
            );

            return $locked;
        }, 3);
    }

    public function validatePublication(
        ProductCompletionTask $task,
        ProductCompletionSubmission $submission,
        array $resolvedPayload,
        ?User $actor = null,
        ?array $decisions = null,
    ): array {
        if ($submission->task_id !== $task->getKey()) {
            $this->invalid('submission');
        }
        if ($decisions === null) {
            $submission->publicationPayload();
            $decisions = $submission->approval_selection_json['decisions'] ?? [];
        }
        $actual = $this->draftValidator->validate($task, $resolvedPayload, $actor)->toArray();
        $required = $actual;
        $original = $submission->payload_json ?? [];
        foreach (['title', 'short_description', 'full_description'] as $field) {
            if (($decisions['text:'.$field] ?? null) === 'reject') {
                $required[$field] = $original[$field] ?? null;
            }
        }
        if (array_key_exists('attributes', $actual)) {
            $attributes = collect($actual['attributes'])->keyBy('attribute_id')->all();
            $originalAttributes = collect($original['attributes'] ?? [])->keyBy('attribute_id')->all();
            foreach ($decisions as $identity => $decision) {
                if ($decision !== 'reject' || ! str_starts_with($identity, 'attribute:')) {
                    continue;
                }
                $attributeId = (int) substr($identity, strlen('attribute:'));
                unset($attributes[$attributeId]);
                if (isset($originalAttributes[$attributeId])) {
                    $attributes[$attributeId] = $originalAttributes[$attributeId];
                }
            }
            $required['attributes'] = array_values($attributes);
        }
        $this->draftValidator->validate($task, $required, $actor, true);
        foreach (['title', 'short_description', 'full_description'] as $field) {
            if (($decisions['text:'.$field] ?? null) === 'reject' && array_key_exists($field, $resolvedPayload)) {
                $actual[$field] = $resolvedPayload[$field];
            }
        }

        return $actual;
    }

    private function defaultDecisions(ProductCompletionSubmission $submission, Collection $rows, array $edited): array
    {
        $task = $submission->task;
        $decisions = [];
        foreach (['title', 'short_description', 'full_description'] as $field) {
            if ($this->scopeAllows($task, $field)) {
                $decisions['text:'.$field] = 'approve';
            }
        }
        foreach ([$task->initial_snapshot_json ?? [], $task->commission_content_snapshot_json ?? [], $submission->payload_json ?? [], $edited] as $payload) {
            foreach ($payload['attributes'] ?? [] as $attribute) {
                $decisions['attribute:'.(int) $attribute['attribute_id']] = 'approve';
            }
        }
        foreach ($rows as $row) {
            $decisions['media:'.$row->getKey()] = 'approve';
        }
        foreach (($submission->approval_selection_json['decisions'] ?? []) as $identity => $decision) {
            if (str_starts_with($identity, 'attribute:')) {
                $decisions[$identity] = 'approve';
            }
        }

        return $decisions;
    }

    private function resolveContent(array $baseline, array $source, array $decisions): array
    {
        $resolved = $baseline;
        foreach (['title', 'short_description', 'full_description'] as $field) {
            if (($decisions['text:'.$field] ?? 'reject') === 'approve') {
                $resolved[$field] = $source[$field] ?? null;
            }
        }
        if (array_key_exists('attributes', $baseline) || array_key_exists('attributes', $source)) {
            $attributes = collect($baseline['attributes'] ?? [])->keyBy('attribute_id')->all();
            $incoming = collect($source['attributes'] ?? [])->keyBy('attribute_id')->all();
            foreach (array_unique([...array_keys($attributes), ...array_keys($incoming)]) as $attributeId) {
                if (($decisions['attribute:'.$attributeId] ?? 'reject') === 'approve') {
                    if (isset($incoming[$attributeId])) {
                        $attributes[$attributeId] = $incoming[$attributeId];
                    } else {
                        unset($attributes[$attributeId]);
                    }
                }
            }
            ksort($attributes);
            $resolved['attributes'] = array_values($attributes);
        }
        foreach ($resolved['attributes'] ?? [] as $index => $attribute) {
            $resolved['attributes'][$index]['value_ids'] = $attribute['value_ids'] ?? [];
            $resolved['attributes'][$index]['proposed_values'] = $attribute['proposed_values'] ?? [];
        }

        return $resolved;
    }

    private function resolveMedia(ProductCompletionTask $task, Collection $rows, array $decisions): array
    {
        $baseline = collect(data_get($task->initial_snapshot_json, 'gallery.items', []))->keyBy('product_gallery_id');
        $items = [];
        $seen = [];
        $galleryIds = [];
        foreach ($rows as $row) {
            $identity = $row->action === 'add' ? 'media:'.$row->media_id : 'gallery:'.$row->product_gallery_id;
            if (isset($seen[$identity]) || ! in_array($row->action, ['add', 'keep', 'remove'], true)) {
                $this->invalid('gallery');
            }
            $seen[$identity] = true;
            $approved = $decisions['media:'.$row->getKey()] === 'approve';
            if ($row->action === 'add') {
                if (! $approved) {
                    continue;
                }
                if (! data_get($task->scope_json, 'gallery.add', false) || $row->product_gallery_id !== null
                    || $row->media === null || (int) $row->media->task_id !== $task->getKey()
                    || ! in_array($row->media->status, [ProductCompletionMediaStatus::TEMPORARY, ProductCompletionMediaStatus::UNDER_REVIEW, ProductCompletionMediaStatus::ACCEPTED], true)) {
                    $this->invalid('gallery');
                }
                $reference = ['media_public_id' => $row->media->public_id, 'product_gallery_id' => null];
            } else {
                if ($row->media_id !== null || $row->productGallery === null
                    || (int) $row->productGallery->product_id !== $task->product_id || $row->productGallery->file_type !== 'image'
                    || ! $baseline->has($row->product_gallery_id)) {
                    $this->invalid('gallery');
                }
                $galleryIds[] = (int) $row->product_gallery_id;
                $reference = ['media_public_id' => null, 'product_gallery_id' => (int) $row->product_gallery_id];
            }
            $action = $approved ? $row->action : 'keep';
            if ($action === 'remove' && ! data_get($task->scope_json, 'gallery.propose_removal', false)) {
                $this->invalid('gallery');
            }
            $original = $baseline->get($row->product_gallery_id, []);
            $items[] = [
                'id' => (int) $row->getKey(),
                'action' => $action,
                'sort_order' => $action === 'remove' ? null : ($approved ? $row->sort_order : ($original['sort_order'] ?? null)),
                'is_primary' => false,
                'is_seo_image' => (bool) ($row->productGallery?->is_seo_image ?? $row->is_seo_image),
                'title' => $approved ? $row->title : ($original['title'] ?? null),
                'alt_text' => $approved ? $row->alt_text : ($original['alt_text'] ?? null),
                ...$reference,
            ];
        }
        if (array_diff($baseline->keys()->all(), $galleryIds) !== []) {
            $this->invalid('gallery');
        }
        usort($items, static fn (array $left, array $right): int => (($left['sort_order'] ?? PHP_INT_MAX) <=> ($right['sort_order'] ?? PHP_INT_MAX)) ?: ($left['id'] <=> $right['id']));
        $order = 0;
        foreach ($items as &$item) {
            if ($item['action'] !== 'remove') {
                $item['sort_order'] = ++$order;
            }
        }
        unset($item);
        if (($order < 1 && $baseline->isNotEmpty()) || $order > (int) config('product_completion.media.max_images', 10)) {
            $this->invalid('gallery');
        }
        $items = $this->primaryImage->apply($task, ['items' => $items])['items'];
        $overlays = [];
        $gallery = [];
        foreach ($items as $item) {
            $overlays[] = array_intersect_key($item, array_flip(['id', 'action', 'sort_order', 'is_primary', 'is_seo_image', 'title', 'alt_text']));
            $gallery[] = array_diff_key($item, array_flip(['id', 'is_seo_image']));
        }

        return [['items' => $gallery], $overlays];
    }

    private function assertSize(array $payload, array $decisions): void
    {
        try {
            $encoded = json_encode([$payload, $decisions], JSON_THROW_ON_ERROR, 32);
        } catch (\JsonException) {
            $this->invalid('review');
        }
        if (strlen($encoded) > 2097152 || count($decisions) > 1000) {
            $this->invalid('review');
        }
    }

    private function scopeAllows(ProductCompletionTask $task, string $field): bool
    {
        $scope = data_get($task->scope_json, $field, false);

        return $scope === true || is_array($scope);
    }

    private function invalid(string $field): never
    {
        $messages = [
            'submission' => 'این ارسال در وضعیت قابل ویرایش نیست یا اطلاعات محصول تغییر کرده است. صفحه را دوباره بارگذاری کنید.',
            'lock_version' => 'اطلاعات بازبینی تغییر کرده است. پیش از ذخیره، صفحه را دوباره بارگذاری کنید.',
            'schema_version' => 'تغییر نسخهٔ ساختار اطلاعات مجاز نیست.',
            'gallery' => 'تصاویر انتخاب‌شده یا تصمیم‌های بازبینی معتبر نیستند. تصاویر باید از همین ارسال باشند و گالری نهایی معتبر بماند.',
            'decisions' => 'برای موارد مجاز بازبینی، تصمیم تأیید یا رد معتبر ثبت کنید.',
            'review' => 'اطلاعات بازبینی نامعتبر است یا از حجم مجاز بیشتر است.',
        ];

        throw ValidationException::withMessages([$field => $messages[$field] ?? 'اطلاعات بازبینی معتبر نیست.']);
    }
}
