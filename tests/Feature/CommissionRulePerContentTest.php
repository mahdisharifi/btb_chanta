<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CommissionCalculationType;
use App\Enums\CommissionContentType;
use App\Enums\ProductCompletionRevisionPolicy;
use App\Enums\UserRole;
use App\Models\CommissionRule;
use App\Models\ProductCompletionTask;
use App\Models\User;
use App\Services\CommissionRuleService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CommissionRulePerContentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    #[Test]
    public function it_creates_an_active_version_two_rule_with_immutable_content_items(): void
    {
        $finance = User::factory()->create()->assignRole(UserRole::FINANCE_MANAGER->value);
        $rule = app(CommissionRuleService::class)->createPerContentVersion($finance, [
            'name' => 'قانون واحدی تکمیل کامل',
            'task_type' => 'full_completion',
            'revision_percentage' => '30.00',
            'currency' => 'IRR',
            'items' => [
                ['content_type' => 'image', 'unit_amount' => '5000.00', 'max_payable_units' => 6, 'is_enabled' => true],
                ['content_type' => 'video', 'unit_amount' => '0.00', 'max_payable_units' => 0, 'is_enabled' => false],
                ['content_type' => 'attribute', 'unit_amount' => '2000.00', 'max_payable_units' => 20, 'is_enabled' => true],
                ['content_type' => 'description', 'unit_amount' => '10000.00', 'max_payable_units' => 1, 'is_enabled' => true],
            ],
        ]);

        $this->assertTrue($rule->is_active);
        $this->assertSame(2, $rule->pricing_schema_version);
        $this->assertSame(CommissionCalculationType::PER_CONTENT_TYPE, $rule->calculation_type);
        $this->assertSame(ProductCompletionRevisionPolicy::SCOPE_CHANGE_ONLY, $rule->revision_policy);
        $this->assertSame('30.00', $rule->revision_percentage);
        $this->assertCount(4, $rule->items);
        $this->assertFalse($rule->items->firstWhere('content_type', CommissionContentType::VIDEO)->is_enabled);

        $this->expectException(LogicException::class);
        $rule->items->firstWhere('content_type', CommissionContentType::IMAGE)
            ->forceFill(['unit_amount' => '6000.00'])
            ->save();
    }

    #[Test]
    public function it_rejects_video_or_non_applicable_content_without_partial_rule_creation(): void
    {
        $finance = User::factory()->create()->assignRole(UserRole::FINANCE_MANAGER->value);
        $input = [
            'name' => 'قانون نامعتبر رسانه',
            'task_type' => 'media',
            'revision_percentage' => '30.00',
            'currency' => 'IRR',
            'items' => [
                ['content_type' => 'image', 'unit_amount' => '5000.00', 'max_payable_units' => 6, 'is_enabled' => true],
                ['content_type' => 'video', 'unit_amount' => '9000.00', 'max_payable_units' => 1, 'is_enabled' => true],
                ['content_type' => 'attribute', 'unit_amount' => '0.00', 'max_payable_units' => 0, 'is_enabled' => false],
                ['content_type' => 'description', 'unit_amount' => '0.00', 'max_payable_units' => 0, 'is_enabled' => false],
            ],
        ];

        try {
            app(CommissionRuleService::class)->createPerContentVersion($finance, $input);
            $this->fail('فعال‌سازی ویدئو باید رد شود.');
        } catch (ValidationException) {
            $this->assertDatabaseMissing('commission_rules', ['name' => 'قانون نامعتبر رسانه']);
        }
    }

    #[Test]
    public function task_commission_contract_cannot_be_repointed_after_snapshot(): void
    {
        $task = ProductCompletionTask::factory()->create(['active_product_id' => null]);
        $otherRule = CommissionRule::factory()->create();

        $this->expectException(LogicException::class);
        $task->forceFill(['commission_rule_id' => $otherRule->getKey()])->save();
    }

    #[Test]
    public function a_new_version_preserves_the_previous_version_and_rates(): void
    {
        $finance = User::factory()->create()->assignRole(UserRole::FINANCE_MANAGER->value);
        $service = app(CommissionRuleService::class);
        $input = [
            'name' => 'قانون تصاویر',
            'task_type' => 'media',
            'revision_percentage' => '30.00',
            'currency' => 'IRR',
            'items' => [
                ['content_type' => 'image', 'unit_amount' => '5000.00', 'max_payable_units' => 6, 'is_enabled' => true],
                ['content_type' => 'video', 'unit_amount' => '0.00', 'max_payable_units' => 0, 'is_enabled' => false],
                ['content_type' => 'attribute', 'unit_amount' => '0.00', 'max_payable_units' => 0, 'is_enabled' => false],
                ['content_type' => 'description', 'unit_amount' => '0.00', 'max_payable_units' => 0, 'is_enabled' => false],
            ],
        ];
        $first = $service->createPerContentVersion($finance, $input);
        $input['items'][0]['unit_amount'] = '7000.00';
        $second = $service->createPerContentVersion($finance, $input);

        $this->assertSame(1, $first->version);
        $this->assertSame(2, $second->version);
        $this->assertFalse($first->refresh()->is_active);
        $this->assertTrue($second->is_active);
        $this->assertSame('5000.00', $first->items()->where('content_type', 'image')->value('unit_amount'));
        $this->assertSame('7000.00', $second->items()->where('content_type', 'image')->value('unit_amount'));
        $this->assertSame(2, CommissionRule::query()->where('name', 'قانون تصاویر')->count());
    }

    #[Test]
    public function selecting_a_default_replaces_the_previous_system_default_across_task_types(): void
    {
        $finance = User::factory()->create()->assignRole(UserRole::FINANCE_MANAGER->value);
        $previousDefault = CommissionRule::factory()->create([
            'task_type' => 'full_completion',
            'is_default' => true,
        ]);
        $selectedDefault = CommissionRule::factory()->create([
            'task_type' => 'media',
            'is_default' => false,
            'effective_from' => now()->subDay(),
            'effective_until' => null,
        ]);

        app(CommissionRuleService::class)->setDefault($finance, $selectedDefault);

        $this->assertFalse($previousDefault->refresh()->is_default);
        $this->assertTrue($selectedDefault->refresh()->is_default);
        $this->assertSame(1, CommissionRule::query()->where('is_default', true)->count());
    }

    #[Test]
    public function seo_rules_cannot_be_selected_as_the_system_default(): void
    {
        $finance = User::factory()->create()->assignRole(UserRole::FINANCE_MANAGER->value);
        $seoRule = CommissionRule::factory()->create([
            'task_type' => 'seo',
            'is_default' => false,
        ]);

        $this->expectException(ValidationException::class);

        app(CommissionRuleService::class)->setDefault($finance, $seoRule);
    }
}
