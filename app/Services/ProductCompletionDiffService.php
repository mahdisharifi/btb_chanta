<?php

declare(strict_types=1);

namespace App\Services;

class ProductCompletionDiffService
{
    public function diff(array $before, array $after): array
    {
        $diff = [];
        $fields = array_unique(array_merge(array_keys($before), array_keys($after)));

        foreach ($fields as $field) {
            if ($field === 'schema_version') {
                continue;
            }

            $oldValue = $before[$field] ?? null;
            $newValue = $after[$field] ?? null;

            if ($this->canonicalJson($oldValue) !== $this->canonicalJson($newValue)) {
                $diff[$field] = [
                    'before' => $oldValue,
                    'after' => $newValue,
                ];
            }
        }

        ksort($diff);

        return $diff;
    }

    public function hash(array $payload): string
    {
        return hash('sha256', $this->canonicalJson($payload));
    }

    private function canonicalJson(mixed $value): string
    {
        return json_encode(
            $this->canonicalize($value),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalize($item);
        }

        return $value;
    }
}
