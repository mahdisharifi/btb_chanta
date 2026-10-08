<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ProductCompletionTaskType;
use Illuminate\Validation\ValidationException;

class ProductCompletionScopeDefinitionValidator
{
    public function resolve(ProductCompletionTaskType $taskType, ?array $scope = null): array
    {
        $scope ??= config("product_completion.scope_presets.{$taskType->value}");

        if (! is_array($scope)) {
            throw ValidationException::withMessages([
                'scope' => 'محدوده مجاز برای این نوع تسک تعریف نشده است.',
            ]);
        }

        $schema = config('product_completion.scope_presets.full_completion', []);
        $violations = [];

        foreach ($scope as $key => $value) {
            if (! array_key_exists($key, $schema)) {
                $violations[] = (string) $key;

                continue;
            }

            $schemaValue = $schema[$key];

            if (is_array($schemaValue)) {
                if ($value === false) {
                    continue;
                }

                if (! is_array($value)) {
                    $violations[] = (string) $key;

                    continue;
                }

                foreach ($value as $nestedKey => $nestedValue) {
                    if (! array_key_exists($nestedKey, $schemaValue) || ! is_bool($nestedValue)) {
                        $violations[] = $key.'.'.$nestedKey;
                    }
                }

                continue;
            }

            if (! is_bool($value)) {
                $violations[] = (string) $key;
            }
        }

        if ($violations !== []) {
            throw ValidationException::withMessages([
                'scope' => 'ساختار محدوده مجاز نامعتبر است: '.implode('، ', array_unique($violations)),
            ]);
        }

        $normalized = $this->normalizeAgainstSchema($scope, $schema);

        if (! $this->hasEnabledField($normalized)) {
            throw ValidationException::withMessages([
                'scope' => 'حداقل یک بخش قابل ویرایش باید در محدوده تسک فعال باشد.',
            ]);
        }

        return $normalized;
    }

    private function normalizeAgainstSchema(array $scope, array $schema): array
    {
        $normalized = [];

        foreach ($schema as $key => $schemaValue) {
            $value = $scope[$key] ?? false;

            if (is_array($schemaValue)) {
                if ($value === false) {
                    $normalized[$key] = false;

                    continue;
                }

                $nested = [];
                foreach ($schemaValue as $nestedKey => $default) {
                    $nested[$nestedKey] = (bool) ($value[$nestedKey] ?? false);
                }
                $normalized[$key] = $nested;

                continue;
            }

            $normalized[$key] = (bool) $value;
        }

        return $normalized;
    }

    private function hasEnabledField(array $scope): bool
    {
        foreach ($scope as $value) {
            if ($value === true) {
                return true;
            }

            if (is_array($value) && in_array(true, $value, true)) {
                return true;
            }
        }

        return false;
    }
}
