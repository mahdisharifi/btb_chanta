<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ProductCompletionOperation;
use App\Services\ProductCompletionOperationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessProductCompletionOperationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly int $operationId)
    {
    }

    public function handle(ProductCompletionOperationService $service): void
    {
        $operation = ProductCompletionOperation::query()->findOrFail($this->operationId);
        $service->process($operation);
    }

    public function backoff(): array
    {
        return [60, 300, 900];
    }
}
