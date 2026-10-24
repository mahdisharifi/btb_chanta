<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ProductCompletionTaskStatus;
use App\Enums\UserRole;
use App\Livewire\Admin\Outsourcing\Tasks;
use App\Models\ProductCompletionTask;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminOutsourcingTasksPageTest extends TestCase
{
    use RefreshDatabase;

    public function createApplication()
    {
        $app = parent::createApplication();
        if (! $app->environment('testing')
            || $app['config']->get('database.connections.mysql.database') !== 'b2b_marketplace_test') {
            throw new \RuntimeException('Workflow tests require the isolated test database.');
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    #[Test]
    public function admin_sees_metrics_and_can_filter_tasks_needing_attention(): void
    {
        $admin = User::factory()->create()->assignRole(UserRole::ADMIN->value);
        $healthy = ProductCompletionTask::factory()->create([
            'status' => ProductCompletionTaskStatus::AVAILABLE,
            'due_at' => now()->addDays(2),
        ]);
        $failed = ProductCompletionTask::factory()->create([
            'status' => ProductCompletionTaskStatus::APPROVED,
            'content_applied_at' => null,
            'media_published_at' => null,
            'last_error' => 'خطای شبیه‌سازی‌شده هنگام اعمال محتوا',
            'due_at' => now()->addDay(),
        ]);

        Livewire::actingAs($admin)->test(Tasks::class)
            ->assertSee('نیازمند رسیدگی')
            ->set('attentionFilter', 'finalization')
            ->assertSee($failed->public_id)
            ->assertDontSee($healthy->public_id);
    }

    #[Test]
    public function search_matches_public_id_and_filters_reset(): void
    {
        $admin = User::factory()->create()->assignRole(UserRole::ADMIN->value);
        $task = ProductCompletionTask::factory()->create([
            'status' => ProductCompletionTaskStatus::AVAILABLE,
            'due_at' => now()->addDays(2),
        ]);
        $other = ProductCompletionTask::factory()->create([
            'status' => ProductCompletionTaskStatus::AVAILABLE,
            'due_at' => now()->addDays(2),
        ]);

        Livewire::actingAs($admin)->test(Tasks::class)
            ->set('search', $task->public_id)
            ->assertSee($task->public_id)
            ->assertDontSee($other->public_id)
            ->call('resetFilters')
            ->assertSee($other->public_id);
    }

    #[Test]
    public function create_form_is_hidden_until_requested(): void
    {
        $admin = User::factory()->create()->assignRole(UserRole::ADMIN->value);

        Livewire::actingAs($admin)->test(Tasks::class)
            ->assertDontSee('ایجاد و انتشار تسک')
            ->call('toggleCreateForm')
            ->assertSee('ایجاد و انتشار تسک');
    }
}
