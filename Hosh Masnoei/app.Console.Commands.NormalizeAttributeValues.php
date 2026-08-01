<?php

namespace App\Console\Commands;

use App\Models\AttributeValue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NormalizeAttributeValues extends Command
{
    protected $signature = 'attributes:normalize';

    protected $description = 'Normalize multi-value attribute values while preserving URLs and pivot values';

    public function handle(): int
    {
        $processed = 0;
        $skipped = 0;
        $createdRelations = 0;

        AttributeValue::query()
            ->whereHas('attribute', function ($query) {
                $query->where('is_multiple', true);
            })
            ->where(function ($query) {
                $query
                    ->where('value', 'like', '%/%')
                    ->orWhere('value', 'like', '%http://%')
                    ->orWhere('value', 'like', '%https://%');
            })
            ->orderBy('id')
            ->chunkById(100, function ($attributeValues) use (&$processed, &$skipped, &$createdRelations) {
                foreach ($attributeValues as $attributeValue) {
                    $parts = $this->splitValueSafely($attributeValue->value);

                    if (count($parts) <= 1) {
                        $skipped++;
                        continue;
                    }

                    DB::transaction(function () use ($attributeValue, $parts, &$processed, &$createdRelations) {
                        $pivotRows = DB::table('attribute_product')
                            ->where('attribute_value_id', $attributeValue->id)
                            ->get();

                        foreach ($parts as $index => $part) {
                            $newAttributeValue = AttributeValue::query()->firstOrCreate([
                                'attribute_id' => $attributeValue->attribute_id,
                                'value' => $part,
                            ]);

                            foreach ($pivotRows as $pivotRow) {
                                $pivotValue = $this->resolvePivotValue(
                                    $pivotRow->value,
                                    $attributeValue->value,
                                    $parts,
                                    $index,
                                    $part
                                );

                                DB::table('attribute_product')->updateOrInsert(
                                    [
                                        'product_id' => $pivotRow->product_id,
                                        'attribute_value_id' => $newAttributeValue->id,
                                    ],
                                    [
                                        'value' => $pivotValue,
                                        'created_at' => $pivotRow->created_at ?? now(),
                                        'updated_at' => now(),
                                    ]
                                );

                                $createdRelations++;
                            }
                        }

                        DB::table('attribute_product')
                            ->where('attribute_value_id', $attributeValue->id)
                            ->delete();

                        $oldValue = $attributeValue->value;

                        $attributeValue->delete();

                        $processed++;

                        $this->line("Normalized: {$oldValue}");
                    });
                }
            });

        $this->info('Normalization completed.');
        $this->info("Processed: {$processed}");
        $this->info("Skipped: {$skipped}");
        $this->info("Created/updated relations: {$createdRelations}");

        return self::SUCCESS;
    }

    private function splitValueSafely(?string $value): array
    {
        $value = trim((string) $value);

        if ($value === '') {
            return [];
        }

        if ($this->containsUrl($value)) {
            return $this->splitUrlValue($value);
        }

        return $this->splitNormalValue($value);
    }

    private function splitNormalValue(string $value): array
    {
        $parts = preg_split('/\s*\/\s*/u', $value, -1, PREG_SPLIT_NO_EMPTY);

        return $this->cleanParts($parts ?: []);
    }

    private function splitUrlValue(string $value): array
    {
        preg_match_all('/https?:\/\//i', $value, $matches, PREG_OFFSET_CAPTURE);

        $urlStarts = $matches[0] ?? [];

        if (count($urlStarts) <= 1) {
            return [$this->trimUrlSeparators($value)];
        }

        $parts = [];

        foreach ($urlStarts as $index => $match) {
            $start = $match[1];
            $end = isset($urlStarts[$index + 1])
                ? $urlStarts[$index + 1][1]
                : strlen($value);

            $parts[] = substr($value, $start, $end - $start);
        }

        return $this->cleanParts(array_map(
            fn (string $part): string => $this->trimUrlSeparators($part),
            $parts
        ));
    }

    private function resolvePivotValue(
        ?string $pivotValue,
        string $originalAttributeValue,
        array $attributeParts,
        int $partIndex,
        string $fallbackPart
    ): string {
        $pivotValue = trim((string) $pivotValue);

        if ($pivotValue === '') {
            return $fallbackPart;
        }

        $pivotParts = $this->splitValueSafely($pivotValue);

        if (count($pivotParts) === count($attributeParts)) {
            return $pivotParts[$partIndex];
        }

        if ($pivotValue === trim($originalAttributeValue)) {
            return $fallbackPart;
        }

        return $pivotValue;
    }

    private function containsUrl(string $value): bool
    {
        return preg_match('/https?:\/\//i', $value) === 1;
    }

    private function cleanParts(array $parts): array
    {
        $cleaned = [];

        foreach ($parts as $part) {
            $part = trim((string) $part);

            if ($part === '') {
                continue;
            }

            $cleaned[$part] = $part;
        }

        return array_values($cleaned);
    }

    private function trimUrlSeparators(string $value): string
    {
        return trim($value, " \t\n\r\0\x0B/,،;|");
    }
}
