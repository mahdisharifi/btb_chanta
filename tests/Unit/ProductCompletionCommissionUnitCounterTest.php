<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ProductCompletionCommissionUnitCounter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductCompletionCommissionUnitCounterTest extends TestCase
{
    #[Test]
    public function it_counts_new_and_approved_removed_images_but_ignores_metadata_and_order(): void
    {
        $before = [
            'gallery' => ['items' => [
                ['product_gallery_id' => 10, 'action' => 'keep', 'sort_order' => 1, 'alt_text' => 'قدیمی'],
                ['product_gallery_id' => 20, 'action' => 'keep', 'sort_order' => 2],
            ]],
        ];
        $after = [
            'gallery' => ['items' => [
                ['product_gallery_id' => 20, 'action' => 'keep', 'sort_order' => 1],
                ['product_gallery_id' => 10, 'action' => 'remove', 'sort_order' => 2, 'alt_text' => 'جدید'],
                ['media_public_id' => '01NEWIMAGE000000000000000', 'action' => 'add', 'sort_order' => 3],
            ]],
        ];

        $units = app(ProductCompletionCommissionUnitCounter::class)->compare($before, $after);

        $this->assertSame([
            'image:gallery:10:removed',
            'image:media:01NEWIMAGE000000000000000:added',
        ], $units['image']);
        $this->assertSame([], $units['video']);
    }

    #[Test]
    public function it_counts_each_attribute_once_regardless_of_selected_value_count(): void
    {
        $before = ['attributes' => [
            ['attribute_id' => 1, 'value_ids' => [10, 20]],
            ['attribute_id' => 2, 'value_ids' => [30]],
        ]];
        $after = ['attributes' => [
            ['attribute_id' => 1, 'value_ids' => [20, 40, 50]],
            ['attribute_id' => 2, 'value_ids' => [30]],
            ['attribute_id' => 3, 'value_ids' => [60, 70]],
        ]];

        $units = app(ProductCompletionCommissionUnitCounter::class)->compare($before, $after);

        $this->assertSame(['attribute:1', 'attribute:3'], $units['attribute']);
    }

    #[Test]
    public function it_counts_short_and_long_descriptions_as_one_shared_unit(): void
    {
        $units = app(ProductCompletionCommissionUnitCounter::class)->compare(
            ['short_description' => 'کوتاه قبلی', 'full_description' => 'بلند قبلی'],
            ['short_description' => 'کوتاه جدید', 'full_description' => 'بلند جدید'],
        );

        $this->assertSame(['description:short_and_long'], $units['description']);
    }
}
