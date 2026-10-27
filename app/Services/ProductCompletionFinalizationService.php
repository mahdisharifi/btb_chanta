<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ProductCompletionCommission;
use App\Models\ProductCompletionTask;

class ProductCompletionFinalizationService
{
    public function __construct(
        private readonly ProductCompletionApprovalService $approvalService,
        private readonly ProductCompletionMediaService $mediaService,
        private readonly ProductCompletionCommissionService $commissionService,
    ) {
    }

    /**
     * Retry-safe orchestration after a submission has been approved.
     */
    public function finalize(ProductCompletionTask $task): ProductCompletionCommission
    {
        $this->approvalService->applyApprovedContent($task);
        $task->refresh();

        $this->mediaService->prepareApprovedMedia($task);
        $this->mediaService->publishApprovedMedia($task);
        $task->refresh();

        return $this->commissionService->calculate($task);
    }
}
