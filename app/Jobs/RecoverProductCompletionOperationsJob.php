<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\ProductCompletionOperationStatus;
use App\Models\ProductCompletionOperation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RecoverProductCompletionOperationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        ProductCompletionOperation::query()
            ->whereIn('status', [
                ProductCompletionOperationStatus::PENDING,
                ProductCompletionOperationStatus::FAILED,
            ])
            ->where('attempts', '<', 5)
            ->where('available_at', '<=', now())
            ->select('id')
            ->chunkById(100, function ($operations): void {
                foreach ($operations as $operation) {
                    ProcessProductCompletionOperationJob::dispatch($operation->getKey());
                }
            });
    }
}
