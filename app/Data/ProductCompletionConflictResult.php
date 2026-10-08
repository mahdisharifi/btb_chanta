<?php

declare(strict_types=1);

namespace App\Data;

final readonly class ProductCompletionConflictResult
{
    public function __construct(
        public array $mergedPayload,
        public array $conflicts,
        public int $currentProductVersion,
    ) {
    }

    public function hasConflicts(): bool
    {
        return $this->conflicts !== [];
    }
}
