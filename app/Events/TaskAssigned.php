<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\ProductCompletionTask;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskAssigned
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly ProductCompletionTask $task)
    {
    }
}
