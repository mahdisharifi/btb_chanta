<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\ProductCompletionTaskStatus;
use App\Models\ProductCompletionTask;
use App\Services\ProductCompletionTaskService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExpireProductCompletionTasksJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function handle(ProductCompletionTaskService $service): void
    {
        ProductCompletionTask::query()
            ->whereIn('status', [
                ProductCompletionTaskStatus::AVAILABLE,
                ProductCompletionTaskStatus::ASSIGNED,
                ProductCompletionTaskStatus::ACCEPTED,
                ProductCompletionTaskStatus::IN_PROGRESS,
                ProductCompletionTaskStatus::CHANGES_REQUESTED,
            ])
            ->where(function ($query): void {
                $query->where('due_at', '<', now())
                    ->orWhere(function ($assignment): void {
                        $assignment->whereNull('accepted_at')
                            ->where('assignment_expires_at', '<', now());
                    });
            })
            ->select('id')
            ->chunkById(100, function ($tasks) use ($service): void {
                foreach ($tasks as $task) {
                    $service->expire($task);
                }
            });
    }
}
