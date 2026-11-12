<?php

namespace App\Livewire\Supplier;

use App\Services\SupplierJobStatusService;
use Livewire\Component;

class JobStatus extends Component
{
    public function render()
    {
        abort_unless(auth()->check(), 403);
        // Resolve on every poll: never trust a supplier ID supplied by the browser.
        $supplier = auth()->user()->currentSupplier();
        try {
            $status = app(SupplierJobStatusService::class)->summary((int) $supplier->id);
        } catch (\Illuminate\Database\QueryException $exception) {
            report($exception);
            $status = ['available' => false, 'groups' => [], 'remaining' => 0, 'failed' => 0];
        }

        return view('livewire.supplier.job-status', compact('status', 'supplier'));
    }
}
