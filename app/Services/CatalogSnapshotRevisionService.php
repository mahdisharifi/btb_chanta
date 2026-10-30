<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class CatalogSnapshotRevisionService
{
    private const ALL_SUPPLIER_SHARDS = 64;

    private int $batchDepth = 0;

    /** @var array<int, int> */
    private array $batchedSupplierIds = [];

    private bool $batchedGlobal = false;

    /**
     * Coalesce model-observer invalidations that belong to one logical write.
     * Call this inside the transaction that changes the source rows.
     */
    public function batch(callable $callback): mixed
    {
        $this->batchDepth++;

        try {
            return $callback();
        } finally {
            $this->batchDepth--;

            if ($this->batchDepth === 0) {
                $supplierIds = array_values($this->batchedSupplierIds);
                $global = $this->batchedGlobal;
                $this->batchedSupplierIds = [];
                $this->batchedGlobal = false;

                if ($global) {
                    $this->invalidateGlobal();
                } elseif ($supplierIds !== []) {
                    $this->invalidateSuppliers($supplierIds);
                }
            }
        }
    }

    /**
     * Return a compact revision signature without scanning catalog source tables.
     * An empty supplier list represents an all-suppliers catalog.
     *
     * @param array<int, int|string> $supplierIds
     */
    public function signatureFor(array $supplierIds): string
    {
        $supplierIds = $this->normalizeSupplierIds($supplierIds);

        $query = DB::table('catalog_snapshot_revisions')
            ->where(function (Builder $query) use ($supplierIds): void {
                $query->where('scope_type', 'global');

                if ($supplierIds === []) {
                    $query->orWhere('scope_type', 'all_suppliers_shard');

                    return;
                }

                $query->orWhere(function (Builder $supplierQuery) use ($supplierIds): void {
                    $supplierQuery->where('scope_type', 'supplier')
                        ->whereIn('scope_id', $supplierIds);
                });
            })
            ->orderBy('scope_type')
            ->orderBy('scope_id')
            ->get(['scope_type', 'scope_id', 'revision']);

        $revisions = ['global' => 0, 'all_suppliers_shards' => [], 'suppliers' => []];
        foreach ($query as $row) {
            if ($row->scope_type === 'global') {
                $revisions['global'] = (int) $row->revision;
            } elseif ($row->scope_type === 'all_suppliers_shard') {
                $revisions['all_suppliers_shards'][(int) $row->scope_id] = (int) $row->revision;
            } elseif ($row->scope_type === 'all_suppliers') {
                // Legacy scope rows are intentionally ignored after migration to sharded revisions.
            } else {
                $revisions['suppliers'][(int) $row->scope_id] = (int) $row->revision;
            }
        }

        ksort($revisions['all_suppliers_shards']);

        if ($supplierIds !== []) {
            foreach ($supplierIds as $supplierId) {
                $revisions['suppliers'][$supplierId] ??= 0;
            }
            ksort($revisions['suppliers']);
        }

        return hash('sha256', (string) json_encode($revisions, JSON_THROW_ON_ERROR));
    }

    public function invalidateGlobal(): void
    {
        if ($this->batchDepth > 0) {
            $this->batchedGlobal = true;

            return;
        }

        $this->bumpScopes([['scope_type' => 'global', 'scope_id' => 0]]);
    }

    public function invalidateSupplier(int $supplierId): void
    {
        $this->invalidateSuppliers([$supplierId]);
    }

    /**
     * @param iterable<int, int|string> $supplierIds
     */
    public function invalidateSuppliers(iterable $supplierIds): void
    {
        $supplierIds = $this->normalizeSupplierIds(is_array($supplierIds) ? $supplierIds : iterator_to_array($supplierIds));
        if ($supplierIds === []) {
            return;
        }

        if ($this->batchDepth > 0) {
            foreach ($supplierIds as $supplierId) {
                $this->batchedSupplierIds[$supplierId] = $supplierId;
            }

            return;
        }

        $scopes = array_map(static fn (int $supplierId): array => [
            'scope_type' => 'supplier',
            'scope_id' => $supplierId,
        ], $supplierIds);
        $shardIds = array_values(array_unique(array_map(
            static fn (int $supplierId): int => $supplierId % self::ALL_SUPPLIER_SHARDS,
            $supplierIds,
        )));
        foreach ($shardIds as $shardId) {
            $scopes[] = ['scope_type' => 'all_suppliers_shard', 'scope_id' => $shardId];
        }

        $this->bumpScopes($scopes);
    }

    /**
     * @param array<int, array{scope_type:string,scope_id:int}> $scopes
     */
    private function bumpScopes(array $scopes): void
    {
        $write = static function () use ($scopes): void {
            $supplierScopes = array_values(array_filter(
                $scopes,
                static fn (array $scope): bool => $scope['scope_type'] === 'supplier',
            ));
            DB::table('catalog_snapshot_revisions')->insertOrIgnore(array_map(
                static fn (array $scope): array => $scope + ['revision' => 0],
                $supplierScopes,
            ));

            foreach (collect($scopes)->groupBy('scope_type') as $scopeType => $rows) {
                DB::table('catalog_snapshot_revisions')
                    ->where('scope_type', $scopeType)
                    ->whereIn('scope_id', $rows->pluck('scope_id')->all())
                    ->increment('revision');
            }
        };

        if (DB::transactionLevel() > 0) {
            $write();

            return;
        }

        DB::transaction($write, 3);
    }

    /**
     * @param array<int, int|string> $supplierIds
     * @return array<int, int>
     */
    private function normalizeSupplierIds(array $supplierIds): array
    {
        $supplierIds = array_values(array_unique(array_filter(
            array_map('intval', $supplierIds),
            static fn (int $supplierId): bool => $supplierId > 0,
        )));
        sort($supplierIds);

        return $supplierIds;
    }
}
