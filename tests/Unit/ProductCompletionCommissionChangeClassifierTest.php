<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ProductCompletionCommissionChangeClassifier;
use App\Services\ProductCompletionCommissionUnitCounter;
use Tests\TestCase;

class ProductCompletionCommissionChangeClassifierTest extends TestCase
{
    public function test_it_classifies_final_content_against_the_original_snapshot(): void
    {
        $before = ['short_description' => null, 'full_description' => null,
            'attributes' => [['attribute_id' => 2, 'value_ids' => [14]]],
            'gallery' => ['items' => [['product_gallery_id' => 1154, 'action' => 'keep']]],
        ];
        $after = ['short_description' => 'Revised introduction.', 'full_description' => 'New description.',
            'attributes' => [
                ['attribute_id' => 2, 'value_ids' => [], 'proposed_values' => ['pcs:41']],
                ['attribute_id' => 9, 'value_ids' => [4960]],
                ['attribute_id' => 15, 'value_ids' => [], 'proposed_values' => ['Plastic']],
            ],
            'gallery' => ['items' => [['product_gallery_id' => 1154, 'action' => 'keep'], ['media_public_id' => 'new-image', 'action' => 'add']]],
        ];
        $changes = app(ProductCompletionCommissionChangeClassifier::class)->classify($before, $after);
        $this->assertSame('100.00', $changes['short_description'][0]['rate_percentage']);
        $this->assertSame('100.00', $changes['full_description'][0]['rate_percentage']);
        $this->assertSame(['30.00', '100.00', '100.00'], array_column($changes['attributes'], 'rate_percentage'));
        $this->assertSame('100.00', $changes['gallery'][0]['rate_percentage']);
        $this->assertCount(1, $changes['gallery']);
        $this->assertSame(['attribute:15', 'attribute:2', 'attribute:9'], app(ProductCompletionCommissionUnitCounter::class)->compare($before, $after)['attribute']);
        $this->assertSame([], app(ProductCompletionCommissionChangeClassifier::class)->classify($after, $after));
    }

    public function test_existing_text_and_deletions_are_corrections_and_blank_markup_is_empty(): void
    {
        $changes = app(ProductCompletionCommissionChangeClassifier::class)->classify(
            ['short_description' => 'Wrong', 'full_description' => '<p>&nbsp;</p>', 'gallery' => ['items' => [['product_gallery_id' => 1]]]],
            ['short_description' => 'Correct', 'full_description' => 'Added', 'gallery' => ['items' => [['product_gallery_id' => 1, 'action' => 'remove']]]],
        );
        $this->assertSame('30.00', $changes['short_description'][0]['rate_percentage']);
        $this->assertSame('100.00', $changes['full_description'][0]['rate_percentage']);
        $this->assertSame('30.00', $changes['gallery'][0]['rate_percentage']);
    }

    public function test_adding_a_value_to_an_existing_multiple_attribute_is_new_content(): void
    {
        $classifier = app(ProductCompletionCommissionChangeClassifier::class);
        $before = ['attributes' => [['attribute_id' => 1, 'value_ids' => [10]]]];
        $after = ['attributes' => [['attribute_id' => 1, 'value_ids' => [10], 'proposed_values' => ['Added']]]];
        $this->assertSame(['new'], array_column($classifier->classify($before, $after)['attributes'], 'kind'));
        $after['attributes'][0] = ['attribute_id' => 1, 'value_ids' => [], 'proposed_values' => ['Replacement', 'Added']];
        $this->assertSame(['correction'], array_column($classifier->classify($before, $after)['attributes'], 'kind'));
    }

    public function test_the_description_pair_gets_full_rate_if_either_changed_description_is_new(): void
    {
        $classifier = app(ProductCompletionCommissionChangeClassifier::class);
        $before = ['short_description' => 'Old text', 'full_description' => null];
        $after = ['short_description' => 'Corrected text', 'full_description' => 'New full text'];
        $this->assertSame('100.00', $classifier->units($before, $after)['description']['description:short_and_long']['rate_percentage']);
        $before['full_description'] = 'Old full text';
        $this->assertSame('30.00', $classifier->units($before, $after)['description']['description:short_and_long']['rate_percentage']);
    }
}
