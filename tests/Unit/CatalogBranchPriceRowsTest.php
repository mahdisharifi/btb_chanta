<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\CatalogBranchPriceRows;
use PHPUnit\Framework\TestCase;

class CatalogBranchPriceRowsTest extends TestCase
{
    public function test_inactive_price_types_are_omitted_from_all_and_explicit_branch_rows(): void
    {
        $service = new CatalogBranchPriceRows();
        $branches = [7 => ['label' => 'تهران', 'prices' => []]];
        $priceTypes = [
            1 => ['code' => 'cash', 'name' => 'نقدی', 'is_active' => true],
            2 => ['code' => 'check', 'name' => 'چکی', 'is_active' => false],
            3 => ['code' => 'warranty', 'name' => 'گارانتی', 'is_active' => true],
        ];

        $allRows = $service->build($branches, 'all', $priceTypes);
        $explicitRows = $service->build($branches, [0 => [1, 2, 3]], $priceTypes);

        $this->assertCount(4, $allRows);
        $this->assertCount(1, $explicitRows);
        $this->assertSame('0_1', $explicitRows[0]['key']);
    }
}
