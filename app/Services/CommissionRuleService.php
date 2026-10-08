<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CommissionCalculationType;
use App\Enums\CommissionContentType;
use App\Enums\ProductCompletionRevisionPolicy;
use App\Enums\ProductCompletionTaskType;
use App\Models\CommissionRule;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Closure;

class CommissionRuleService
{
    public function __construct(
        private readonly ProductCompletionInputSanitizer $sanitizer,
        private readonly ProductCompletionAuditService $auditService,
    ) {
    }

    public function createVersion(User $actor, array $input): CommissionRule
    {
        Gate::forUser($actor)->authorize('create', CommissionRule::class);

        $validated = Validator::make($input, [
            'name' => ['required', 'string', 'max:255', 'regex:/^[\p{L}\p{N}\s\x{200C}_-]+$/u'],
            'task_type' => ['required', Rule::enum(ProductCompletionTaskType::class)],
            'calculation_type' => ['required', Rule::enum(CommissionCalculationType::class)],
            'revision_policy' => ['required', Rule::enum(ProductCompletionRevisionPolicy::class)],
            'base_amount' => ['required', 'regex:/^\d{1,12}(?:\.\d{1,2})?$/'],
            'per_revision_amount' => ['required', 'regex:/^\d{1,12}(?:\.\d{1,2})?$/'],
            'per_approved_item_amount' => ['required', 'regex:/^\d{1,12}(?:\.\d{1,2})?$/'],
            'max_amount' => ['nullable', 'regex:/^\d{1,12}(?:\.\d{1,2})?$/'],
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'conditions_json' => ['nullable', 'array'],
            'effective_from' => ['nullable', 'date'],
            'effective_until' => ['nullable', 'date', 'after:effective_from'],
        ], [
            'name.required' => 'نام قانون پورسانت الزامی است.',
            'name.regex' => 'نام قانون پورسانت شامل کاراکتر غیرمجاز است.',
            '*.regex' => 'فرمت مبلغ نامعتبر است یا بیش از دو رقم اعشار دارد.',
            'effective_until.after' => 'تاریخ پایان باید بعد از تاریخ شروع باشد.',
        ])->validate();

        $validated['name'] = $this->sanitizer->plainText($validated['name'], 255);

        return DB::transaction(function () use ($actor, $validated): CommissionRule {
            $lastVersion = (int) (CommissionRule::query()
                ->where('name', $validated['name'])
                ->orderByDesc('version')
                ->lockForUpdate()
                ->value('version') ?? 0);
            $rule = CommissionRule::query()->create([
                'name' => $validated['name'],
                'version' => $lastVersion + 1,
                'task_type' => $validated['task_type'],
                'calculation_type' => $validated['calculation_type'],
                'revision_policy' => $validated['revision_policy'],
                'base_amount' => (string) $validated['base_amount'],
                'per_revision_amount' => (string) $validated['per_revision_amount'],
                'per_approved_item_amount' => (string) $validated['per_approved_item_amount'],
                'max_amount' => isset($validated['max_amount']) ? (string) $validated['max_amount'] : null,
                'currency' => $validated['currency'],
                'conditions_json' => $validated['conditions_json'] ?? null,
                'is_active' => true,
                'effective_from' => $validated['effective_from'] ?? null,
                'effective_until' => $validated['effective_until'] ?? null,
                'created_by' => $actor->getKey(),
            ]);
            $this->auditService->record(
                action: 'commission_rule_version_created',
                actor: $actor,
                metadata: [
                    'rule_id' => $rule->getKey(),
                    'name' => $rule->name,
                    'version' => $rule->version,
                ],
                entity: $rule,
            );

            return $rule;
        }, 3);
    }

    public function createPerContentVersion(User $actor, array $input): CommissionRule
    {
        Gate::forUser($actor)->authorize('create', CommissionRule::class);

        $allowedTaskTypes = [
            ProductCompletionTaskType::CONTENT->value,
            ProductCompletionTaskType::ATTRIBUTES->value,
            ProductCompletionTaskType::MEDIA->value,
            ProductCompletionTaskType::FULL_COMPLETION->value,
            ProductCompletionTaskType::CORRECTION->value,
        ];
        $validated = Validator::make($input, [
            'name' => ['required', 'string', 'max:255', 'regex:/^[\p{L}\p{N}\s\x{200C}_-]+$/u'],
            'task_type' => ['required', Rule::in($allowedTaskTypes)],
            'revision_percentage' => ['required', 'regex:/^(?:\d{1,2}(?:\.\d{1,2})?|100(?:\.0{1,2})?)$/'],
            'currency' => ['required', 'string', Rule::in([(string) config('product_completion.currency', 'IRR')])],
            'items' => ['required', 'array', 'size:4'],
            'items.*.content_type' => ['required', Rule::enum(CommissionContentType::class)],
            'items.*.unit_amount' => ['required', 'regex:/^\d{1,12}(?:\.\d{1,2})?$/'],
            'items.*.max_payable_units' => ['required', 'integer', 'min:0', 'max:100'],
            'items.*.is_enabled' => ['required', 'boolean'],
        ], [
            'name.required' => 'نام قانون پورسانت الزامی است.',
            'name.regex' => 'نام قانون پورسانت شامل کاراکتر غیرمجاز است.',
            'task_type.in' => 'برای این نوع Task هنوز قیمت‌گذاری واحدی تعریف نشده است.',
            'revision_percentage.required' => 'درصد اصلاح الزامی است.',
            'revision_percentage.regex' => 'درصد اصلاح باید بین صفر تا صد و حداکثر دو رقم اعشار باشد.',
            'currency.in' => 'واحد پول قانون باید با واحد پول سیستم یکسان باشد.',
            'items.required' => 'تعریف نرخ‌های محتوا الزامی است.',
            'items.size' => 'تعریف نرخ‌های محتوا ناقص است.',
            'items.*.unit_amount.regex' => 'فرمت مبلغ واحد نامعتبر است یا بیش از دو رقم اعشار دارد.',
            'items.*.max_payable_units.integer' => 'سقف تعداد باید عدد صحیح باشد.',
            'items.*.max_payable_units.min' => 'سقف تعداد نمی‌تواند منفی باشد.',
            'items.*.max_payable_units.max' => 'سقف تعداد نمی‌تواند بیشتر از صد باشد.',
        ])->validate();
        $validated['name'] = $this->sanitizer->plainText($validated['name'], 255);
        $items = $this->normalizePerContentItems(
            ProductCompletionTaskType::from($validated['task_type']),
            $validated['items'],
        );

        return $this->withRuleVersionLock(
            $validated['name'],
            function () use ($actor, $validated, $items): CommissionRule {
                return DB::transaction(function () use ($actor, $validated, $items): CommissionRule {
                    $lastVersion = (int) (CommissionRule::query()
                        ->where('name', $validated['name'])
                        ->orderByDesc('version')
                        ->lockForUpdate()
                        ->value('version') ?? 0);
                    $rule = CommissionRule::query()->create([
                        'name' => $validated['name'],
                        'version' => $lastVersion + 1,
                        'task_type' => $validated['task_type'],
                        'calculation_type' => CommissionCalculationType::PER_CONTENT_TYPE,
                        'revision_policy' => ProductCompletionRevisionPolicy::SCOPE_CHANGE_ONLY,
                        'pricing_schema_version' => 2,
                        'revision_percentage' => (string) $validated['revision_percentage'],
                        'base_amount' => '0.00',
                        'per_revision_amount' => '0.00',
                        'per_approved_item_amount' => '0.00',
                        'max_amount' => null,
                        'currency' => $validated['currency'],
                        'conditions_json' => null,
                        'is_active' => false,
                        'created_by' => $actor->getKey(),
                    ]);

                    foreach ($items as $item) {
                        $rule->items()->create($item);
                    }

                    $previousActiveRuleIds = CommissionRule::query()
                        ->where('name', $validated['name'])
                        ->where('is_active', true)
                        ->whereKeyNot($rule->getKey())
                        ->pluck('id')
                        ->all();
                    CommissionRule::query()
                        ->whereIn('id', $previousActiveRuleIds)
                        ->update(['is_active' => false]);
                    $rule->forceFill(['is_active' => true])->save();
                    $this->auditService->record(
                        action: 'commission_rule_version_created',
                        actor: $actor,
                        metadata: [
                            'rule_id' => $rule->getKey(),
                            'name' => $rule->name,
                            'version' => $rule->version,
                            'pricing_schema_version' => 2,
                            'revision_percentage' => $rule->revision_percentage,
                            'deactivated_rule_ids' => $previousActiveRuleIds,
                            'items' => $items,
                        ],
                        entity: $rule,
                    );

                    return $rule->load('items');
                }, 3);
            }
        );
    }

    private function withRuleVersionLock(string $ruleName, Closure $callback): mixed
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return $callback();
        }

        $lockName = 'commission-rule:'.sha1($ruleName);
        $result = DB::selectOne('SELECT GET_LOCK(?, 10) AS acquired', [$lockName]);

        if ((int) ($result->acquired ?? 0) !== 1) {
            throw ValidationException::withMessages([
                'name' => 'نسخه دیگری از این قانون در حال ایجاد است؛ چند ثانیه بعد دوباره تلاش کنید.',
            ]);
        }

        try {
            return $callback();
        } finally {
            DB::selectOne('SELECT RELEASE_LOCK(?) AS released', [$lockName]);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function normalizePerContentItems(ProductCompletionTaskType $taskType, array $items): array
    {
        $byType = collect($items)->keyBy('content_type');
        $expectedTypes = CommissionContentType::values();

        if ($byType->count() !== count($expectedTypes)
            || array_diff($expectedTypes, $byType->keys()->all()) !== []) {
            throw ValidationException::withMessages([
                'items' => 'هر نوع محتوا باید دقیقاً یک‌بار در قانون تعریف شود.',
            ]);
        }

        $applicableTypes = match ($taskType) {
            ProductCompletionTaskType::CONTENT => [CommissionContentType::DESCRIPTION],
            ProductCompletionTaskType::ATTRIBUTES => [CommissionContentType::ATTRIBUTE],
            ProductCompletionTaskType::MEDIA => [CommissionContentType::IMAGE],
            ProductCompletionTaskType::FULL_COMPLETION,
            ProductCompletionTaskType::CORRECTION => [
                CommissionContentType::IMAGE,
                CommissionContentType::ATTRIBUTE,
                CommissionContentType::DESCRIPTION,
            ],
            default => [],
        };
        $applicableValues = array_map(static fn (CommissionContentType $type): string => $type->value, $applicableTypes);
        $normalized = [];
        $enabledCount = 0;

        foreach (CommissionContentType::cases() as $contentType) {
            $item = $byType->get($contentType->value);
            $unitAmount = (string) $item['unit_amount'];
            $maxUnits = (int) $item['max_payable_units'];
            $isApplicable = in_array($contentType->value, $applicableValues, true);
            $requestedEnabled = (bool) ($item['is_enabled'] ?? false);
            $hasAmount = (float) $unitAmount > 0;
            $hasUnits = $maxUnits > 0;

            if ($contentType === CommissionContentType::VIDEO) {
                $isApplicable = false;
            }

            if (! $isApplicable && $requestedEnabled) {
                throw ValidationException::withMessages([
                    "items.{$contentType->value}" => 'برای این نوع Task، نرخ این محتوا قابل تعریف نیست.',
                ]);
            }

            if ($requestedEnabled && (! $hasAmount || ! $hasUnits)) {
                throw ValidationException::withMessages([
                    "items.{$contentType->value}" => 'برای فعال‌سازی هر نوع محتوا، مبلغ واحد و سقف تعداد باید هر دو بیشتر از صفر باشند.',
                ]);
            }

            if ($contentType === CommissionContentType::IMAGE
                && $maxUnits > (int) config('product_completion.media.max_images', 10)) {
                throw ValidationException::withMessages([
                    'items.image' => 'سقف تصاویر نمی‌تواند بیشتر از حداکثر تصاویر مجاز محصول باشد.',
                ]);
            }

            if ($contentType === CommissionContentType::DESCRIPTION && $maxUnits > 1) {
                throw ValidationException::withMessages([
                    'items.description' => 'توضیحات کوتاه و بلند در مجموع فقط یک واحد هستند.',
                ]);
            }

            $enabled = $isApplicable && $requestedEnabled && $hasAmount && $hasUnits;
            $enabledCount += $enabled ? 1 : 0;
            $normalized[] = [
                'content_type' => $contentType->value,
                'unit_amount' => $enabled ? $unitAmount : '0.00',
                'max_payable_units' => $enabled ? $maxUnits : 0,
                'is_enabled' => $enabled,
            ];
        }

        if ($enabledCount === 0) {
            throw ValidationException::withMessages([
                'items' => 'حداقل یک نوع محتوای قابل پرداخت باید مبلغ و سقف تعداد داشته باشد.',
            ]);
        }

        return $normalized;
    }

    public function deactivate(User $actor, CommissionRule $rule): CommissionRule
    {
        Gate::forUser($actor)->authorize('update', $rule);

        return DB::transaction(function () use ($actor, $rule): CommissionRule {
            $locked = CommissionRule::query()->lockForUpdate()->findOrFail($rule->getKey());
            Gate::forUser($actor)->authorize('update', $locked);
            $locked->forceFill(['is_active' => false])->save();
            $this->auditService->record(
                action: 'commission_rule_deactivated',
                actor: $actor,
                metadata: ['rule_id' => $locked->getKey(), 'version' => $locked->version],
                entity: $locked,
            );

            return $locked;
        }, 3);
    }
}
