<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\ProductCompletionSubmission;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubmissionApproved
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly ProductCompletionSubmission $submission)
    {
    }
}
