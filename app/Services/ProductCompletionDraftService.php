<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ProductCompletionDraftVersionConflictException;
use App\Models\ProductCompletionTask;
use App\Models\User;
use App\Support\ProductCompletionPayload;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ProductCompletionDraftService
{
    public function __construct(
        private readonly ProductCompletionDraftValidator $validator,
        private readonly ProductCompletionAuditService $auditService,
    ) {
    }

    public function save(
        User $actor,
        ProductCompletionTask $task,
        array $payload,
        int $expectedDraftVersion,
    ): ProductCompletionTask {
        Gate::forUser($actor)->authorize('updateDraft', $task);

        return DB::transaction(function () use ($actor, $task, $payload, $expectedDraftVersion): ProductCompletionTask {
            $locked = ProductCompletionTask::query()->lockForUpdate()->findOrFail($task->getKey());
            Gate::forUser($actor)->authorize('updateDraft', $locked);

            if ($locked->draft_version !== $expectedDraftVersion) {
                throw new ProductCompletionDraftVersionConflictException(
                    expected: $expectedDraftVersion,
                    actual: $locked->draft_version,
                );
            }

            $incoming = $this->validator->validate($locked, $payload, $actor)->toArray();
            $current = $locked->current_draft_json ?? [];
            $merged = $this->mergeDraft($current, $incoming);
            $changedFields = $this->changedFields($current, $merged);

            $locked->forceFill([
                'current_draft_json' => $merged,
                'draft_version' => $locked->draft_version + 1,
                'lock_version' => $locked->lock_version + 1,
            ])->save();

            $this->auditService->record(
                action: 'draft_updated',
                task: $locked,
                actor: $actor,
                metadata: [
                    'draft_version' => $locked->draft_version,
                    'changed_fields' => $changedFields,
                ],
                entity: $locked,
            );

            DB::afterCommit(static fn () => \App\Events\DraftUpdated::dispatch(
                $locked,
                $locked->draft_version,
            ));

            return $locked;
        }, 3);
    }

    private function mergeDraft(array $current, array $incoming): array
    {
        $current = ProductCompletionPayload::withoutExcludedFields($current);
        foreach ($incoming as $field => $value) {
            $current[$field] = $value;
        }

        $current['schema_version'] = (int) config('product_completion.schema_version', 1);

        return $current;
    }

    private function changedFields(array $before, array $after): array
    {
        $changed = [];

        foreach (array_unique(array_merge(array_keys($before), array_keys($after))) as $field) {
            if (($before[$field] ?? null) !== ($after[$field] ?? null)) {
                $changed[] = $field;
            }
        }

        return $changed;
    }
}
