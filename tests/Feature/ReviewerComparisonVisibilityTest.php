<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ProductCompletionSubmissionStatus;
use App\Enums\ProductCompletionTaskStatus;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class ReviewerComparisonVisibilityTest extends TestCase
{
    public function test_comparisons_and_history_are_only_rendered_outside_the_editor(): void
    {
        // Pure view rendering: no migrations, persisted models, or database access.
        $task = (object) [
            'product' => (object) ['title' => 'Review fixture'],
            'last_submission_id' => 1,
            'status' => ProductCompletionTaskStatus::SUBMITTED,
            'scope_json' => [],
            'currency' => 'IRR',
            'commission_rule_snapshot_json' => [],
            'revision_count' => 0,
            'revision_limit' => 3,
        ];
        $submission = (object) [
            'id' => 1,
            'status' => ProductCompletionSubmissionStatus::UNDER_REVIEW,
            'version_no' => 2,
            'approval_selection_json' => null,
            'diff_json' => [
                'gallery' => ['before' => ['items' => []], 'after' => ['items' => []]],
                'short_description' => ['before' => 'Comparison before', 'after' => 'Comparison after'],
            ],
            'payload_json' => ['short_description' => 'Submission history content'],
        ];
        $data = [
            'task' => $task,
            'submission' => $submission,
            'errors' => new ViewErrorBag(),
            'reviewPayload' => [],
            'reviewDecisions' => [],
            'reviewEditsSaved' => false,
            'editorAttributes' => collect(),
            'reviewMediaItems' => collect(),
            'commissionPreviewPendingReview' => false,
            'commissionRateUnits' => [],
            'commissionPreview' => ['total_amount' => 0],
            'hasCorrections' => false,
            'classifications' => [],
            'fieldLabels' => ['gallery' => 'Gallery', 'short_description' => 'Description'],
        ];

        $comparison = view('livewire.reviewer.task.review', [...$data, 'showReviewEditor' => false])->render();
        $this->assertStringContainsString('review-field-gallery', $comparison);
        $this->assertStringContainsString('Comparison before', $comparison);
        $this->assertStringContainsString('Submission history content', $comparison);
        $this->assertStringNotContainsString('id="review-editor-panel"', $comparison);

        $editor = view('livewire.reviewer.task.review', [...$data, 'showReviewEditor' => true])->render();
        $this->assertStringContainsString('id="review-editor-panel"', $editor);
        $this->assertStringNotContainsString('review-field-gallery', $editor);
        $this->assertStringNotContainsString('Comparison before', $editor);
        $this->assertStringNotContainsString('Submission history content', $editor);

        $restored = view('livewire.reviewer.task.review', [...$data, 'showReviewEditor' => false])->render();
        $this->assertStringContainsString('review-field-gallery', $restored);
        $this->assertStringContainsString('Comparison before', $restored);
    }
}
