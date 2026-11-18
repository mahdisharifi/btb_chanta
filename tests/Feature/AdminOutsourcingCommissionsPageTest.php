<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ProductCompletionCommissionStatus as Status;
use App\Enums\ProductCompletionTaskType;
use App\Enums\UserRole;
use App\Livewire\Admin\Outsourcing\Commissions;
use App\Models\CommissionRule;
use App\Models\ProductCompletionCommission;
use App\Models\ProductCompletionTask;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class AdminOutsourcingCommissionsPageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Keep these page/service tests independent of unrelated legacy MySQL migrations.
        config([
            'database.default' => 'commissions_page_test',
            'database.connections.commissions_page_test' => [
                'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
            ],
        ]);
        DB::purge('commissions_page_test');
        foreach ([
            '0001_01_01_000000_create_users_table.php',
            '2026_02_15_104854_create_permission_tables.php',
            '2026_05_17_002840_add_display_fields_to_roles_and_permissions.php',
        ] as $migration) {
            (require database_path('migrations/'.$migration))->up();
        }
        Schema::create('suppliers', function (Blueprint $table): void {
            $table->id();
        });
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->softDeletes();
        });
        foreach ([
            '2026_10_03_120100_create_product_completion_tasks_table.php',
            '2026_10_03_120110_create_product_completion_submissions_table.php',
            '2026_10_03_120120_add_last_submission_to_product_completion_tasks_table.php',
            '2026_10_03_120300_create_commission_rules_table.php',
            '2026_10_03_120310_create_product_completion_commissions_table.php',
            '2026_10_03_120320_create_product_completion_audits_table.php',
            '2026_10_13_090000_create_outsourcing_settlements.php',
        ] as $migration) {
            (require database_path('migrations/'.$migration))->up();
        }
        Schema::table('product_completion_tasks', function (Blueprint $table): void {
            $table->decimal('commission_rate_percentage', 5, 2)->default(100);
        });
        $this->seed(RolePermissionSeeder::class);
    }

    private function commission(array $attributes = [], array $taskAttributes = []): ProductCompletionCommission
    {
        $productId = DB::table('products')->insertGetId(['title' => 'Test commission product']);
        $rule = CommissionRule::factory()->create();
        $task = ProductCompletionTask::factory()->create([
            'product_id' => $productId, 'supplier_id' => null, 'commission_rule_id' => $rule->id,
            ...$taskAttributes,
        ]);

        return ProductCompletionCommission::factory()->create([
            'task_id' => $task->id, 'commission_rule_id' => $rule->id, ...$attributes,
        ]);
    }

    public function test_finance_can_approve_and_mark_selected_commissions_payable_with_audits(): void
    {
        $actor = User::factory()->create()->assignRole(UserRole::FINANCE_MANAGER->value);
        $first = $this->commission();
        $second = $this->commission(['status' => Status::UNDER_REVIEW]);
        $untouched = $this->commission();
        $ids = [$first->public_id, $second->public_id];

        Livewire::actingAs($actor)->test(Commissions::class)
            ->set('selectedCommissions', $ids)
            ->assertViewHas('selection', fn ($value) => $value['approvable'] === 2 && $value['totals']['IRR'] === '1000000.00')
            ->call('processSelected', 'approve')->assertHasNoErrors()
            ->assertSet('selectedCommissions', [])
            ->set('selectedCommissions', $ids)
            ->call('processSelected', 'payable')->assertHasNoErrors();

        foreach ([$first, $second] as $commission) {
            $this->assertSame(Status::PAYABLE, $commission->fresh()->status);
            $this->assertSame($actor->id, $commission->fresh()->approved_by);
            $this->assertNotNull($commission->fresh()->payable_at);
            foreach (['commission_approved', 'commission_marked_payable'] as $action) {
                $this->assertDatabaseHas('product_completion_audits', ['task_id' => $commission->task_id, 'user_id' => $actor->id, 'action' => $action]);
            }
        }
        $this->assertSame(Status::CALCULATED, $untouched->fresh()->status);
    }

    public function test_stale_or_missing_selection_does_not_partially_approve(): void
    {
        $actor = User::factory()->create()->assignRole(UserRole::FINANCE_MANAGER->value);
        $first = $this->commission();
        $stale = $this->commission(['status' => Status::PAID]);

        $page = Livewire::actingAs($actor)->test(Commissions::class);
        foreach ([$stale->public_id, 'missing-commission'] as $invalid) {
            $page->set('selectedCommissions', [$first->public_id, $invalid])
                ->call('processSelected', 'approve')->assertHasErrors('selectedCommissions');
            $this->assertSame(Status::CALCULATED, $first->fresh()->status);
            $this->assertDatabaseMissing('product_completion_audits', ['action' => 'commission_approved']);
        }
    }

    public function test_view_permission_alone_cannot_execute_bulk_actions(): void
    {
        $actor = User::factory()->create()->givePermissionTo('outsourcing.view_all_commissions');
        $commission = $this->commission();
        Livewire::actingAs($actor)->test(Commissions::class)
            ->assertHasNoErrors()
            ->set('selectedCommissions', [$commission->public_id])
            // The application's exception handler conceals forbidden responses as 404.
            ->call('processSelected', 'approve')->assertNotFound();
        $this->assertSame(Status::CALCULATED, $commission->fresh()->status);
    }

    public function test_filters_and_page_selection_only_select_eligible_visible_rows(): void
    {
        $actor = User::factory()->create()->assignRole(UserRole::FINANCE_MANAGER->value);
        $contractor = User::factory()->create();
        $matching = $this->commission(['user_id' => $contractor->id, 'calculated_at' => '2026-11-10 12:00:00'], ['task_type' => ProductCompletionTaskType::MEDIA]);
        $approved = $this->commission(['status' => Status::APPROVED]);
        $this->commission(['status' => Status::PAID]);

        Livewire::actingAs($actor)->test(Commissions::class)
            ->call('selectPage', 'payable')->assertSet('selectedCommissions', [$approved->public_id])
            ->set('filterUserId', (string) $contractor->id)->assertSet('selectedCommissions', [])
            ->set('filterTaskType', 'media')->set('dateFrom', '1405/08/19')->set('dateTo', '1405/08/19')
            ->set('settlementFilter', 'unreserved')->set('search', $matching->public_id)
            ->assertViewHas('commissions', fn ($rows) => $rows->total() === 1 && $rows->first()->is($matching))
            ->call('selectPage', 'approve')->assertSet('selectedCommissions', [$matching->public_id])
            ->call('gotoPage', 2)->assertSet('selectedCommissions', [])
            ->call('resetFilters')->assertSet('selectedCommissions', [])->assertSet('search', '')
            ->assertViewHas('commissions', fn ($rows) => $rows->total() === 3);
    }

    public function test_failure_during_a_batch_rolls_back_preceding_changes_and_audits(): void
    {
        $actor = User::factory()->create()->assignRole(UserRole::FINANCE_MANAGER->value);
        $first = $this->commission();
        $second = $this->commission();
        $service = app(\App\Services\ProductCompletionCommissionService::class);
        $mock = \Mockery::mock(\App\Services\ProductCompletionCommissionService::class);
        $mock->shouldReceive('approve')->twice()->andReturnUsing(function ($actor, $row) use ($second, $service) {
            if ($row->is($second)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['commission' => 'Concurrent financial change.']);
            }

            return $service->approve($actor, $row);
        });
        $this->app->instance(\App\Services\ProductCompletionCommissionService::class, $mock);

        Livewire::actingAs($actor)->test(Commissions::class)
            ->set('selectedCommissions', [$first->public_id, $second->public_id])
            ->call('processSelected', 'approve')->assertHasErrors('commission');

        $this->assertSame(Status::CALCULATED, $first->fresh()->status);
        $this->assertSame(Status::CALCULATED, $second->fresh()->status);
        $this->assertDatabaseMissing('product_completion_audits', ['action' => 'commission_approved']);
    }

    public function test_page_size_can_change_and_only_supported_sizes_are_accepted(): void
    {
        $actor = User::factory()->create()->assignRole(UserRole::FINANCE_MANAGER->value);
        $commission = $this->commission();

        Livewire::actingAs($actor)->test(Commissions::class)
            ->set('selectedCommissions', [$commission->public_id])
            ->set('perPage', 50)->assertHasNoErrors()
            ->assertSet('selectedCommissions', [])
            ->assertViewHas('commissions', fn ($rows) => $rows->perPage() === 50)
            ->set('perPage', 10)->assertHasNoErrors()
            ->assertViewHas('commissions', fn ($rows) => $rows->perPage() === 10)
            ->set('perPage', 500)->assertHasErrors('perPage');
    }

    public function test_all_pages_selection_respects_filters_survives_navigation_and_processes_over_100_rows(): void
    {
        $actor = User::factory()->create()->assignRole(UserRole::FINANCE_MANAGER->value);
        $contractor = User::factory()->create();
        $attributes = ['user_id' => $contractor->id, 'calculated_at' => '2026-11-10 12:00:00'];
        $taskAttributes = ['task_type' => ProductCompletionTaskType::MEDIA];
        $eligible = collect();
        for ($index = 0; $index < 101; $index++) {
            $eligible->push($this->commission([
                ...$attributes,
                'status' => $index === 100 ? Status::UNDER_REVIEW : Status::CALCULATED,
            ], $taskAttributes));
        }
        $wrongContractor = $this->commission(['calculated_at' => $attributes['calculated_at']], $taskAttributes);
        $wrongType = $this->commission($attributes);
        $wrongDate = $this->commission([...$attributes, 'calculated_at' => '2026-11-09 12:00:00'], $taskAttributes);
        $paid = $this->commission([...$attributes, 'status' => Status::PAID], $taskAttributes);
        $approved = $this->commission([...$attributes, 'status' => Status::APPROVED], $taskAttributes);
        $reserved = $this->commission($attributes, $taskAttributes);
        $settlementId = DB::table('outsourcing_settlements')->insertGetId([
            'public_id' => (string) \Illuminate\Support\Str::ulid(),
            'user_id' => $contractor->id, 'currency' => 'IRR',
            'from_date' => '2026-11-01', 'to_date' => '2026-11-17',
            'creation_key' => hash('sha256', 'test-batch'), 'request_hash' => hash('sha256', 'test-request'),
            'created_by' => $actor->id,
        ]);
        $reserved->forceFill(['settlement_id' => $settlementId])->save();
        $ids = $eligible->pluck('public_id')->all();

        $page = Livewire::actingAs($actor)->test(Commissions::class)
            ->set('perPage', 10)
            ->set('filterUserId', (string) $contractor->id)
            ->set('filterTaskType', 'media')
            ->set('dateFrom', '1405/08/19')->set('dateTo', '1405/08/19')
            ->call('selectPage', 'approve')->assertCount('selectedCommissions', 10)
            ->call('selectAllPages', 'approve')->assertHasNoErrors()
            ->assertCount('selectedCommissions', 101)->assertSet('allPagesSelected', true)
            ->assertViewHas('selection', fn ($selection) => $selection['count'] === 101 && $selection['totals']['IRR'] === '50500000.00')
            ->call('gotoPage', 2)->assertSet('selectedCommissions', $ids)
            ->set('perPage', 50)->assertSet('selectedCommissions', $ids)
            ->assertViewHas('commissions', fn ($rows) => $rows->perPage() === 50 && $rows->currentPage() === 1)
            ->call('processSelected', 'approve')->assertHasNoErrors()
            ->assertSet('selectedCommissions', [])->assertSet('allPagesSelected', false);

        $this->assertSame(101, ProductCompletionCommission::query()->whereIn('public_id', $ids)->where('status', Status::APPROVED)->count());
        $page->call('selectAllPages', 'payable')->assertCount('selectedCommissions', 102)
            ->call('processSelected', 'payable')->assertHasNoErrors();
        $this->assertSame(101, ProductCompletionCommission::query()->whereIn('public_id', $ids)->where('status', Status::PAYABLE)->count());
        $this->assertSame(Status::PAYABLE, $approved->fresh()->status);
        foreach ([$wrongContractor, $wrongType, $wrongDate, $reserved] as $excluded) {
            $this->assertSame(Status::CALCULATED, $excluded->fresh()->status);
        }
        $this->assertSame(Status::PAID, $paid->fresh()->status);
    }

    public function test_changing_filters_clears_all_pages_selection_and_viewers_cannot_select_all(): void
    {
        $actor = User::factory()->create()->assignRole(UserRole::FINANCE_MANAGER->value);
        $this->commission();
        Livewire::actingAs($actor)->test(Commissions::class)
            ->call('selectAllPages', 'approve')->assertCount('selectedCommissions', 1)
            ->set('status', 'calculated')
            ->assertSet('selectedCommissions', [])->assertSet('allPagesSelected', false);

        $viewer = User::factory()->create()->givePermissionTo('outsourcing.view_all_commissions');
        Livewire::actingAs($viewer)->test(Commissions::class)
            ->call('selectAllPages', 'approve')->assertNotFound();
    }

    public function test_jalali_filters_accept_persian_and_arabic_digits_and_include_the_whole_tehran_day(): void
    {
        config(['app.timezone' => 'UTC']);
        $actor = User::factory()->create()->assignRole(UserRole::FINANCE_MANAGER->value);
        $before = $this->commission(['calculated_at' => '2026-11-09 20:29:59']);
        $first = $this->commission(['calculated_at' => '2026-11-09 20:30:00']);
        $last = $this->commission(['calculated_at' => '2026-11-10 20:29:59']);
        $after = $this->commission(['calculated_at' => '2026-11-10 20:30:00']);

        Livewire::actingAs($actor)->test(Commissions::class)
            ->set('dateFrom', '۱۴۰۵/۰۸/۱۹')->set('dateTo', '١٤٠٥/٠٨/١٩')->assertHasNoErrors()
            ->assertViewHas('commissions', fn ($rows) => $rows->pluck('id')->all() === [$first->id, $last->id])
            ->assertSee('۱۴۰۵/۰۸/۱۹ ۰۰:۰۰')
            ->assertSee('۱۴۰۵/۰۸/۱۹ ۲۳:۵۹')
            ->call('selectAllPages', 'approve')
            ->assertSet('selectedCommissions', [$first->public_id, $last->public_id]);
        $this->assertSame(Status::CALCULATED, $before->fresh()->status);
        $this->assertSame(Status::CALCULATED, $after->fresh()->status);
    }

    public function test_invalid_jalali_dates_and_reversed_ranges_show_errors_and_cannot_select_rows(): void
    {
        $actor = User::factory()->create()->assignRole(UserRole::FINANCE_MANAGER->value);
        $this->commission();
        $page = Livewire::actingAs($actor)->test(Commissions::class);
        foreach (['۱۴۰۴/۱۲/۳۰', '۱۴۰۵/۰۷/۳۱', '2026-11-10', 'invalid'] as $invalid) {
            $page->set('dateFrom', $invalid)->assertHasErrors('dateFrom')
                ->assertViewHas('commissions', fn ($rows) => $rows->total() === 0)
                ->call('selectAllPages', 'approve')->assertSet('selectedCommissions', []);
        }
        $page->set('dateFrom', '۱۴۰۳/۱۲/۳۰')->assertHasNoErrors('dateFrom')
            ->set('dateFrom', '۱۴۰۵/۰۸/۲۰')->set('dateTo', '۱۴۰۵/۰۸/۱۹')->assertHasErrors('dateTo')
            ->assertViewHas('commissions', fn ($rows) => $rows->total() === 0)
            ->call('resetFilters')->assertHasNoErrors(['dateFrom', 'dateTo'])
            ->assertViewHas('commissions', fn ($rows) => $rows->total() === 1);
    }
}
