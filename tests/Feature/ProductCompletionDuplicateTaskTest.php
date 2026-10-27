<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ProductCompletionTaskStatus;
use App\Enums\ProductCompletionTaskType;
use App\Livewire\Admin\Products\Index;
use App\Models\CommissionRule;
use App\Models\Product;
use App\Models\ProductCompletionAudit;
use App\Models\ProductCompletionTask;
use App\Models\User;
use App\Services\ProductCompletionTaskService;
use App\Services\ProductCompletionAuditService;
use App\Services\ProductCompletionStorageGuard;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProductCompletionDuplicateTaskTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private CommissionRule $rule;

    public function createApplication()
    {
        $app = parent::createApplication();
        if (! $app->environment('testing')
            || $app['config']->get('database.connections.mysql.database') !== 'b2b_marketplace_test') {
            throw new \RuntimeException('Duplicate task tests require the isolated test database.');
        }
        $connection = $app['db']->connection();
        if ($connection->getDatabaseName() !== 'b2b_marketplace_test'
            || $connection->selectOne('SELECT DATABASE() AS current_database', [], false)->current_database !== 'b2b_marketplace_test') {
            throw new \RuntimeException('Resolved connection must use the isolated test database.');
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->actor = User::factory()->create();
        $this->actor->givePermissionTo('outsourcing.create_task');
        $this->rule = CommissionRule::factory()->create([
            'task_type' => ProductCompletionTaskType::CONTENT,
        ]);
    }

    public function test_every_nonterminal_status_blocks_creation_without_an_active_marker(): void
    {
        foreach (ProductCompletionTaskStatus::cases() as $status) {
            if ($status->isTerminal()) {
                continue;
            }

            $task = ProductCompletionTask::factory()->create([
                'status' => $status,
                'active_product_id' => null,
            ]);

            $this->assertCreationBlocked($task);
        }
    }

    public function test_stale_active_marker_cannot_bypass_product_ownership_check(): void
    {
        $task = ProductCompletionTask::factory()->create([
            'active_product_id' => Product::factory()->create()->getKey(),
            'status' => ProductCompletionTaskStatus::APPROVED,
        ]);

        $this->assertCreationBlocked($task);
    }

    public function test_older_hidden_seo_task_blocks_even_with_a_newer_finalized_task(): void
    {
        $task = ProductCompletionTask::factory()->create([
            'task_type' => ProductCompletionTaskType::SEO,
            'status' => ProductCompletionTaskStatus::APPROVED,
            'active_product_id' => null,
            'created_at' => now()->subDays(2),
        ]);
        ProductCompletionTask::factory()->create([
            'product_id' => $task->product_id,
            'active_product_id' => null,
            'status' => ProductCompletionTaskStatus::COMPLETED,
            'content_applied_at' => now(),
            'media_published_at' => now(),
        ]);

        $this->assertFalse(ProductCompletionTask::query()->whereKey($task->getKey())->exists());
        $this->assertCreationBlocked($task);
        $this->assertSame(2, ProductCompletionTask::withoutGlobalScope('withoutExcludedFields')
            ->where('product_id', $task->product_id)->count());
    }

    public function test_completed_task_blocks_until_both_finalization_markers_exist(): void
    {
        foreach ([[null, null], [now(), null], [null, now()]] as [$contentAppliedAt, $mediaPublishedAt]) {
            $task = ProductCompletionTask::factory()->create([
                'active_product_id' => null,
                'status' => ProductCompletionTaskStatus::COMPLETED,
                'content_applied_at' => $contentAppliedAt,
                'media_published_at' => $mediaPublishedAt,
            ]);

            $this->assertCreationBlocked($task);
        }
    }

    public function test_finalized_cancelled_and_expired_tasks_allow_new_creation(): void
    {
        foreach ([ProductCompletionTaskStatus::COMPLETED, ProductCompletionTaskStatus::CANCELLED, ProductCompletionTaskStatus::EXPIRED] as $status) {
            $task = ProductCompletionTask::factory()->create([
                'active_product_id' => null,
                'status' => $status,
                'content_applied_at' => $status === ProductCompletionTaskStatus::COMPLETED ? now() : null,
                'media_published_at' => $status === ProductCompletionTaskStatus::COMPLETED ? now() : null,
            ]);

            $this->assertFalse(ProductCompletionTaskService::applyUnfinishedTaskFilter(
                ProductCompletionTask::query()->where('product_id', $task->product_id)
            )->exists());
            $created = app(ProductCompletionTaskService::class)->create(
                $this->actor, $task->product, $this->rule, ProductCompletionTaskType::CONTENT,
            );

            $this->assertNotSame($task->getKey(), $created->getKey());
            $this->assertSame($task->product_id, $created->active_product_id);
        }
    }

    public function test_terminal_own_markers_are_reconciled_with_an_audit(): void
    {
        foreach ([ProductCompletionTaskStatus::COMPLETED, ProductCompletionTaskStatus::CANCELLED, ProductCompletionTaskStatus::EXPIRED] as $status) {
            $task = ProductCompletionTask::factory()->create([
                'status' => $status,
                'task_type' => ProductCompletionTaskType::SEO,
                'content_applied_at' => $status === ProductCompletionTaskStatus::COMPLETED ? now() : null,
                'media_published_at' => $status === ProductCompletionTaskStatus::COMPLETED ? now() : null,
            ]);
            $version = $task->lock_version;
            $eligible = Product::query()->whereKey($task->product_id)->whereDoesntHave(
                'completionTasks',
                static fn ($query) => ProductCompletionTaskService::applyUnfinishedTaskFilter($query),
            )->exists();
            $this->assertTrue($eligible);

            $created = app(ProductCompletionTaskService::class)->create(
                $this->actor, $task->product, $this->rule, ProductCompletionTaskType::CONTENT,
            );
            $task = ProductCompletionTask::withoutGlobalScope('withoutExcludedFields')->findOrFail($task->id);
            $this->assertNull($task->active_product_id);
            $this->assertSame($status, $task->status);
            $this->assertSame($version + 1, $task->lock_version);
            $this->assertSame($task->product_id, $created->active_product_id);
            $audit = ProductCompletionAudit::query()->where('task_id', $task->id)
                ->where('action', 'task_active_marker_reconciled')->sole();
            $this->assertSame(['active_product_id' => $task->product_id], $audit->before_json);
            $this->assertSame(['active_product_id' => null], $audit->after_json);
            $this->assertSame($this->actor->id, $audit->user_id);
        }
    }

    public function test_incomplete_completed_own_marker_is_never_cleared(): void
    {
        $task = ProductCompletionTask::factory()->create([
            'status' => ProductCompletionTaskStatus::COMPLETED,
            'content_applied_at' => now(),
            'media_published_at' => null,
        ]);
        $before = $task->refresh()->getRawOriginal();
        $this->assertCreationBlocked($task);
        $this->assertSame($before, $task->refresh()->getRawOriginal());
        $this->assertFalse(ProductCompletionAudit::query()->where('task_id', $task->id)->exists());
    }

    public function test_marker_owned_by_another_product_fails_without_mutation(): void
    {
        $product = Product::factory()->create();
        $task = ProductCompletionTask::factory()->create([
            'status' => ProductCompletionTaskStatus::CANCELLED,
            'task_type' => ProductCompletionTaskType::SEO,
            'active_product_id' => $product->id,
        ]);
        $task = ProductCompletionTask::withoutGlobalScope('withoutExcludedFields')->findOrFail($task->id);
        $before = $task->getRawOriginal();
        try {
            app(ProductCompletionTaskService::class)->create(
                $this->actor, $product, $this->rule, ProductCompletionTaskType::CONTENT,
            );
            $this->fail('A marker owned by another product requires manual reconciliation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('product_id', $exception->errors());
            $unchanged = ProductCompletionTask::withoutGlobalScope('withoutExcludedFields')->findOrFail($task->id);
            $this->assertSame($before, $unchanged->getRawOriginal());
            $this->assertFalse(ProductCompletionTask::withoutGlobalScope('withoutExcludedFields')
                ->where('product_id', $product->id)->exists());
            $this->assertFalse(ProductCompletionAudit::query()->where('task_id', $task->id)->exists());
        }
    }

    public function test_reconciliation_audit_failure_rolls_back_the_marker(): void
    {
        $task = ProductCompletionTask::factory()->create(['status' => ProductCompletionTaskStatus::CANCELLED]);
        $before = $task->refresh()->getRawOriginal();
        $this->mock(ProductCompletionAuditService::class, function ($mock): void {
            $mock->shouldReceive('record')->once()->andThrow(new \RuntimeException('Audit failed.'));
        });
        try {
            app(ProductCompletionTaskService::class)->create(
                $this->actor, $task->product, $this->rule, ProductCompletionTaskType::CONTENT,
            );
            $this->fail('Reconciliation must be atomic with its audit.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Audit failed.', $exception->getMessage());
            $this->assertSame($before, $task->refresh()->getRawOriginal());
            $this->assertSame(1, ProductCompletionTask::query()->where('product_id', $task->product_id)->count());
        }
    }

    public function test_storage_guard_prevents_creation_and_reconciliation(): void
    {
        $task = ProductCompletionTask::factory()->create(['status' => ProductCompletionTaskStatus::CANCELLED]);
        $before = $task->refresh()->getRawOriginal();
        $this->mock(ProductCompletionStorageGuard::class, function ($mock): void {
            $mock->shouldReceive('assertTransactional')->once()->andThrow(
                ValidationException::withMessages(['storage' => 'Transactional storage required.']),
            );
        });
        try {
            app(ProductCompletionTaskService::class)->create(
                $this->actor, $task->product, $this->rule, ProductCompletionTaskType::CONTENT,
            );
            $this->fail('Creation requires transactional storage.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('storage', $exception->errors());
            $this->assertSame($before, $task->refresh()->getRawOriginal());
            $this->assertSame(1, ProductCompletionTask::query()->where('product_id', $task->product_id)->count());
        }
    }

    private function assertCreationBlocked(ProductCompletionTask $task): void
    {
        $productQuery = Product::query()->whereKey($task->product_id);
        $blockingTasks = static fn ($query) => ProductCompletionTaskService::applyUnfinishedTaskFilter($query);
        $this->assertTrue((clone $productQuery)->whereHas('completionTasks', $blockingTasks)->exists());
        $this->assertFalse((clone $productQuery)->whereDoesntHave('completionTasks', $blockingTasks)->exists());
        $product = (clone $productQuery)->withExists([
            'completionTasks as has_active_completion_task' => $blockingTasks,
        ])->firstOrFail();
        $this->assertTrue((bool) $product->has_active_completion_task);

        $component = new class extends Index {
            public function filteredProductIds(): array
            {
                return $this->getFilteredQuery()->pluck('products.id')->all();
            }
        };
        $component->taskStatus = 'active';
        $this->assertContains($task->product_id, $component->filteredProductIds());

        $previousCount = ProductCompletionTask::withoutGlobalScope('withoutExcludedFields')
            ->where('product_id', $task->product_id)->count();

        try {
            app(ProductCompletionTaskService::class)->create(
                $this->actor, $task->product, $this->rule, ProductCompletionTaskType::CONTENT,
            );
            $this->fail('An unfinished task must prevent creating another task for the product.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('product_id', $exception->errors());
        }

        $this->assertSame($previousCount, ProductCompletionTask::withoutGlobalScope('withoutExcludedFields')
            ->where('product_id', $task->product_id)->count());
    }
}
