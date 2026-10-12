<?php

declare(strict_types=1);

namespace App\Casts;

use App\Support\ProductCompletionPayload;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class ProductCompletionPayloadCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        return $value === null ? null : $this->filter($key, json_decode($value, true, 512, JSON_THROW_ON_ERROR));
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        return json_encode($this->filter($key, $value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    private function filter(string $key, array $value): array
    {
        if ($key === 'requested_fields_json') {
            return array_values(array_filter($value, static fn (string $field): bool => ! ProductCompletionPayload::isExcludedField($field)));
        }

        return ProductCompletionPayload::withoutExcludedFields($value);
    }
}
