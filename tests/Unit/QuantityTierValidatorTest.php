<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Helpers\QuantityTierValidator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class QuantityTierValidatorTest extends TestCase
{
    public function test_three_tiers_with_blank_last_max_are_valid(): void
    {
        $tiers = [
            ['name' => 'خرد', 'min_quantity' => 1, 'max_quantity' => 50],
            ['name' => 'میانی', 'min_quantity' => 51, 'max_quantity' => 200],
            ['name' => 'عمده', 'min_quantity' => 201, 'max_quantity' => ''],
        ];

        $this->assertTrue(QuantityTierValidator::validate($tiers));
        $this->assertNull(QuantityTierValidator::normalize($tiers)[2]['max_quantity']);
    }

    public function test_three_tiers_with_null_last_max_are_valid(): void
    {
        $tiers = [
            ['name' => 'خرد', 'min_quantity' => 1, 'max_quantity' => 50],
            ['name' => 'میانی', 'min_quantity' => 51, 'max_quantity' => 200],
            ['name' => 'عمده', 'min_quantity' => 201, 'max_quantity' => null],
        ];

        $this->assertTrue(QuantityTierValidator::validate($tiers));
    }

    public function test_blank_max_on_a_non_last_tier_is_rejected_as_open_ended(): void
    {
        $tiers = [
            ['name' => 'خرد', 'min_quantity' => 1, 'max_quantity' => ''],
            ['name' => 'میانی', 'min_quantity' => 51, 'max_quantity' => 200],
            ['name' => 'عمده', 'min_quantity' => 201, 'max_quantity' => null],
        ];

        $this->expectException(ValidationException::class);

        QuantityTierValidator::validate($tiers);
    }

    public function test_non_last_tier_with_blank_max_stops_before_next_range(): void
    {
        $tiers = [
            ['name' => 'خرد', 'min_quantity' => 1, 'max_quantity' => null],
            ['name' => 'میانی', 'min_quantity' => 51, 'max_quantity' => 200],
            ['name' => 'عمده', 'min_quantity' => 201, 'max_quantity' => null],
        ];

        $this->expectException(ValidationException::class);

        QuantityTierValidator::validate($tiers);
    }

    public function test_capped_last_tier_error_is_attached_to_that_tier_field(): void
    {
        $tiers = [
            ['name' => 'خرد', 'min_quantity' => 1, 'max_quantity' => 50],
            ['name' => 'میانی', 'min_quantity' => 51, 'max_quantity' => 200],
            ['name' => 'عمده', 'min_quantity' => 201, 'max_quantity' => 300],
        ];

        try {
            QuantityTierValidator::validate($tiers);
            $this->fail('The capped last tier should have been rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('quantity_tiers.2.max_quantity', $exception->errors());
        }
    }

    public function test_all_inactive_tiers_return_validation_error_instead_of_a_type_error(): void
    {
        $tiers = [
            ['name' => 'خرد', 'min_quantity' => 0, 'max_quantity' => 0],
            ['name' => 'میانی', 'min_quantity' => 0, 'max_quantity' => 0],
            ['name' => 'عمده', 'min_quantity' => 0, 'max_quantity' => 0],
        ];

        $this->expectException(ValidationException::class);

        QuantityTierValidator::validate($tiers);
    }
}
