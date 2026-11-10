<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Livewire\Reviewer\Task\Review;
use Livewire\Features\SupportAttributes\SupportAttributes;
use Tests\TestCase;

class ReviewerAttributeSearchRenderTest extends TestCase
{
    public function test_livewire_search_action_skips_full_review_render(): void
    {
        $review = new Review();
        $hook = new SupportAttributes();
        $hook->setComponent($review);

        $hook->callCall('searchReviewAttributeValues', [0, 'value'], static function (): void {});

        $this->assertTrue(\Livewire\store($review)->get('skipRender'));
    }

    public function test_livewire_attribute_changes_still_render_updated_review_state(): void
    {
        $review = new Review();
        $hook = new SupportAttributes();
        $hook->setComponent($review);

        $hook->callCall('toggleReviewAttributeValue', [0, 1], static function (): void {});

        $this->assertNull(\Livewire\store($review)->get('skipRender'));
    }
}
