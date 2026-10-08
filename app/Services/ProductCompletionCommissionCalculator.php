<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CommissionContentType;
use App\Enums\CommissionCalculationType;
use App\Enums\ProductCompletionRevisionClassification;
use App\Enums\ProductCompletionRevisionPolicy;
use App\Enums\ProductCompletionRevisionStatus;
use App\Models\ProductCompletionSubmission;
use App\Models\ProductCompletionTask;
use InvalidArgumentException;

class ProductCompletionCommissionCalculator
{
    public function __construct(
        private readonly ProductCompletionCommissionUnitCounter $unitCounter,
    ) {
    }

    public function calculate(
        ProductCompletionTask $task,
        ProductCompletionSubmission $submission,
        string|int|float $bonus = '0',
        string|int|float $penalty = '0',
        string|int|float $adjustment = '0',
    ): array {
        $snapshot = $task->commission_rule_snapshot_json;

        if ((int) ($snapshot['pricing_schema_version'] ?? 1) >= 2) {
            return $this->calculatePerContent(
                $task,
                $submission,
                $snapshot,
                $bonus,
                $penalty,
                $adjustment,
            );
        }

        return $this->calculateLegacy(
            $task,
            $submission,
            $snapshot,
            $bonus,
            $penalty,
            $adjustment,
        );
    }

    private function calculatePerContent(
        ProductCompletionTask $task,
        ProductCompletionSubmission $submission,
        array $snapshot,
        string|int|float $bonus,
        string|int|float $penalty,
        string|int|float $adjustment,
    ): array {
        $scale = (int) config('product_completion.commission.decimal_scale', 2);
        $baseUnits = $this->unitCounter->compare(
            $task->initial_snapshot_json ?? [],
            $submission->payload_json ?? [],
        );
        $basePricing = $this->priceUnits($snapshot, $baseUnits, $scale);
        $revisionPricing = $this->priceRevisions($task, $snapshot, $scale);
        $rawBaseMinor = $basePricing['total_minor'];
        $taskRateBasisPoints = $this->percentageToBasisPoints(
            $task->commission_rate_percentage ?? '100.00'
        );
        $baseMinor = $this->percentageOf($rawBaseMinor, $taskRateBasisPoints);
        $revisionMinor = $revisionPricing['total_minor'];
        $bonusMinor = $this->toMinor($bonus, $scale);
        $penaltyMinor = $this->toMinor($penalty, $scale);
        $adjustmentMinor = $this->toMinor($adjustment, $scale);
        $totalMinor = max(
            0,
            $baseMinor + $revisionMinor + $bonusMinor + $adjustmentMinor - $penaltyMinor
        );
        $maxAmount = $snapshot['max_amount'] ?? null;

        if ($maxAmount !== null && $maxAmount !== '') {
            $totalMinor = min($totalMinor, $this->toMinor($maxAmount, $scale));
        }

        return [
            'base_amount' => $this->fromMinor($baseMinor, $scale),
            'revision_amount' => $this->fromMinor($revisionMinor, $scale),
            'approved_items_amount' => $this->fromMinor(0, $scale),
            'bonus_amount' => $this->fromMinor($bonusMinor, $scale),
            'penalty_amount' => $this->fromMinor($penaltyMinor, $scale),
            'adjustment_amount' => $this->fromMinor($adjustmentMinor, $scale),
            'total_amount' => $this->fromMinor($totalMinor, $scale),
            'breakdown' => [
                'pricing_schema_version' => 2,
                'rule_id' => $snapshot['rule_id'] ?? null,
                'rule_version' => $snapshot['rule_version'] ?? null,
                'calculation_type' => 'per_content_type',
                'revision_policy' => $snapshot['revision_policy'] ?? null,
                'revision_percentage' => (string) ($snapshot['revision_percentage'] ?? '0.00'),
                'task_rate_percentage' => $this->basisPointsToPercentage($taskRateBasisPoints),
                'amount_before_task_rate' => $this->fromMinor($rawBaseMinor, $scale),
                'approved_item_count' => $basePricing['payable_unit_count'],
                'base_units' => $basePricing['breakdown'],
                'base_unit_keys' => $baseUnits,
                'revision_count' => $revisionPricing['revision_count'],
                'revisions' => $revisionPricing['breakdown'],
            ],
        ];
    }

    private function calculateLegacy(
        ProductCompletionTask $task,
        ProductCompletionSubmission $submission,
        array $snapshot,
        string|int|float $bonus,
        string|int|float $penalty,
        string|int|float $adjustment,
    ): array {
        $scale = (int) config('product_completion.commission.decimal_scale', 2);
        $revisionPolicy = ProductCompletionRevisionPolicy::from(
            (string) ($snapshot['revision_policy'] ?? ProductCompletionRevisionPolicy::NONE->value)
        );
        $calculationType = CommissionCalculationType::from(
            (string) ($snapshot['calculation_type'] ?? CommissionCalculationType::PER_TASK->value)
        );
        $revisionCount = $this->commissionableRevisionCount($task, $revisionPolicy);
        $approvedItemCount = $calculationType === CommissionCalculationType::PER_FIELD_GROUP
            ? count($submission->diff_json ?? [])
            : 0;
        $taskRateBasisPoints = $this->percentageToBasisPoints(
            $task->commission_rate_percentage ?? '100.00'
        );
        $rawBaseMinor = $this->toMinor($snapshot['base_amount'] ?? '0', $scale);
        $rawApprovedItemsMinor = $this->toMinor(
            $snapshot['per_approved_item_amount'] ?? '0',
            $scale
        ) * $approvedItemCount;
        $baseMinor = $this->percentageOf($rawBaseMinor, $taskRateBasisPoints);
        $approvedItemsMinor = $this->percentageOf($rawApprovedItemsMinor, $taskRateBasisPoints);
        $revisionMinor = $this->toMinor($snapshot['per_revision_amount'] ?? '0', $scale) * $revisionCount;
        $bonusMinor = $this->toMinor($bonus, $scale);
        $penaltyMinor = $this->toMinor($penalty, $scale);
        $adjustmentMinor = $this->toMinor($adjustment, $scale);
        $totalMinor = max(
            0,
            $baseMinor + $revisionMinor + $approvedItemsMinor + $bonusMinor + $adjustmentMinor - $penaltyMinor
        );
        $maxAmount = $snapshot['max_amount'] ?? null;

        if ($maxAmount !== null && $maxAmount !== '') {
            $totalMinor = min($totalMinor, $this->toMinor($maxAmount, $scale));
        }

        return [
            'base_amount' => $this->fromMinor($baseMinor, $scale),
            'revision_amount' => $this->fromMinor($revisionMinor, $scale),
            'approved_items_amount' => $this->fromMinor($approvedItemsMinor, $scale),
            'bonus_amount' => $this->fromMinor($bonusMinor, $scale),
            'penalty_amount' => $this->fromMinor($penaltyMinor, $scale),
            'adjustment_amount' => $this->fromMinor($adjustmentMinor, $scale),
            'total_amount' => $this->fromMinor($totalMinor, $scale),
            'breakdown' => [
                'rule_id' => $snapshot['rule_id'] ?? null,
                'rule_version' => $snapshot['rule_version'] ?? null,
                'calculation_type' => $calculationType->value,
                'revision_policy' => $revisionPolicy->value,
                'task_rate_percentage' => $this->basisPointsToPercentage($taskRateBasisPoints),
                'amount_before_task_rate' => $this->fromMinor($rawBaseMinor + $rawApprovedItemsMinor, $scale),
                'commissionable_revision_count' => $revisionCount,
                'approved_item_count' => $approvedItemCount,
                'approved_items_amount' => $this->fromMinor($approvedItemsMinor, $scale),
            ],
        ];
    }

    /**
     * @param array<string, array<int, string>> $units
     * @return array{total_minor: int, payable_unit_count: int, breakdown: array<string, array<string, int|string|bool>>}
     */
    private function priceUnits(array $snapshot, array $units, int $scale): array
    {
        $totalMinor = 0;
        $payableUnitCount = 0;
        $breakdown = [];
        $seenTypes = [];

        foreach ((array) ($snapshot['items'] ?? []) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $contentType = CommissionContentType::tryFrom((string) ($item['content_type'] ?? ''));

            if ($contentType === null || isset($seenTypes[$contentType->value])) {
                continue;
            }

            $seenTypes[$contentType->value] = true;
            $enabled = ($item['is_enabled'] ?? false) === true || ($item['is_enabled'] ?? null) === 1;
            $actualUnits = count($units[$contentType->value] ?? []);
            $maxPayableUnits = max(0, (int) ($item['max_payable_units'] ?? 0));
            $payableUnits = $enabled ? min($actualUnits, $maxPayableUnits) : 0;
            $unitAmountMinor = $enabled
                ? $this->toMinor($item['unit_amount'] ?? '0', $scale)
                : 0;
            $amountMinor = $unitAmountMinor * $payableUnits;
            $totalMinor += $amountMinor;
            $payableUnitCount += $payableUnits;
            $breakdown[$contentType->value] = [
                'enabled' => $enabled,
                'actual_units' => $actualUnits,
                'max_payable_units' => $maxPayableUnits,
                'payable_units' => $payableUnits,
                'unit_amount' => $this->fromMinor($unitAmountMinor, $scale),
                'amount' => $this->fromMinor($amountMinor, $scale),
            ];
        }

        return [
            'total_minor' => $totalMinor,
            'payable_unit_count' => $payableUnitCount,
            'breakdown' => $breakdown,
        ];
    }

    /** @return array{total_minor: int, revision_count: int, breakdown: array<int, array<string, mixed>>} */
    private function priceRevisions(ProductCompletionTask $task, array $snapshot, int $scale): array
    {
        $policy = ProductCompletionRevisionPolicy::from(
            (string) ($snapshot['revision_policy'] ?? ProductCompletionRevisionPolicy::NONE->value)
        );

        if ($policy === ProductCompletionRevisionPolicy::NONE) {
            return ['total_minor' => 0, 'revision_count' => 0, 'breakdown' => []];
        }

        $query = $task->revisions()
            ->where('status', ProductCompletionRevisionStatus::RESOLVED)
            ->where('is_commissionable', true)
            ->with([
                'submission:id,payload_json',
                'resolvedBySubmission:id,payload_json',
            ]);

        if ($policy === ProductCompletionRevisionPolicy::SCOPE_CHANGE_ONLY) {
            $query->where('classification', ProductCompletionRevisionClassification::SUPPLIER_SCOPE_CHANGE);
        }

        $percentageBasisPoints = $this->percentageToBasisPoints(
            $snapshot['revision_percentage'] ?? '0'
        );
        $totalMinor = 0;
        $breakdown = [];

        foreach ($query->get() as $revision) {
            if ($revision->submission === null || $revision->resolvedBySubmission === null) {
                continue;
            }

            $units = is_array($revision->changed_units_json)
                && (int) $revision->pricing_schema_version >= 2
                    ? $revision->changed_units_json
                    : $this->unitCounter->compare(
                        $revision->submission->payload_json ?? [],
                        $revision->resolvedBySubmission->payload_json ?? [],
                    );
            $unitPricing = $this->priceUnits($snapshot, $units, $scale);
            $revisionMinor = $this->percentageOf(
                $unitPricing['total_minor'],
                $percentageBasisPoints,
            );
            $totalMinor += $revisionMinor;
            $breakdown[] = [
                'revision_id' => $revision->getKey(),
                'revision_no' => $revision->revision_no,
                'changed_units' => $units,
                'unit_pricing' => $unitPricing['breakdown'],
                'amount_before_percentage' => $this->fromMinor($unitPricing['total_minor'], $scale),
                'revision_percentage' => $this->basisPointsToPercentage($percentageBasisPoints),
                'amount' => $this->fromMinor($revisionMinor, $scale),
            ];
        }

        return [
            'total_minor' => $totalMinor,
            'revision_count' => count($breakdown),
            'breakdown' => $breakdown,
        ];
    }

    public function recalculateTotal(
        string|int|float $base,
        string|int|float $revision,
        string|int|float $approvedItems,
        string|int|float $bonus,
        string|int|float $penalty,
        string|int|float $adjustment,
        string|int|float|null $maxAmount = null,
    ): string {
        $scale = (int) config('product_completion.commission.decimal_scale', 2);
        $total = max(
            0,
            $this->toMinor($base, $scale)
                + $this->toMinor($revision, $scale)
                + $this->toMinor($approvedItems, $scale)
                + $this->toMinor($bonus, $scale)
                + $this->toMinor($adjustment, $scale)
                - $this->toMinor($penalty, $scale)
        );

        if ($maxAmount !== null && $maxAmount !== '') {
            $total = min($total, $this->toMinor($maxAmount, $scale));
        }

        return $this->fromMinor($total, $scale);
    }

    private function commissionableRevisionCount(
        ProductCompletionTask $task,
        ProductCompletionRevisionPolicy $policy,
    ): int {
        if ($policy === ProductCompletionRevisionPolicy::NONE) {
            return 0;
        }

        $query = $task->revisions()
            ->where('status', ProductCompletionRevisionStatus::RESOLVED)
            ->where('is_commissionable', true);

        if ($policy === ProductCompletionRevisionPolicy::SCOPE_CHANGE_ONLY) {
            $query->where('classification', ProductCompletionRevisionClassification::SUPPLIER_SCOPE_CHANGE);
        }

        return $query->count();
    }

    private function percentageToBasisPoints(string|int|float $percentage): int
    {
        $value = trim((string) $percentage);

        if (preg_match('/^\d{1,3}(?:\.\d{1,2})?$/', $value) !== 1) {
            throw new InvalidArgumentException('درصد اصلاح نامعتبر است.');
        }

        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $basisPoints = ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');

        if ($basisPoints > 10000) {
            throw new InvalidArgumentException('درصد اصلاح نمی‌تواند بیشتر از صد باشد.');
        }

        return $basisPoints;
    }

    private function percentageOf(int $amountMinor, int $percentageBasisPoints): int
    {
        return intdiv(($amountMinor * $percentageBasisPoints) + 5000, 10000);
    }

    private function basisPointsToPercentage(int $basisPoints): string
    {
        return intdiv($basisPoints, 100).'.'.str_pad(
            (string) ($basisPoints % 100),
            2,
            '0',
            STR_PAD_LEFT,
        );
    }

    private function toMinor(string|int|float $amount, int $scale): int
    {
        $value = trim((string) $amount);

        if (preg_match('/^-?\d+(?:\.\d+)?$/', $value) !== 1) {
            throw new InvalidArgumentException('مبلغ مالی نامعتبر است.');
        }

        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '-');
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');

        if (strlen($fraction) > $scale) {
            throw new InvalidArgumentException("مبلغ نباید بیشتر از {$scale} رقم اعشار داشته باشد.");
        }

        $fraction = str_pad($fraction, $scale, '0');
        $minor = ((int) $whole * (10 ** $scale)) + (int) $fraction;

        return $negative ? -$minor : $minor;
    }

    private function fromMinor(int $amount, int $scale): string
    {
        $negative = $amount < 0;
        $amount = abs($amount);
        $factor = 10 ** $scale;
        $value = intdiv($amount, $factor).'.'.str_pad(
            (string) ($amount % $factor),
            $scale,
            '0',
            STR_PAD_LEFT,
        );

        return $negative ? '-'.$value : $value;
    }
}
