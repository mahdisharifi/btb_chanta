<?php

namespace App\Livewire\Admin\Suppliers;

use App\Models\Supplier;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Index extends Component
{
    use WithPagination;

    // متغیرهای فیلتر و جستجو
    public $search = '';
    public $status = '';           // active, inactive
    public $verification = '';     // pending, approved, rejected
    public $province = '';

    // متغیرهای مرتب‌سازی
    public $sortField = 'created_at';
    public $sortDirection = 'desc';

    // متغیرهای Bulk Actions (عملیات گروهی)
    public $selectedSuppliers = [];
    public $selectAll = false;

    // مودال رد درخواست
    public $showRejectModal = false;
    public $rejectSupplierId = null;
    public $rejectReason = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'status' => ['except' => ''],
        'verification' => ['except' => ''],
        'province' => ['except' => ''],
        'sortField' => ['except' => 'created_at'],
        'sortDirection' => ['except' => 'desc'],
    ];

    protected $listeners = ['openRejectModal'];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingStatus()
    {
        $this->resetPage();
    }

    public function updatingVerification()
    {
        $this->resetPage();
    }

    public function sortBy($field)
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    // تغییر وضعیت فعالیت
    public function toggleActive($supplierId)
    {
        $supplier = Supplier::findOrFail($supplierId);
        $this->authorize('update', $supplier);

        $supplier->is_active = !$supplier->is_active;
        $supplier->save();

        session()->flash('message', 'وضعیت فعالیت تامین‌کننده تغییر کرد.');
    }

    // تایید تامین‌کننده
    public function approveSupplier($supplierId)
    {
        $supplier = Supplier::findOrFail($supplierId);
        $this->authorize('update', $supplier);

        $supplier->verification_status = 'approved';
        $supplier->verified_at = now();
        $supplier->verified_by = auth()->id();
        $supplier->rejection_reason = null;
        $supplier->is_verified = true; // همگام‌سازی با سیستم قدیمی
        $supplier->save();
        session()->flash('message', 'تامین‌کننده با موفقیت تایید شد.');
    }

    // رد تامین‌کننده
    public function rejectSupplier($supplierId, $reason = null)
    {
        $supplier = Supplier::findOrFail($supplierId);
        $this->authorize('update', $supplier);

        $supplier->verification_status = 'rejected';
        $supplier->rejection_reason = $reason;
        $supplier->verified_at = null;
        $supplier->verified_by = auth()->id(); // ثبت اینکه چه کسی رد کرد
        $supplier->is_verified = false;
        $supplier->save();

        session()->flash('message', 'تامین‌کننده رد شد.');
    }

    // باز کردن مودال رد
    public function openRejectModal($id)
    {
        $this->rejectSupplierId = $id;
        $this->rejectReason = '';
        $this->showRejectModal = true;
    }

    // تایید نهایی رد
    public function confirmReject()
    {
        if (!$this->rejectSupplierId) {
            return;
        }

        $this->rejectSupplier($this->rejectSupplierId, $this->rejectReason);
        $this->showRejectModal = false;
        $this->rejectSupplierId = null;
        $this->rejectReason = '';
    }

    // لغو مودال
    public function cancelReject()
    {
        $this->showRejectModal = false;
        $this->rejectSupplierId = null;
        $this->rejectReason = '';
    }

    // برگرداندن به وضعیت در انتظار بررسی
    public function setPending($supplierId)
    {
        $supplier = Supplier::findOrFail($supplierId);
        $this->authorize('update', $supplier);

        $supplier->verification_status = 'pending';
        $supplier->verified_at = null;
        $supplier->verified_by = null;
        $supplier->rejection_reason = null;
        $supplier->is_verified = false;
        $supplier->save();

        session()->flash('message', 'وضعیت تامین‌کننده به حالت در انتظار تغییر کرد.');
    }

    // حذف ایمن تامین‌کننده
    public function deleteSupplier($id)
    {
        $supplier = Supplier::findOrFail($id);
        $this->authorize('delete', $supplier);

        // بررسی رابطه با محصولات
        if ($supplier->products()->exists()) {
            session()->flash('error', 'امکان حذف این تامین‌کننده وجود ندارد، زیرا دارای محصولات مرتبط است.');
            return;
        }

        $supplier->delete();
        session()->flash('message', 'تامین‌کننده با موفقیت حذف شد.');
    }

    // مدیریت انتخاب همه
    public function updatedSelectAll($value)
    {
        if ($value) {
            $this->selectedSuppliers = $this->getSuppliersQuery()
                ->pluck('id')
                ->map(fn($id) => (string)$id)
                ->toArray();
        } else {
            $this->selectedSuppliers = [];
        }
    }

    // فعال‌سازی گروهی
    public function bulkActivate()
    {
        if (empty($this->selectedSuppliers)) {
            return;
        }

        Supplier::whereIn('id', $this->selectedSuppliers)->update(['is_active' => true]);
        $this->resetBulkSelection();
        session()->flash('message', 'تامین‌کنندگان انتخابی فعال شدند.');
    }

    // غیرفعال‌سازی گروهی
    public function bulkDeactivate()
    {
        if (empty($this->selectedSuppliers)) {
            return;
        }

        Supplier::whereIn('id', $this->selectedSuppliers)->update(['is_active' => false]);
        $this->resetBulkSelection();
        session()->flash('message', 'تامین‌کنندگان انتخابی غیرفعال شدند.');
    }

    // تایید گروهی
    public function bulkVerify()
    {
        if (empty($this->selectedSuppliers)) {
            return;
        }

        Supplier::whereIn('id', $this->selectedSuppliers)->update([
            'verification_status' => 'approved',
            'verified_at' => now(),
            'verified_by' => auth()->id(),
            'is_verified' => true,
            'rejection_reason' => null,
        ]);

        $this->resetBulkSelection();
        session()->flash('message', 'تامین‌کنندگان انتخابی تایید شدند.');
    }

    // رد گروهی
    public function bulkReject()
    {
        if (empty($this->selectedSuppliers)) {
            return;
        }

        Supplier::whereIn('id', $this->selectedSuppliers)->update([
            'verification_status' => 'rejected',
            'verified_at' => null,
            'verified_by' => auth()->id(),
            'is_verified' => false,
        ]);

        $this->resetBulkSelection();
        session()->flash('message', 'تامین‌کنندگان انتخابی رد شدند.');
    }

    private function resetBulkSelection()
    {
        $this->selectedSuppliers = [];
        $this->selectAll = false;
    }

    // کوئری اصلی
    protected function getSuppliersQuery()
    {
        $query = Supplier::query()
            ->with(['user', 'verifier'])
            ->whereHas('user.roles', function ($q) {
                $q->whereIn('name', [
                    \App\Enums\UserRole::SUPPLIER->value,          // 'supplier'
//                    \App\Enums\UserRole::REGULAR_SUPPLIER->value,  // 'regular_supplier'
                ]);
            });

        // جستجو
        if (!empty($this->search)) {
            $query->where(function ($q) {
                $q->where('company_name', 'like', '%' . $this->search . '%')
                    ->orWhere('brand_name', 'like', '%' . $this->search . '%')
                    ->orWhere('mobile', 'like', '%' . $this->search . '%')
                    ->orWhere('phone', 'like', '%' . $this->search . '%')
                    ->orWhereHas('user', function ($userQuery) {
                        $userQuery->where('name', 'like', '%' . $this->search . '%')
                            ->orWhere('email', 'like', '%' . $this->search . '%')
                            ->orWhere('mobile', 'like', '%' . $this->search . '%');
                    });
            });
        }

        // فیلتر وضعیت فعالیت
        if ($this->status !== '') {
            $query->where('is_active', $this->status === 'active');
        }

        // فیلتر وضعیت تایید (استفاده از verification_status جدید)
        if ($this->verification !== '') {
            $query->where('verification_status', $this->verification);
        }

        // فیلتر استان
        if (!empty($this->province)) {
            $query->where('province', $this->province);
        }

        // مرتب‌سازی
        if ($this->sortField === 'user_name') {
            $query->join('users', 'suppliers.user_id', '=', 'users.id')
                ->orderBy('users.name', $this->sortDirection)
                ->select('suppliers.*');
        } else {
            $query->orderBy($this->sortField, $this->sortDirection);
        }

        return $query;
    }

    // خروجی اکسل
    public function exportExcel(): StreamedResponse
    {
        ini_set('memory_limit', '256M');
        set_time_limit(180);

        $fileName = "suppliers_export_" . date('Y-m-d_H-i') . ".xlsx";
        $writer = SimpleExcelWriter::streamDownload($fileName);

        $this->getSuppliersQuery()->chunk(500, function ($suppliers) use ($writer) {
            foreach ($suppliers as $supplier) {
                $writer->addRow([
                    'شناسه' => $supplier->id,
                    'نام شرکت' => $supplier->company_name,
                    'نام برند' => $supplier->brand_name ?? '---',
                    'کاربر متصل' => $supplier->user->name ?? '---',
                    'موبایل تجاری' => $supplier->mobile ?? '---',
                    'تلفن ثابت' => $supplier->phone ?? '---',
                    'استان' => $supplier->province ?? '---',
                    'شهر' => $supplier->city ?? '---',
                    'وضعیت تایید' => match($supplier->verification_status) {
                        'approved' => 'تایید شده',
                        'rejected' => 'رد شده',
                        default => 'در انتظار بررسی',
                    },
                    'وضعیت فعالیت' => $supplier->is_active ? 'فعال' : 'غیرفعال',
                    'تایید توسط' => $supplier->verifier->name ?? '---',
                    'تاریخ تایید' => $supplier->verified_at ? $supplier->verified_at->format('Y-m-d H:i') : '---',
                    'علت رد' => $supplier->rejection_reason ?? '---',
                    'تاریخ ثبت‌نام' => $supplier->created_at ? $supplier->created_at->format('Y-m-d H:i') : '---',
                ]);
            }
        });

        return response()->streamDownload(fn() => $writer->close(), $fileName);
    }

    public function render()
    {
        $provinces = Supplier::whereNotNull('province')
            ->where('province', '!=', '')
            ->distinct()
            ->pluck('province');

        return view('livewire.admin.suppliers.index', [
            'suppliers' => $this->getSuppliersQuery()->paginate(15),
            'provinces' => $provinces,
        ])->layout('components.layouts.admin');
    }
}
