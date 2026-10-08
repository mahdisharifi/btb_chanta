<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ProductCompletionTask;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Throwable;

class ProductCompletionScopeGuard
{
    public function __construct(private readonly ProductCompletionAuditService $auditService)
    {
    }

    /**
     * Reject forbidden keys and return only canonical draft fields.
     */
    public function filter(ProductCompletionTask $task, array $payload, ?User $actor = null): array
    {
        $violations = [];
        $paths = $this->collectPaths($payload);
        $neverAllowed = array_map(
            static fn (string $field): string => mb_strtolower($field),
            config('product_completion.never_allowed_fields', [])
        );
        $draftFields = config('product_completion.draft_fields', []);

        foreach ($paths as $path) {
            $segments = explode('.', $path);
            $root = $segments[0];
            $normalizedSegments = array_map('mb_strtolower', $segments);

            if (array_intersect($normalizedSegments, $neverAllowed) !== []) {
                $violations[] = $path;

                continue;
            }

            if (! in_array($root, $draftFields, true)) {
                $violations[] = $path;

                continue;
            }

            if ($root === 'schema_version') {
                continue;
            }

            $scope = data_get($task->scope_json, $root, false);

            if ($scope !== true && ! is_array($scope)) {
                $violations[] = $path;

                continue;
            }

            if ($root === 'seo' && count($segments) > 1) {
                $seoField = $segments[1];

                if (data_get($scope, $seoField, false) !== true) {
                    $violations[] = $path;
                }
            }
        }

        if ($violations !== []) {
            try {
                $this->auditService->forbiddenScopeAttempt($task, $actor, $violations);
            } catch (Throwable $exception) {
                report($exception);
            }

            throw ValidationException::withMessages([
                'draft' => 'تلاش برای تغییر فیلد خارج از محدوده مجاز تسک شناسایی شد.',
                'draft.scope' => 'فیلدهای غیرمجاز: '.implode('، ', array_values(array_unique($violations))),
            ]);
        }

        $filtered = Arr::only($payload, $draftFields);
        $filtered['schema_version'] = (int) config('product_completion.schema_version', 1);

        return $filtered;
    }

    private function collectPaths(array $payload, string $prefix = ''): array
    {
        $paths = [];

        foreach ($payload as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            $paths[] = $path;

            if (is_array($value)) {
                $paths = array_merge($paths, $this->collectPaths($value, $path));
            }
        }

        return $paths;
    }
}
