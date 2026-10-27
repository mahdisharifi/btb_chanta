<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ProductCompletionCommissionStatus as CommissionStatus;
use App\Models\OutsourcingSettlement;
use App\Models\OutsourcingSettlementPayment;
use App\Models\ProductCompletionCommission;
use App\Models\User;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class OutsourcingSettlementService
{
    public function __construct(private readonly ProductCompletionAuditService $audit) {}

    public function preview(User $actor, int $userId, string $currency, string $fromDate, string $toDate, bool $includePrevious = true, string $adjustment = '0', string $reason = ''): array
    {
        $this->authorize($actor, 'approve_commission');
        [$from, $until, $adjustment] = $this->validateInput($currency, $fromDate, $toDate, $adjustment, $reason);
        $rows = $this->eligible($userId, $currency, $from, $until, $includePrevious)->get();

        return $this->totals($rows, $from, $adjustment);
    }

    public function create(User $actor, int $userId, string $currency, string $fromDate, string $toDate, bool $includePrevious = true, string $adjustment = '0', string $reason = '', string $token = ''): OutsourcingSettlement
    {
        $this->authorize($actor, 'approve_commission');
        [$from, $until, $adjustment] = $this->validateInput($currency, $fromDate, $toDate, $adjustment, $reason);
        $key = $this->key($token);
        $hash = hash('sha256', json_encode([$actor->id, $userId, $currency, $fromDate, $toDate, $includePrevious, $adjustment, trim($reason)], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($actor, $userId, $currency, $fromDate, $toDate, $from, $until, $includePrevious, $adjustment, $reason, $key, $hash) {
            // Serialize creation per contractor, including retries with the same creation key.
            User::query()->lockForUpdate()->findOrFail($userId);
            $existing = OutsourcingSettlement::query()->where('creation_key', $key)->first();
            if ($existing) {
                $this->ensure(hash_equals($existing->request_hash, $hash), 'شناسه درخواست برای صورتحساب دیگری استفاده شده است.');
                return $existing;
            }
            $rows = $this->eligible($userId, $currency, $from, $until, $includePrevious)
                ->with('task.product')->orderBy('id')->lockForUpdate()->get();
            $this->ensure($rows->isNotEmpty(), 'کارکرد قابل پرداخت و رزرو‌نشده‌ای در این بازه وجود ندارد.');
            $totals = $this->totals($rows, $from, $adjustment);
            $this->ensure(BigDecimal::of($totals['net_amount'])->isPositive(), 'مبلغ نهایی صورتحساب باید بیشتر از صفر باشد.');
            $statement = OutsourcingSettlement::query()->create([
                'user_id' => $userId, 'currency' => $currency, 'from_date' => $fromDate, 'to_date' => $toDate,
                'include_previous' => $includePrevious, 'gross_amount' => $totals['gross_amount'],
                'previous_amount' => $totals['previous_amount'], 'period_amount' => $totals['period_amount'],
                'adjustment_amount' => $adjustment, 'adjustment_reason' => trim($reason) ?: null,
                'net_amount' => $totals['net_amount'], 'status' => 'draft', 'created_by' => $actor->id,
                'creation_key' => $key, 'request_hash' => $hash,
            ]);
            foreach ($rows as $commission) {
                $snapshot = $commission->only(['base_amount', 'revision_amount', 'bonus_amount', 'penalty_amount', 'adjustment_amount', 'total_amount', 'currency', 'breakdown_json']);
                $snapshot += ['task_public_id' => $commission->task?->public_id, 'product_title' => $commission->task?->product?->title,
                    'commission_public_id' => $commission->public_id, 'calculated_at' => $commission->calculated_at?->toIso8601String()];
                $statement->items()->create(['commission_id' => $commission->id, 'amount' => $commission->total_amount,
                    'is_previous' => $commission->calculated_at->lt($from), 'snapshot_json' => $snapshot]);
                $commission->forceFill(['settlement_id' => $statement->id])->save();
            }
            $this->record($actor, $statement, 'settlement_created');
            return $statement;
        }, 3);
    }

    public function approve(User $actor, OutsourcingSettlement $settlement): OutsourcingSettlement
    {
        $this->authorize($actor, 'approve_commission');
        return DB::transaction(function () use ($actor, $settlement) {
            $locked = OutsourcingSettlement::query()->lockForUpdate()->findOrFail($settlement->id);
            $this->ensure($locked->status === 'draft', 'فقط پیش‌نویس قابل تأیید است.');
            $this->lockedCommissions($locked);
            $locked->forceFill(['status' => 'approved', 'approved_by' => $actor->id, 'approved_at' => now()])->save();
            $this->record($actor, $locked, 'settlement_approved');
            return $locked;
        }, 3);
    }

    public function cancel(User $actor, OutsourcingSettlement $settlement, string $reason): OutsourcingSettlement
    {
        $this->authorize($actor, 'approve_commission');
        Validator::make(['reason' => trim($reason)], ['reason' => ['required', 'string', 'max:2000']])->validate();
        return DB::transaction(function () use ($actor, $settlement, $reason) {
            $locked = OutsourcingSettlement::query()->lockForUpdate()->findOrFail($settlement->id);
            $this->ensure(in_array($locked->status, ['draft', 'approved'], true) && ! $locked->payments()->exists(), 'صورتحساب پرداخت‌شده یا لغوشده قابل لغو نیست.');
            $rows = $this->lockedCommissions($locked);
            foreach ($rows as $commission) {
                $commission->forceFill(['settlement_id' => null])->save();
            }
            $locked->forceFill(['status' => 'cancelled', 'cancelled_by' => $actor->id, 'cancelled_at' => now(), 'cancel_reason' => trim($reason)])->save();
            $this->record($actor, $locked, 'settlement_cancelled');
            return $locked;
        }, 3);
    }

    public function pay(User $actor, OutsourcingSettlement $settlement, string $reference, string $token, string $paidOn): OutsourcingSettlement
    {
        $this->authorize($actor, 'pay_commission');
        $this->validatePaymentDate($paidOn);
        $reference = trim($reference);
        Validator::make(compact('reference'), ['reference' => ['required', 'string', 'max:191', 'regex:/^[\pL\pN\-\/_\.]+$/u']], [
            'reference.required' => 'شماره پیگیری پرداخت الزامی است.',
            'reference.regex' => 'شماره پیگیری نباید فاصله یا نویسه نامعتبر داشته باشد.',
            'reference.max' => 'شماره پیگیری بیش از حد طولانی است.',
        ])->validate();
        $key = $this->key($token);

        return DB::transaction(function () use ($actor, $settlement, $reference, $key, $paidOn) {
            $locked = OutsourcingSettlement::query()->lockForUpdate()->findOrFail($settlement->id);
            $prior = $locked->payments()->first();
            if ($prior) {
                $this->ensure($locked->status === 'paid' && hash_equals($prior->idempotency_key, $key)
                    && $prior->reference === $reference && (int) $prior->paid_by === (int) $actor->id
                    && $prior->paid_on?->format('Y-m-d') === $paidOn,
                    'پرداخت این صورتحساب قبلاً ثبت شده است.');
                return $locked;
            }
            $this->ensure($locked->status === 'approved', 'ثبت پرداخت فقط برای صورتحساب تأییدشده مجاز است.');
            $rows = $this->lockedCommissions($locked);
            if (config('product_completion.commission.require_distinct_approver_and_payer', true)) {
                $this->ensure((int) $locked->approved_by !== (int) $actor->id
                    && ! $rows->contains(fn ($row) => (int) $row->approved_by === (int) $actor->id),
                    'طبق تنظیمات مالی، ثبت‌کننده پرداخت باید با تأییدکننده صورتحساب و کارکردها متفاوت باشد.');
            }
            $this->ensure(! OutsourcingSettlementPayment::query()->where('reference', $reference)->orWhere('idempotency_key', $key)->exists()
                && ! ProductCompletionCommission::query()->where('transaction_id', $reference)->exists(),
                'شماره پیگیری یا شناسه درخواست قبلاً استفاده شده است.');
            // Unique constraints arbitrate concurrent receipt submissions across statements.
            $inserted = DB::table('outsourcing_settlement_payments')->insertOrIgnore([
                'settlement_id' => $locked->id, 'reference' => $reference, 'idempotency_key' => $key,
                'amount' => $locked->net_amount, 'paid_by' => $actor->id, 'created_at' => now(), 'updated_at' => now(),
                'paid_on' => $paidOn,
            ]);
            $this->ensure($inserted === 1, 'این رسید پرداخت قبلاً ثبت شده است.');
            foreach ($rows as $commission) {
                $commission->forceFill(['status' => CommissionStatus::PAID, 'paid_by' => $actor->id, 'paid_at' => now()])->save();
            }
            $locked->forceFill(['status' => 'paid', 'paid_at' => now()])->save();
            $this->record($actor, $locked, 'settlement_paid', ['paid_on' => $paidOn, 'reference' => $reference]);
            DB::afterCommit(static function () use ($rows): void {
                foreach ($rows as $commission) {
                    \App\Events\CommissionPaid::dispatch($commission);
                }
            });
            return $locked;
        }, 3);
    }

    public function correctPaymentDate(User $actor, OutsourcingSettlement $settlement, string $paidOn, string $reason): OutsourcingSettlement
    {
        $this->authorize($actor, 'pay_commission');
        Gate::forUser($actor)->authorize('outsourcing.adjust_commission');
        $this->validatePaymentDate($paidOn);
        $reason = trim($reason);
        Validator::make(compact('reason'), ['reason' => ['required', 'string', 'max:2000']], [
            'reason.required' => 'دلیل اصلاح تاریخ پرداخت الزامی است.',
        ])->validate();

        return DB::transaction(function () use ($actor, $settlement, $paidOn, $reason) {
            $locked = OutsourcingSettlement::query()->lockForUpdate()->findOrFail($settlement->id);
            $this->ensure($locked->status === 'paid', 'فقط تاریخ رسید صورتحساب پرداخت‌شده قابل اصلاح است.');
            $payment = $locked->payments()->lockForUpdate()->firstOrFail();
            $before = $payment->paid_on?->format('Y-m-d');
            $this->ensure($before !== $paidOn, 'تاریخ جدید با تاریخ ثبت‌شده یکسان است.');
            $payment->forceFill(['paid_on' => $paidOn])->save();
            $this->record($actor, $locked, 'settlement_payment_date_corrected', [
                'payment_id' => $payment->id, 'previous_paid_on' => $before, 'paid_on' => $paidOn, 'reason' => $reason,
            ]);

            return $locked;
        }, 3);
    }

    private function authorize(User $actor, string $permission): void
    {
        Gate::forUser($actor)->authorize('outsourcing.view_all_commissions');
        Gate::forUser($actor)->authorize('outsourcing.'.$permission);
    }

    private function validateInput(string $currency, string $fromDate, string $toDate, string $adjustment, string $reason): array
    {
        Validator::make(compact('currency', 'fromDate', 'toDate', 'adjustment', 'reason'), [
            'currency' => ['required', 'regex:/^[A-Z]{3}$/'], 'fromDate' => ['required', 'date_format:Y-m-d'],
            'toDate' => ['required', 'date_format:Y-m-d', 'after_or_equal:fromDate'],
            'adjustment' => ['required', 'regex:/^-?\d{1,16}(?:\.\d{1,2})?$/'], 'reason' => ['nullable', 'string', 'max:2000'],
        ])->validate();
        $amount = $this->amount($adjustment);
        $this->ensure(BigDecimal::of($amount)->isZero() || trim($reason) !== '', 'دلیل تعدیل صورتحساب الزامی است.');
        $timezone = config('app.timezone');
        return [CarbonImmutable::parse($fromDate, 'Asia/Tehran')->startOfDay()->setTimezone($timezone),
            CarbonImmutable::parse($toDate, 'Asia/Tehran')->startOfDay()->addDay()->setTimezone($timezone), $amount];
    }

    private function eligible(int $userId, string $currency, CarbonImmutable $from, CarbonImmutable $until, bool $includePrevious): Builder
    {
        return ProductCompletionCommission::query()->where('user_id', $userId)->where('currency', $currency)
            ->where('status', CommissionStatus::PAYABLE)->whereNull('settlement_id')
            ->whereNotNull('approved_at')->whereNotNull('approved_by')
            ->where('payable_at', '<=', now())->where('calculated_at', '<', $until)->where('calculated_at', '<=', now())
            ->when(! $includePrevious, fn ($query) => $query->where('calculated_at', '>=', $from));
    }

    private function totals(Collection $rows, CarbonImmutable $from, string $adjustment): array
    {
        $previous = BigDecimal::zero();
        $period = BigDecimal::zero();
        foreach ($rows as $row) {
            $this->ensure(! BigDecimal::of($row->total_amount)->isNegative(), 'کارکرد منفی قابل تسویه نیست.');
            if ($row->calculated_at->lt($from)) {
                $previous = $previous->plus($row->total_amount);
            } else {
                $period = $period->plus($row->total_amount);
            }
        }
        $gross = $previous->plus($period);
        return ['count' => $rows->count(), 'previous_amount' => $this->amount((string) $previous),
            'period_amount' => $this->amount((string) $period), 'gross_amount' => $this->amount((string) $gross),
            'adjustment_amount' => $adjustment, 'net_amount' => $this->amount((string) $gross->plus($adjustment))];
    }

    private function lockedCommissions(OutsourcingSettlement $settlement): Collection
    {
        $items = $settlement->items()->get()->keyBy('commission_id');
        $rows = ProductCompletionCommission::query()->whereIn('id', $items->keys())->orderBy('id')->lockForUpdate()->get();
        $this->ensure($rows->isNotEmpty() && $rows->count() === $items->count(), 'ردیف‌های صورتحساب ناقص است.');
        $sum = BigDecimal::zero();
        foreach ($rows as $row) {
            $this->ensure((int) $row->settlement_id === (int) $settlement->id && (int) $row->user_id === (int) $settlement->user_id
                && $row->currency === $settlement->currency && $row->status === CommissionStatus::PAYABLE
                && $row->payable_at !== null && $row->payable_at->lte(now())
                && BigDecimal::of($row->total_amount)->isEqualTo($items[$row->id]->amount),
                'وضعیت یا مبلغ یکی از کارکردهای صورتحساب تغییر کرده است.');
            $sum = $sum->plus($row->total_amount);
        }
        $this->ensure($sum->isEqualTo($settlement->gross_amount)
            && $sum->plus($settlement->adjustment_amount)->isEqualTo($settlement->net_amount)
            && BigDecimal::of($settlement->net_amount)->isPositive(), 'جمع صورتحساب با ردیف‌ها مطابقت ندارد.');
        return $rows;
    }

    private function amount(string $amount): string
    {
        $number = BigDecimal::of($amount)->toScale(2);
        $this->ensure($number->abs()->isLessThanOrEqualTo('9999999999999999.99'), 'مبلغ صورتحساب از محدوده مجاز بیشتر است.');
        return (string) $number;
    }

    private function key(string $token): string
    {
        Validator::make(compact('token'), ['token' => ['required', 'uuid']])->validate();
        return hash('sha256', $token);
    }

    private function ensure(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['settlement' => $message]);
        }
    }

    private function validatePaymentDate(string $paidOn): void
    {
        Validator::make(['paymentDate' => $paidOn], [
            'paymentDate' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now('Asia/Tehran')->format('Y-m-d')],
        ], [
            'paymentDate.required' => 'تاریخ واقعی پرداخت الزامی است.',
            'paymentDate.date_format' => 'تاریخ پرداخت معتبر نیست.',
            'paymentDate.before_or_equal' => 'تاریخ پرداخت نمی‌تواند در آینده باشد.',
        ])->validate();
    }

    private function record(User $actor, OutsourcingSettlement $settlement, string $action, array $extra = []): void
    {
        $this->audit->record(action: $action, actor: $actor, entity: $settlement,
            metadata: [...$settlement->only(['public_id', 'user_id', 'status', 'currency', 'gross_amount', 'adjustment_amount', 'adjustment_reason', 'net_amount', 'cancel_reason']), ...$extra]);
    }
}
