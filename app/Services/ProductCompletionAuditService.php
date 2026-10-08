<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ProductCompletionAudit;
use App\Models\ProductCompletionTask;
use App\Models\User;
use Illuminate\Support\Str;

class ProductCompletionAuditService
{
    public function record(
        string $action,
        ?ProductCompletionTask $task = null,
        ?User $actor = null,
        array $metadata = [],
        ?array $before = null,
        ?array $after = null,
        ?string $fieldName = null,
        ?object $entity = null,
    ): ProductCompletionAudit {
        $request = app()->bound('request') ? request() : null;
        $requestId = $request?->headers->get('X-Request-ID');

        if (! is_string($requestId) || ! Str::isUuid($requestId)) {
            $requestId = (string) Str::uuid();
        }

        return ProductCompletionAudit::query()->create([
            'task_id' => $task?->getKey(),
            'product_id' => $task?->product_id,
            'user_id' => $actor?->getKey(),
            'actor_type' => $actor === null ? 'system' : 'user',
            'action' => $action,
            'entity_type' => $entity !== null ? $entity::class : null,
            'entity_id' => $entity !== null && method_exists($entity, 'getKey')
                ? $entity->getKey()
                : null,
            'field_name' => $fieldName,
            'before_json' => $before,
            'after_json' => $after,
            'metadata_json' => $metadata,
            'request_id' => $requestId,
            'ip_address' => $request?->ip(),
            'user_agent' => mb_substr(
                (string) ($request?->userAgent() ?? ''),
                0,
                (int) config('product_completion.audit.user_agent_max_length', 500)
            ) ?: null,
        ]);
    }

    public function forbiddenScopeAttempt(
        ProductCompletionTask $task,
        ?User $actor,
        array $fieldPaths,
    ): void {
        $this->record(
            action: 'forbidden_scope_mutation_attempt',
            task: $task,
            actor: $actor,
            metadata: ['field_paths' => array_values(array_unique($fieldPaths))],
        );
    }
}
