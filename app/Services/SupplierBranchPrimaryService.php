<?php

namespace App\Services;

use App\Models\Supplier;

class SupplierBranchPrimaryService
{
    /** Kept to reject callers of the retired primary-branch promotion API. */
    public function promote(Supplier $currentPrimary, int $branchId): Supplier
    {
        abort(403, 'تغییر شعبهٔ اصلی در دسترس نیست.');
    }
}
