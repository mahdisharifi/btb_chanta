<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Supplier;

class SupplierProfileController extends Controller
{
    public function show(Supplier $supplier)
    {
        dd('ok');
        abort_unless($supplier->is_active ?? true, 404);

        return view('pages.suppliers.show', [
            'supplier' => $supplier,
        ]);
    }
}
