<?php

namespace App\Livewire\Supplier\Branches;

use App\Models\Supplier;
use App\Models\SupplierBranchProductTransfer;
use App\Services\SupplierBranchPrimaryService;
use App\Services\SupplierBranchTransferService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;

#[Layout('components.layouts.supplier')]
class ManageBranches extends Component
{
    public string $branchName = '';
    public array $branchCities = [];

    public function mount(): void
    {
        $primary = auth()->user()->supplier()->firstOrFail();
        $this->branchCities[$primary->id] = $primary->city ?? '';

        foreach ($primary->branches()->get(['id', 'city']) as $branch) {
            $this->branchCities[$branch->id] = $branch->city ?? '';
        }
    }

    #[Computed]
    public function primarySupplier(): Supplier
    {
        return auth()->user()->supplier()->firstOrFail();
    }

    #[Computed]
    public function branches()
    {
        return $this->primarySupplier->branches()
            ->withCount(['branchProductTransfers as pending_transfers_count' => fn ($query) => $query->where('status', 'pending')])
            ->withExists(['ownedProducts as has_shared_active_product' => fn ($products) => $products
                ->eligiblePrimarySwitchCopy($this->primarySupplier)])
            ->get();
    }

    public function createBranch(SupplierBranchTransferService $transferService)
    {
        $primary = auth()->user()->supplier()->firstOrFail();
        $this->validate([
            'branchName' => [
                'required',
                'string',
                'max:120',
                Rule::unique('suppliers', 'branch_name')
                    ->where(fn ($query) => $query->where('parent_supplier_id', $primary->id)),
            ],
        ], [
            'branchName.required' => 'نام شعبه را وارد کنید.',
            'branchName.unique' => 'این نام قبلاً برای یکی از شعبه‌های فروشگاه استفاده شده است.',
        ]);

        try {
            $primaryId = (int) $primary->id;
            $ownerId = (int) auth()->id();
            $branch = DB::transaction(function () use ($primaryId, $ownerId, $transferService): Supplier {
                $primary = Supplier::query()
                    ->whereKey($primaryId)
                    ->where('user_id', $ownerId)
                    ->whereNull('parent_supplier_id')
                    ->lockForUpdate()
                    ->firstOrFail();

                $branch = $primary->replicate();
                $branch->parent_supplier_id = $primary->id;
                $branch->branch_name = trim($this->branchName);
                $branch->slug = $this->branchSlug($primary, $this->branchName);
                $branch->user_id = $primary->user_id;
                $branch->save();

                $transferService->seedPendingTransfers($primary, $branch);

                return $branch;
            }, 3);
        } catch (Throwable $exception) {
            report($exception);
            $this->addError('branchName', 'ساخت شعبه انجام نشد. دوباره تلاش کنید.');
            return null;
        }

        session()->put('supplier.active_branch_id', $branch->id);
        $this->branchName = '';

        return redirect()->route('supplier.branches.transfers');
    }

    public function saveCity(int $supplierId): void
    {
        $primary = auth()->user()->supplier()->firstOrFail();
        $field = 'branchCities.' . $supplierId;
        $this->validate([$field => 'nullable|string|max:120']);

        $supplier = (int) $supplierId === (int) $primary->id
            ? $primary
            : $primary->branches()->whereKey($supplierId)->firstOrFail();

        $supplier->update(['city' => trim((string) ($this->branchCities[$supplierId] ?? '')) ?: null]);
        $this->dispatch('notify', ['type' => 'success', 'message' => 'شهر شعبه ذخیره شد.']);
    }

    public function deleteBranch(int $supplierId): void
    {
        DB::transaction(function () use ($supplierId): void {
            $branch = $this->primarySupplier->branches()->whereKey($supplierId)->lockForUpdate()->firstOrFail();
            $now = now();

            SupplierBranchProductTransfer::query()
                ->where('target_supplier_id', $branch->id)
                ->where('status', SupplierBranchProductTransfer::PENDING)
                ->update([
                    'status' => SupplierBranchProductTransfer::CANCELLED,
                    'decided_at' => $now,
                    'updated_at' => $now,
                ]);

            $branch->delete();
        }, 3);

        if ((int) session('supplier.active_branch_id') === $supplierId) {
            session()->forget('supplier.active_branch_id');
        }

        unset($this->branchCities[$supplierId]);
        unset($this->branches);

        $this->dispatch('notify', ['type' => 'success', 'message' => 'شعبه از فهرست شعب فعال حذف شد.']);
    }

    public function makePrimary(int $supplierId)
    {
        $newPrimary = app(SupplierBranchPrimaryService::class)->promote($this->primarySupplier, $supplierId);
        session()->forget('supplier.active_branch_id');
        unset($this->primarySupplier, $this->branches);
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'شعبهٔ انتخاب‌شده به ریشهٔ فروشگاه تبدیل شد؛ نسبت قیمت محصولات مشترک حفظ شد.',
        ]);

        return $this->redirectRoute('supplier.branches.index');
    }

    private function branchSlug(Supplier $primary, string $name): string
    {
        $base = Str::slug($primary->slug ?: $primary->name ?: $primary->company_name) ?: 'store';
        $branch = Str::slug($name) ?: 'branch';
        $suffix = Str::lower(Str::random(8));

        return Str::limit($base, 100, '') . '-' . Str::limit($branch, 70, '') . '-' . $suffix;
    }

    public function render()
    {
        return view('livewire.supplier.branches.manage');
    }
}
