<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\ProductCompletionRevision;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RevisionRequested
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly ProductCompletionRevision $revision)
    {
    }
}
