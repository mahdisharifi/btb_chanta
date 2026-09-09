<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CheckPercentageChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $supplierId;
    public ?int $categoryId;
    public ?int $checkPeriodId;
    public string $changeType; // 'default', 'category'
    public float $oldPercentage;
    public float $newPercentage;

    /**
     * Create a new event instance.
     */
    public function __construct(
        int $supplierId,
        ?int $categoryId,
        ?int $checkPeriodId,
        string $changeType,
        float $oldPercentage,
        float $newPercentage
    ) {
        $this->supplierId = $supplierId;
        $this->categoryId = $categoryId;
        $this->checkPeriodId = $checkPeriodId;
        $this->changeType = $changeType;
        $this->oldPercentage = $oldPercentage;
        $this->newPercentage = $newPercentage;
    }
}
