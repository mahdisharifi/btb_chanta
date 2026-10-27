<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Admin\Products\Create;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminProductQuantityTiersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function admin(): User
    {
        return User::factory()->create()->assignRole(UserRole::SUPER_ADMIN->value);
    }

    private function productData(): array
    {
        return [
            'title' => 'Tier product',
            'slug' => 'tier-product',
            'category_id' => Category::factory()->create()->id,
            'supplier_id' => Supplier::factory()->create()->id,
        ];
    }

    public function test_three_tiers_can_be_saved_with_a_blank_last_max(): void
    {
        Livewire::actingAs($this->admin())->test(Create::class)
            ->set($this->productData())
            ->set('quantity_tiers', [
                ['name' => 'Retail', 'min_quantity' => 1, 'max_quantity' => 50],
                ['name' => 'Wholesale', 'min_quantity' => 51, 'max_quantity' => 200],
                ['name' => 'Bulk', 'min_quantity' => 201, 'max_quantity' => ''],
            ])
            ->call('save')
            ->assertHasNoErrors();

        $tiers = Product::query()->sole()->quantity_tiers;

        $this->assertCount(3, $tiers);
        $this->assertNull($tiers[2]['max_quantity']);
    }

    public function test_a_fourth_tier_can_be_added_after_capping_the_third(): void
    {
        $component = Livewire::actingAs($this->admin())->test(Create::class)
            ->set('quantity_tiers', [
                ['name' => 'Retail', 'min_quantity' => 1, 'max_quantity' => 50],
                ['name' => 'Wholesale', 'min_quantity' => 51, 'max_quantity' => 200],
                ['name' => 'Bulk', 'min_quantity' => 201, 'max_quantity' => null],
            ])
            ->set('quantity_tiers.2.max_quantity', 300)
            ->call('addQuantityTier')
            ->assertHasNoErrors();

        $component->assertCount('quantity_tiers', 4);
        $component->assertSet('quantity_tiers.3.min_quantity', 301);
        $component->assertSet('quantity_tiers.3.max_quantity', null);
    }
}
