<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\CatalogPriceFormatter;
use App\Services\CatalogPriceTypeSelection;
use PHPUnit\Framework\TestCase;

class CatalogPriceFormatterTest extends TestCase
{
    private CatalogPriceFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = new CatalogPriceFormatter();
    }

    public function test_toman_output_keeps_the_existing_integer_format(): void
    {
        $this->assertSame('1,382,400', $this->formatter->format(1382400, 'TOM'));
    }

    public function test_foreign_output_uses_the_applied_rate_and_rounds_to_two_decimals(): void
    {
        $this->assertSame('5.12 دلار', $this->formatter->format(1382400, 'USD', 'USD', 270000));
        $this->assertSame('5.38 دلار', $this->formatter->format(1451520, 'USD', 'USD', 270000));
        $this->assertSame('4.86 دلار', $this->formatter->format(1313280, 'USD', 'USD', 270000));
    }

    public function test_foreign_output_omits_trailing_zeroes_and_uses_local_currency_labels(): void
    {
        $this->assertSame('5 دلار', $this->formatter->format(1350000, 'USD', 'USD', 270000));
        $this->assertSame('5.12 یوان', $this->formatter->format(128000, 'CNY', 'CNY', 25000));
        $this->assertSame('5.12 درهم', $this->formatter->format(138240, 'AED', 'AED', 27000));
    }

    public function test_foreign_output_is_unavailable_without_a_matching_applied_snapshot(): void
    {
        $this->assertNull($this->formatter->format(1382400, 'USD', null, 270000));
        $this->assertNull($this->formatter->format(1382400, 'USD', 'CNY', 270000));
        $this->assertNull($this->formatter->format(1382400, 'USD', 'USD', 0));
        $this->assertNull($this->formatter->format(1382400, 'EUR', 'EUR', 270000));
    }

    public function test_all_pdf_price_types_use_database_ids_and_exclude_separate_warranty_type(): void
    {
        $selection = new CatalogPriceTypeSelection();

        $this->assertSame([
            0 => [1, 3, 8],
            1 => [1, 3, 8],
            2 => [1, 3, 8],
            3 => [1, 3, 8],
        ], $selection->normalizeForPdf('all', [
            1 => ['code' => 'cash', 'name' => 'نقدی'],
            3 => ['code' => 'check', 'name' => 'چکی'],
            6 => ['code' => 'warranty', 'name' => 'گارانتی'],
            8 => ['code' => 'custom', 'name' => 'سفارشی'],
        ]));
    }

    public function test_explicit_pdf_price_types_are_cast_to_database_ids_and_invalid_or_warranty_ids_are_removed(): void
    {
        $selection = new CatalogPriceTypeSelection();

        $this->assertSame([
            0 => [3, 1],
            2 => [8],
        ], $selection->normalizeForPdf([
            '0' => ['3', '1', '6', '999'],
            '2' => [8],
        ], [
            1 => ['code' => 'cash', 'name' => 'نقدی'],
            3 => ['code' => 'check', 'name' => 'چکی'],
            6 => ['code' => 'warranty', 'name' => 'گارانتی'],
            8 => ['code' => 'custom', 'name' => 'سفارشی'],
        ]));
    }
}
