<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ProductCompletionTask;
use App\Services\ProductCompletionAuditService;
use App\Services\ProductCompletionFinalizationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class FinalizeProductCompletionJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $uniqueFor = 3600;

    public function __construct(public readonly int $taskId)
    {
        $this->onQueue('default');
    }

    public function handle(ProductCompletionFinalizationService $service): void
    {
        $task = ProductCompletionTask::query()->findOrFail($this->taskId);
        $service->finalize($task);
    }

    public function uniqueId(): string
    {
        return 'product-completion:'.$this->taskId;
    }

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function failed(Throwable $exception): void
    {
        DB::transaction(function () use ($exception): void {
            $task = ProductCompletionTask::query()->lockForUpdate()->find($this->taskId);

            if ($task === null || $task->status->isTerminal()) {
                return;
            }

            $message = mb_substr($exception->getMessage(), 0, 2000);
            $task->forceFill([
                'last_error' => $message,
                'lock_version' => $task->lock_version + 1,
            ])->save();
            app(ProductCompletionAuditService::class)->record(
                action: 'product_completion_finalization_failed',
                task: $task,
                metadata: [
                    'job' => self::class,
                    'error' => $message,
                    'attempts' => $this->attempts(),
                ],
                entity: $task,
            );
        }, 3);
    }
}
