<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\ProductCompletionTaskStatus;
use DomainException;

class InvalidProductCompletionTransitionException extends DomainException
{
    public static function between(
        ProductCompletionTaskStatus $current,
        ProductCompletionTaskStatus $next,
    ): self {
        return new self("انتقال وضعیت از {$current->value} به {$next->value} مجاز نیست.");
    }
}
