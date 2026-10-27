<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class CategoryIntegrity
{
    public static function assertParent(?int $id, ?int $parentId, Collection $nodes): void
    {
        $seen = [];
        while ($parentId !== null) {
            $parent = $nodes->get($parentId);
            if ($parentId === $id || isset($seen[$parentId]) || !$parent || $parent->deleted_at !== null) {
                throw ValidationException::withMessages(['parent_id' => 'والد انتخاب‌شده نامعتبر است یا باعث ایجاد حلقه در دسته‌بندی می‌شود.']);
            }
            $seen[$parentId] = true;
            $parentId = $parent->parent_id === null ? null : (int) $parent->parent_id;
        }
    }
}
