<?php

declare(strict_types=1);

namespace App\Data;

final readonly class ProductCompletionDraftData
{
    public function __construct(private array $values)
    {
    }

    public static function fromValidated(array $values): self
    {
        return new self($values);
    }

    public function toArray(): array
    {
        return $this->values;
    }

    public function has(string $field): bool
    {
        return array_key_exists($field, $this->values);
    }

    public function get(string $field, mixed $default = null): mixed
    {
        return $this->values[$field] ?? $default;
    }
}
