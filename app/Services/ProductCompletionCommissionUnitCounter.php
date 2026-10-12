<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CommissionContentType;

class ProductCompletionCommissionUnitCounter
{
    /**
     * Return stable unit identifiers changed between two product-completion payloads.
     *
     * @return array<string, array<int, string>>
     */
    public function compare(array $before, array $after): array
    {
        return [
            CommissionContentType::IMAGE->value => $this->changedImages($before, $after),
            CommissionContentType::VIDEO->value => [],
            CommissionContentType::ATTRIBUTE->value => $this->changedAttributes($before, $after),
            CommissionContentType::DESCRIPTION->value => $this->changedDescription($before, $after),
        ];
    }

    /** @return array<int, string> */
    private function changedImages(array $before, array $after): array
    {
        $beforeImages = $this->activeImages($before);
        $afterImages = $this->activeImages($after);
        $identities = array_unique([...array_keys($beforeImages), ...array_keys($afterImages)]);
        $changed = [];

        foreach ($identities as $identity) {
            $wasActive = $beforeImages[$identity] ?? false;
            $isActive = $afterImages[$identity] ?? false;

            if ($wasActive !== $isActive) {
                $changed[] = 'image:'.$identity.':'.($isActive ? 'added' : 'removed');
            }
        }

        sort($changed);

        return $changed;
    }

    /** @return array<string, bool> */
    private function activeImages(array $payload): array
    {
        $images = [];

        foreach ((array) data_get($payload, 'gallery.items', []) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $identity = $this->imageIdentity($item);

            if ($identity === null) {
                continue;
            }

            $images[$identity] = ($item['action'] ?? 'keep') !== 'remove';
        }

        return $images;
    }

    private function imageIdentity(array $item): ?string
    {
        $galleryId = filter_var($item['product_gallery_id'] ?? null, FILTER_VALIDATE_INT);

        if ($galleryId !== false && $galleryId > 0) {
            return 'gallery:'.$galleryId;
        }

        $mediaId = trim((string) ($item['media_public_id'] ?? ''));

        return $mediaId !== '' ? 'media:'.$mediaId : null;
    }

    /** @return array<int, string> */
    private function changedAttributes(array $before, array $after): array
    {
        $beforeAttributes = $this->attributesById($before);
        $afterAttributes = $this->attributesById($after);
        $attributeIds = array_unique([...array_keys($beforeAttributes), ...array_keys($afterAttributes)]);
        $changed = [];

        foreach ($attributeIds as $attributeId) {
            if (($beforeAttributes[$attributeId] ?? []) !== ($afterAttributes[$attributeId] ?? [])) {
                $changed[] = 'attribute:'.$attributeId;
            }
        }

        sort($changed);

        return $changed;
    }

    /** @return array<int, array{value_ids: array<int, int>, proposed_values: array<int, string>}> */
    private function attributesById(array $payload): array
    {
        $attributes = [];

        foreach ((array) ($payload['attributes'] ?? []) as $attribute) {
            if (! is_array($attribute)) {
                continue;
            }

            $attributeId = filter_var($attribute['attribute_id'] ?? null, FILTER_VALIDATE_INT);

            if ($attributeId === false || $attributeId <= 0) {
                continue;
            }

            $valueIds = array_values(array_unique(array_map(
                'intval',
                (array) ($attribute['value_ids'] ?? [])
            )));
            sort($valueIds);
            $proposals = array_values(array_unique(array_map('trim', (array) ($attribute['proposed_values'] ?? []))));
            sort($proposals);
            if ($valueIds !== [] || $proposals !== []) {
                $attributes[(int) $attributeId] = ['value_ids' => $valueIds, 'proposed_values' => $proposals];
            }
        }

        ksort($attributes);

        return $attributes;
    }

    /** @return array<int, string> */
    private function changedDescription(array $before, array $after): array
    {
        foreach (['short_description', 'full_description'] as $field) {
            if ($this->canonicalJson($before[$field] ?? null) !== $this->canonicalJson($after[$field] ?? null)) {
                return ['description:short_and_long'];
            }
        }

        return [];
    }

    private function canonicalJson(mixed $value): string
    {
        return json_encode(
            $value,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }
}
