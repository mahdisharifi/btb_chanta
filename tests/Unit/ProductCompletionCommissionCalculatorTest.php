<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ProductCompletionCommissionCalculator;
use InvalidArgumentException;
use Tests\TestCase;

class ProductCompletionCommissionCalculatorTest extends TestCase
{
    public function test_money_is_calculated_without_float_drift(): void
    {
        $total = app(ProductCompletionCommissionCalculator::class)->recalculateTotal(
            base: '100.10',
            revision: '20.00',
            approvedItems: '5.00',
            bonus: '3.00',
            penalty: '2.00',
            adjustment: '-1.00',
        );

        $this->assertSame('125.10', $total);
    }

    public function test_excess_decimal_places_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(ProductCompletionCommissionCalculator::class)
            ->recalculateTotal('1.234', '0', '0', '0', '0', '0');
    }
}
