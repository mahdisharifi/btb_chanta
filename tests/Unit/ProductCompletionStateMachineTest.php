<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\ProductCompletionTaskStatus;
use PHPUnit\Framework\TestCase;

class ProductCompletionStateMachineTest extends TestCase
{
    public function test_only_declared_transitions_are_allowed(): void
    {
        $this->assertTrue(ProductCompletionTaskStatus::DRAFT->canTransitionTo(ProductCompletionTaskStatus::ASSIGNED));
        $this->assertTrue(ProductCompletionTaskStatus::IN_PROGRESS->canTransitionTo(ProductCompletionTaskStatus::SUBMITTED));
        $this->assertTrue(ProductCompletionTaskStatus::SUBMITTED->canTransitionTo(ProductCompletionTaskStatus::CONFLICTED));
        $this->assertFalse(ProductCompletionTaskStatus::DRAFT->canTransitionTo(ProductCompletionTaskStatus::COMPLETED));
        $this->assertFalse(ProductCompletionTaskStatus::COMPLETED->canEditDraft());
    }
}
