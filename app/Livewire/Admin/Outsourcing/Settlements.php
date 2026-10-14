<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Outsourcing;

use App\Models\OutsourcingSettlement;
use App\Models\ProductCompletionCommission;
use App\Models\User;
use App\Services\OutsourcingSettlementService;
use App\Support\SettlementDisplay;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.admin')]
class Settlements extends Component
{
    use WithPagination;

    public string $contractorId = '';
    public string $contractorSearch = '';
    public string $currency = 'IRR';
    public string $fromDate = '';
    public string $toDate = '';
    public bool $includePrevious = true;
    public string $adjustment = '0';
    public string $adjustmentReason = '';
    public string $status = '';
    public string $reference = '';
    public string $paymentDate = '';
    public string $paymentDateReason = '';
    public string $cancelReason = '';
    public string $notice = '';

    #[Locked]
    public ?string $selectedId = null;
    #[Locked]
    public ?array $preview = null;
    #[Locked]
    public string $createToken = '';
    #[Locked]
    public string $paymentToken = '';

    public function mount(): void
    {
        Gate::authorize('outsourcing.view_all_commissions');
        $today = jdate(now('Asia/Tehran'));
        $this->fromDate = $today->format('Y/m').'/01';
        $this->toDate = $today->format('Y/m/d');
        $this->createToken = (string) Str::uuid();
        $this->paymentToken = (string) Str::uuid();
        $this->paymentDate = $today->format('Y/m/d');
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['contractorId', 'currency', 'fromDate', 'toDate', 'includePrevious', 'adjustment', 'adjustmentReason'], true)) {
            $this->preview = null;
        }
        if ($property === 'status') {
            $this->resetPage();
        }
    }

    private function inputs(): array
    {
        $this->validate([
            'contractorId' => ['required', 'integer', 'exists:users,id'],
            'currency' => ['required', 'regex:/^[A-Z]{3}$/'],
            'adjustment' => ['required', 'string', 'max:24'],
            'adjustmentReason' => ['nullable', 'string', 'max:2000'],
        ]);

        return [
            'actor' => auth()->user(),
            'userId' => (int) $this->contractorId,
            'currency' => $this->currency,
            'fromDate' => SettlementDisplay::gregorian($this->fromDate, 'fromDate'),
            'toDate' => SettlementDisplay::gregorian($this->toDate, 'toDate'),
            'includePrevious' => $this->includePrevious,
            'adjustment' => SettlementDisplay::latin($this->adjustment),
            'reason' => $this->adjustmentReason,
        ];
    }

    public function previewSettlement(OutsourcingSettlementService $service): void
    {
        $this->resetValidation();
        $this->preview = $service->preview(...$this->inputs());
    }

    public function create(OutsourcingSettlementService $service): void
    {
        $this->resetValidation();
        $settlement = $service->create(...[...$this->inputs(), 'token' => $this->createToken]);
        $this->createToken = (string) Str::uuid();
        $this->preview = null;
        $this->select($settlement->public_id);
        $this->notice = 'صورتحساب ایجاد شد؛ ریز کارکرد را بررسی و سپس تأیید کنید.';
        $this->resetPage();
    }

    public function select(string $publicId): void
    {
        Gate::authorize('outsourcing.view_all_commissions');
        $settlement = OutsourcingSettlement::query()->where('public_id', $publicId)->firstOrFail();
        $this->selectedId = $publicId;
        $this->reference = '';
        $this->paymentDateReason = '';
        $this->paymentDate = jdate(now('Asia/Tehran'))->format('Y/m/d');
        $recordedDate = $settlement->payments()->first()?->paid_on;
        if ($recordedDate !== null) {
            $this->paymentDate = jdate($recordedDate)->format('Y/m/d');
        }
        $this->cancelReason = '';
        $this->paymentToken = (string) Str::uuid();
        $this->resetValidation();
        $this->resetPage('itemsPage');
    }

    public function approve(OutsourcingSettlementService $service): void
    {
        $service->approve(auth()->user(), $this->selected());
        $this->notice = 'صورتحساب تأیید شد. مبلغ و ردیف‌های آن قفل هستند.';
    }

    public function cancel(OutsourcingSettlementService $service): void
    {
        $service->cancel(auth()->user(), $this->selected(), $this->cancelReason);
        $this->notice = 'صورتحساب لغو شد؛ کارکردهای آن دوباره قابل انتخاب هستند.';
    }

    public function pay(OutsourcingSettlementService $service): void
    {
        $service->pay(
            auth()->user(), $this->selected(), SettlementDisplay::latin($this->reference), $this->paymentToken,
            SettlementDisplay::gregorian($this->paymentDate, 'paymentDate'),
        );
        $this->notice = 'رسید پرداخت کامل صورتحساب ثبت شد.';
    }

    public function correctPaymentDate(OutsourcingSettlementService $service): void
    {
        $service->correctPaymentDate(
            auth()->user(), $this->selected(),
            SettlementDisplay::gregorian($this->paymentDate, 'paymentDate'), $this->paymentDateReason,
        );
        $this->paymentDateReason = '';
        $this->notice = 'تاریخ واقعی پرداخت اصلاح شد و سابقه تغییر ثبت شد.';
    }

    private function selected(): OutsourcingSettlement
    {
        Gate::authorize('outsourcing.view_all_commissions');

        return OutsourcingSettlement::query()->where('public_id', $this->selectedId)->firstOrFail();
    }

    public function render()
    {
        Gate::authorize('outsourcing.view_all_commissions');
        $contractors = User::query()
            ->whereIn('id', ProductCompletionCommission::query()->select('user_id'))
            ->when($this->contractorSearch !== '', fn ($query) => $query->where('name', 'like', '%'.mb_substr($this->contractorSearch, 0, 100).'%'))
            ->orderBy('name')->limit(50)->get(['id', 'name']);
        if ($this->contractorId !== '' && ! $contractors->contains('id', (int) $this->contractorId)) {
            $selectedUser = User::query()->whereIn('id', ProductCompletionCommission::query()->select('user_id'))->find($this->contractorId, ['id', 'name']);
            if ($selectedUser) {
                $contractors->prepend($selectedUser);
            }
        }

        $selected = $this->selectedId === null ? null : $this->selected()->load(['user:id,name', 'payments']);

        return view('livewire.admin.outsourcing.settlements', [
            'contractors' => $contractors,
            'currencies' => ProductCompletionCommission::query()->distinct()->orderBy('currency')->pluck('currency'),
            'settlements' => OutsourcingSettlement::query()->with('user:id,name')->withCount('items')
                ->when(in_array($this->status, ['draft', 'approved', 'paid', 'cancelled'], true), fn ($query) => $query->where('status', $this->status))
                ->latest('id')->paginate(15),
            'selected' => $selected,
            'statementItems' => $selected?->items()->orderBy('id')->paginate(50, ['*'], 'itemsPage'),
        ]);
    }
}
