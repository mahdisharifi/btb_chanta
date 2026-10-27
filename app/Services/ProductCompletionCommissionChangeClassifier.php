<?php

declare(strict_types=1);

namespace App\Services;

class ProductCompletionCommissionChangeClassifier
{
    /**
     * Classify final changes against the product at the beginning of the task.
     * Each component represents newly supplied content or correction of existing content.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function classify(array $before, array $after): array
    {
        $changes = [];
        foreach (['title', 'short_description', 'full_description'] as $field) {
            $old = trim((string) ($before[$field] ?? ''));
            $new = trim((string) ($after[$field] ?? ''));
            if ($old !== $new && ($this->hasText($old) || $this->hasText($new))) {
                $changes[$field][] = $this->component($field, $this->hasText($old) ? 'correction' : 'new');
            }
        }

        $oldAttributes = $this->attributes($before);
        $newAttributes = $this->attributes($after);
        foreach (array_unique([...array_keys($oldAttributes), ...array_keys($newAttributes)]) as $id) {
            $removed = array_diff($oldAttributes[$id] ?? [], $newAttributes[$id] ?? []);
            $added = array_diff($newAttributes[$id] ?? [], $oldAttributes[$id] ?? []);
            // Replacing an existing selection is a correction; retaining it and adding another is new content.
            if ($removed !== [] || $added !== []) {
                $kind = $removed !== [] ? 'correction' : 'new';
                $changes['attributes'][] = $this->component('attribute:'.$id, $kind) + ['attribute_id' => $id];
            }
        }

        $oldImages = $this->images($before);
        $newImages = $this->images($after);
        foreach (array_unique([...array_keys($oldImages), ...array_keys($newImages)]) as $identity) {
            if (isset($oldImages[$identity]) !== isset($newImages[$identity])) {
                $added = isset($newImages[$identity]);
                $changes['gallery'][] = $this->component('image:'.$identity.':'.($added ? 'added' : 'removed'), $added ? 'new' : 'correction');
            }
        }

        return $changes;
    }

    /** @return array<string, array<string, array<string, mixed>>> */
    public function units(array $before, array $after): array
    {
        $changes = $this->classify($before, $after);
        $units = ['image' => [], 'video' => [], 'attribute' => [], 'description' => [], 'title' => []];
        foreach (['gallery' => 'image', 'attributes' => 'attribute', 'title' => 'title'] as $field => $type) {
            foreach ($changes[$field] ?? [] as $component) {
                $units[$type][$component['key']] = $component;
            }
        }
        $descriptionChanges = [...($changes['short_description'] ?? []), ...($changes['full_description'] ?? [])];
        if ($descriptionChanges !== []) {
            $isNew = in_array('new', array_column($descriptionChanges, 'kind'), true);
            $units['description']['description:short_and_long'] = $this->component('description:short_and_long', $isNew ? 'new' : 'correction');
        }

        return $units;
    }

    /** @return array{key: string, kind: string, rate_percentage: string} */
    private function component(string $key, string $kind): array
    {
        return ['key' => $key, 'kind' => $kind, 'rate_percentage' => $kind === 'new' ? '100.00' : '30.00'];
    }

    private function hasText(string $value): bool
    {
        $text = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return preg_match('/[^\s\x{00A0}\x{200B}\x{200C}]/u', $text) === 1;
    }

    /** @return array<int, array<int, string>> */
    private function attributes(array $payload): array
    {
        $attributes = [];
        foreach ($payload['attributes'] ?? [] as $attribute) {
            $values = array_map(static fn ($id): string => 'id:'.(int) $id, $attribute['value_ids'] ?? []);
            foreach ($attribute['proposed_values'] ?? [] as $proposal) {
                $proposal = trim((string) $proposal);
                if ($proposal !== '') {
                    $values[] = 'proposal:'.$proposal;
                }
            }
            $attributes[(int) $attribute['attribute_id']] = array_values(array_unique($values));
        }

        return $attributes;
    }

    /** @return array<string, bool> */
    private function images(array $payload): array
    {
        $images = [];
        foreach (data_get($payload, 'gallery.items', []) as $item) {
            if (($item['action'] ?? 'keep') === 'remove') {
                continue;
            }
            $identity = !empty($item['product_gallery_id'])
                ? 'gallery:'.(int) $item['product_gallery_id']
                : (!empty($item['media_public_id']) ? 'media:'.$item['media_public_id'] : null);
            if ($identity !== null) {
                $images[$identity] = true;
            }
        }

        return $images;
    }
}
