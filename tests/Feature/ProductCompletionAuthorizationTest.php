<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\ProductCompletionTask;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCompletionAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_outsourcer_cannot_view_another_users_task(): void
    {
        $owner = User::factory()->create()->assignRole(UserRole::OUTSOURCER->value);
        $attacker = User::factory()->create()->assignRole(UserRole::OUTSOURCER->value);
        $task = ProductCompletionTask::factory()->assignedTo($owner)->create();

        $this->actingAs($attacker)
            ->get(route('outsourcer.tasks.show', $task))
            ->assertForbidden();
    }

    public function test_workspace_does_not_render_commercial_tier_data(): void
    {
        $outsourcer = User::factory()->create()->assignRole(UserRole::OUTSOURCER->value);
        $task = ProductCompletionTask::factory()->inProgress($outsourcer)->create();
        $task->product->update([
            'quantity_tiers' => [['name' => 'SECRET-TIER-987654321', 'min_quantity' => 1]],
        ]);

        $this->actingAs($outsourcer)
            ->get(route('outsourcer.tasks.workspace', $task))
            ->assertOk()
            ->assertDontSee('SECRET-TIER-987654321');
    }
}
