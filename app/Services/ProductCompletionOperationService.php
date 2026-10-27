<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ProductCompletionOperationStatus;
use App\Models\ProductCompletionMedia;
use App\Models\ProductCompletionOperation;
use App\Models\ProductCompletionTask;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class ProductCompletionOperationService
{
    public const FINALIZE_TASK = 'finalize_task';
    public const CLEANUP_MEDIA = 'cleanup_media';

    public function __construct(
        private readonly ProductCompletionFinalizationService $finalizationService,
        private readonly ProductCompletionMediaService $mediaService,
    ) {
    }

    public function enqueue(
        ProductCompletionTask $task,
        string $operationType,
        array $payload = [],
        ?string $idempotencyToken = null,
    ): ProductCompletionOperation {
        $key = hash('sha256', implode('|', [
            $task->getKey(),
            $operationType,
            $idempotencyToken ?? 'default',
        ]));

        return ProductCompletionOperation::query()->firstOrCreate(
            ['idempotency_key' => $key],
            [
                'task_id' => $task->getKey(),
                'operation_type' => $operationType,
                'status' => ProductCompletionOperationStatus::PENDING,
                'payload_json' => $payload,
                'attempts' => 0,
                'available_at' => now(),
            ]
        );
    }

    public function process(ProductCompletionOperation $operation): void
    {
        $operation = DB::transaction(function () use ($operation): ProductCompletionOperation {
            $locked = ProductCompletionOperation::query()
                ->lockForUpdate()
                ->findOrFail($operation->getKey());

            if ($locked->status === ProductCompletionOperationStatus::COMPLETED) {
                return $locked;
            }

            $locked->forceFill([
                'status' => ProductCompletionOperationStatus::PROCESSING,
                'attempts' => $locked->attempts + 1,
                'locked_at' => now(),
                'last_error' => null,
            ])->save();

            return $locked;
        }, 3);

        if ($operation->status === ProductCompletionOperationStatus::COMPLETED) {
            return;
        }

        try {
            $this->execute($operation);

            ProductCompletionOperation::query()
                ->whereKey($operation->getKey())
                ->update([
                    'status' => ProductCompletionOperationStatus::COMPLETED,
                    'completed_at' => now(),
                    'locked_at' => null,
                    'last_error' => null,
                ]);
        } catch (Throwable $exception) {
            ProductCompletionOperation::query()
                ->whereKey($operation->getKey())
                ->update([
                    'status' => ProductCompletionOperationStatus::FAILED,
                    'available_at' => now()->addMinutes(min(60, 2 ** $operation->attempts)),
                    'locked_at' => null,
                    'last_error' => mb_substr($exception->getMessage(), 0, 5000),
                ]);

            throw $exception;
        }
    }

    private function execute(ProductCompletionOperation $operation): void
    {
        $task = $operation->task()->firstOrFail();

        match ($operation->operation_type) {
            self::FINALIZE_TASK => $this->finalizationService->finalize($task),
            self::CLEANUP_MEDIA => $this->mediaService->cleanup(
                ProductCompletionMedia::query()->findOrFail(
                    (int) data_get($operation->payload_json, 'media_id')
                )
            ),
            default => throw new RuntimeException('نوع عملیات تکمیل محصول پشتیبانی نمی‌شود.'),
        };
    }
}
