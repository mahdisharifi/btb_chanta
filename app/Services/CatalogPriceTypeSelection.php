<?php

declare(strict_types=1);

namespace App\Services;

class CatalogPriceTypeSelection
{
    /**
     * Resolve the catalog's saved price-type selection to the database IDs
     * consumed by both PDF templates. Warranty remains a separate toggle.
     *
     * @param array<int|string, array{code?: string, name?: string}> $priceTypesById
     * @return array<int, list<int>>
     */
    public function normalizeForPdf(mixed $selectedPriceTypes, array $priceTypesById): array
    {
        $selectableIds = [];

        foreach ($priceTypesById as $id => $priceType) {
            if (($priceType['code'] ?? null) === 'warranty') {
                continue;
            }

            $selectableIds[] = (int) $id;
        }

        if ($selectedPriceTypes === 'all') {
            return array_fill_keys([0, 1, 2, 3], $selectableIds);
        }

        if (!is_array($selectedPriceTypes)) {
            return [];
        }

        $normalized = [];

        foreach ($selectedPriceTypes as $tierIndex => $priceTypeIds) {
            if (!is_array($priceTypeIds)) {
                continue;
            }

            $normalized[(int) $tierIndex] = array_values(array_unique(array_filter(
                array_map('intval', $priceTypeIds),
                static fn (int $id): bool => isset($priceTypesById[$id])
                    && ($priceTypesById[$id]['code'] ?? null) !== 'warranty'
            )));
        }

        return $normalized;
    }
}
