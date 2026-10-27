<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\ProductCompletionCommission;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CommissionApproved
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly ProductCompletionCommission $commission)
    {
    }
}
