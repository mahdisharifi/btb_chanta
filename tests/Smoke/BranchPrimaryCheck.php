<?php

use App\Livewire\Supplier\Branches\ManageBranches;
use App\Models\Supplier;
use App\Services\SupplierBranchPrimaryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;

require __DIR__.'/BranchSalesListCheck.php';

$primaryCheckCompleted = false;
register_shutdown_function(static function () use (&$primaryCheckCompleted): void {
    if (!$primaryCheckCompleted) {
        exit(1);
    }
});

$snapshot = static function (): array {
    $state = [];
    foreach (Schema::getTableListing() as $table) {
        $state[$table] = DB::table($table)->get()->toJson();
    }

    return $state;
};
$before = $snapshot();
session(['supplier.active_branch_id' => $independent->id]);
$sessionBefore = session()->all();
$queryCount = 0;
DB::listen(static function () use (&$queryCount): void {
    $queryCount++;
});
$promotion = app(SupplierBranchPrimaryService::class);
$component = app(ManageBranches::class);
foreach ([$independent->id, $linked->id, $primary->id, $foreignId, 0, PHP_INT_MAX] as $candidateId) {
    foreach (['service', 'component'] as $entryPoint) {
        try {
            if ($entryPoint === 'service') {
                $promotion->promote($primary, (int) $candidateId);
            } else {
                $component->makePrimary((int) $candidateId);
            }
            throw new RuntimeException('Retired primary promotion was allowed.');
        } catch (HttpException $exception) {
            checkBranchCatalog($exception->getStatusCode() === 403, 'Retired promotion must return 403.');
        }
    }
}
checkBranchCatalog($queryCount === 0, 'Retired promotion accessed the database.');
checkBranchCatalog($before === $snapshot(), 'Retired promotion changed persisted state.');
checkBranchCatalog($sessionBefore === session()->all(), 'Retired promotion changed active branch context.');
$template = file_get_contents(resource_path('views/livewire/supplier/branches/manage.blade.php'));
checkBranchCatalog(!str_contains($template, 'makePrimary'), 'Primary promotion remains exposed in the branch view.');
checkBranchCatalog(str_contains($template, "route('supplier.branches.switch')"), 'Working branch selection was removed.');
checkBranchCatalog(str_contains($template, 'reviewBranchDeletion'), 'Branch deletion was removed.');
$primaryCheckCompleted = true;
echo "PASS: retired primary promotion hidden; service and stale Livewire calls return 403 without database queries, persisted changes or session changes; branch context selection and deletion remain available.\n";
