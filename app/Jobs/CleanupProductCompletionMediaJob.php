<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\ProductCompletionMediaStatus;
use App\Enums\ProductCompletionTaskStatus;
use App\Models\ProductCompletionMedia;
use App\Services\ProductCompletionMediaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CleanupProductCompletionMediaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function handle(ProductCompletionMediaService $service): void
    {
        $retentionDate = now()->subDays(
            (int) config('product_completion.media.rejected_retention_days', 7)
        );

        ProductCompletionMedia::query()
            ->where('updated_at', '<=', $retentionDate)
            ->where(function ($query): void {
                $query->where('status', ProductCompletionMediaStatus::REJECTED)
                    ->orWhere(function ($temporary): void {
                        $temporary->whereIn('status', [
                            ProductCompletionMediaStatus::TEMPORARY,
                            ProductCompletionMediaStatus::UNDER_REVIEW,
                        ])->whereHas('task', function ($task): void {
                            $task->whereIn('status', [
                                ProductCompletionTaskStatus::CANCELLED,
                                ProductCompletionTaskStatus::EXPIRED,
                            ]);
                        });
                    });
            })
            ->select('id')
            ->chunkById(50, function ($mediaItems) use ($service): void {
                foreach ($mediaItems as $media) {
                    $service->cleanup($media);
                }
            });
    }
}
