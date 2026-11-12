<?php

namespace App\Services;

/**
 * Builds branch-specific price lines while keeping one product card per family.
 */
final class CatalogBranchPriceRows
{
    /**
     * @param array<int|string, array{label:string,prices:array<string,array<string,mixed>>}> $branches
     * @param array<int|string, array{code?:string,name?:string}> $priceTypesById
     * @return array<int, array{key:string,label:string,branch_id:int,branch_label:string,price_data:?array}>
     */
    public function build(array $branches, mixed $selectedPriceTypes, array $priceTypesById): array
    {
        if ($branches === []) {
            return [];
        }

        $selectedTypesByTier = $this->selectedTypesByTier($selectedPriceTypes, $priceTypesById);
        $rows = [];
        $tierNames = [0 => 'زیرکارتن', 1 => 'کارتن', 2 => 'عمده', 3 => 'کل موجودی'];

        foreach ($selectedTypesByTier as $tierIndex => $priceTypeIds) {
            $tierName = $tierNames[(int) $tierIndex] ?? "تیراژ {$tierIndex}";

            foreach ($priceTypeIds as $priceTypeId) {
                $priceTypeId = (int) $priceTypeId;
                $priceType = $priceTypesById[$priceTypeId] ?? null;
                if (!$priceType
                    || !($priceType['is_active'] ?? true)
                    || ($priceType['code'] ?? null) === 'warranty') {
                    continue;
                }

                if (($priceType['code'] ?? null) === 'check') {
                    $periods = [];
                    foreach ($branches as $branch) {
                        foreach (($branch['prices'] ?? []) as $priceData) {
                            if (is_array($priceData)
                                && (int) ($priceData['tier_index'] ?? -1) === (int) $tierIndex
                                && (int) ($priceData['price_type_id'] ?? 0) === $priceTypeId
                                && isset($priceData['check_period_id'])) {
                                $periods[(int) $priceData['check_period_id']] = [
                                    'id' => (int) $priceData['check_period_id'],
                                    'days' => (int) ($priceData['check_period_days'] ?? 0),
                                    'name' => (string) ($priceData['check_period_name'] ?? ''),
                                ];
                            }
                        }
                    }

                    uasort($periods, static fn (array $left, array $right): int =>
                        [$left['days'], $left['id']] <=> [$right['days'], $right['id']]
                    );

                    foreach ($periods as $period) {
                        $key = "{$tierIndex}_{$priceTypeId}_{$period['id']}";
                        foreach ($branches as $branchId => $branch) {
                            $priceData = $branch['prices'][$key] ?? null;
                            $periodLabel = trim((string) ($priceData['check_period_label'] ?? ''));
                            if ($periodLabel === '') {
                                $periodLabel = trim((string) ($period['name'] ?? ''));
                            }
                            $periodLabel = preg_replace('/^چک(?:ی)?[\s·-]*/u', '', $periodLabel) ?? $periodLabel;
                            if (trim($periodLabel) === '') {
                                $periodLabel = trim((string) ($period['name'] ?? ''));
                                $periodLabel = preg_replace('/^چک(?:ی)?[\s·-]*/u', '', $periodLabel) ?? $periodLabel;
                            }
                            $labelSuffix = 'چکی' . ($periodLabel !== '' ? " · {$periodLabel}" : '');
                            $rows[] = $this->row(
                                (int) $branchId,
                                $branch,
                                $key,
                                $this->branchLabel($branch, $tierName, $labelSuffix),
                            );
                        }
                    }

                    continue;
                }

                $key = "{$tierIndex}_{$priceTypeId}";
                $typeName = (string) ($priceType['name'] ?? "قیمت {$priceTypeId}");
                $typeSuffix = ($priceType['code'] ?? null) === 'cash'
                    ? (count($priceTypeIds) > 1 ? 'نقدی' : null)
                    : $typeName;
                foreach ($branches as $branchId => $branch) {
                    $rows[] = $this->row(
                        (int) $branchId,
                        $branch,
                        $key,
                        $this->branchLabel($branch, $tierName, $typeSuffix),
                    );
                }
            }
        }

        return $rows;
    }

    /**
     * @param array<int|string, array{code?:string,name?:string}> $priceTypesById
     * @return array<int, array<int>>
     */
    public function selectedTypesByTier(mixed $selectedPriceTypes, array $priceTypesById): array
    {
        if ($selectedPriceTypes === 'all') {
            $typeIds = [];
            foreach ($priceTypesById as $id => $type) {
                if (($type['is_active'] ?? true) && ($type['code'] ?? null) !== 'warranty') {
                    $typeIds[] = (int) $id;
                }
            }

            return array_fill_keys([0, 1, 2, 3], $typeIds);
        }

        if (!is_array($selectedPriceTypes)) {
            return [];
        }

        $selected = [];
        foreach ($selectedPriceTypes as $tier => $typeIds) {
            if (!is_array($typeIds)) {
                continue;
            }

            $selected[(int) $tier] = array_values(array_unique(array_filter(
                array_map('intval', $typeIds),
                static fn (int $id): bool => isset($priceTypesById[$id])
                    && ($priceTypesById[$id]['is_active'] ?? true)
                    && ($priceTypesById[$id]['code'] ?? null) !== 'warranty',
            )));
        }

        ksort($selected);

        return $selected;
    }

    /** @param array{label?:string,prices?:array<string,array<string,mixed>>} $branch */
    private function row(int $branchId, array $branch, string $key, string $label): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'branch_id' => $branchId,
            'branch_label' => (string) ($branch['label'] ?? ''),
            'price_data' => $branch['prices'][$key] ?? null,
        ];
    }

    /** @param array{label?:string} $branch */
    private function branchLabel(array $branch, string $tierName, ?string $priceTypeName): string
    {
        $parts = array_filter([
            (string) ($branch['label'] ?? ''),
            $tierName,
            $priceTypeName,
        ], static fn ($part): bool => is_string($part) && $part !== '');

        return implode(' - ', $parts);
    }
}
