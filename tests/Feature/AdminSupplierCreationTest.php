<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Admin\Suppliers\Create;
use App\Livewire\User\RegisterSupplier;
use App\Models\City;
use App\Models\Province;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminSupplierCreationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'RoleSeeder']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole(UserRole::SUPER_ADMIN);
    }

    public function test_selector_redirects_without_creating_supplier(): void
    {
        $user = User::factory()->create();
        Livewire::actingAs($this->admin)->test(Create::class)
            ->set('user_id', $user->id)->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('user.register.supplier.complete', [
                'user_id' => $user->id, 'source' => 'admin',
            ]));
        $this->assertDatabaseMissing('suppliers', ['user_id' => $user->id]);
    }

    public function test_admin_completes_selected_users_profile_without_changing_admin_account(): void
    {
        $user = User::factory()->create();
        $component = Livewire::actingAs($this->admin)
            ->withQueryParams(['source' => 'admin', 'user_id' => $user->id])
            ->test(RegisterSupplier::class)
            ->assertSet('adminMode', true)->assertSet('mobile', $user->mobile);
        $this->assertDatabaseCount('suppliers', 0);
        $this->fillProfile($component)->call('register')->assertHasNoErrors()
            ->assertRedirect(route('admin.suppliers.index'));
        $this->assertDatabaseHas('suppliers', [
            'user_id' => $user->id, 'slug' => 'pars-tejarat',
            'verification_status' => 'pending', 'is_active' => false,
        ]);
        $this->assertDatabaseMissing('suppliers', ['user_id' => $this->admin->id]);
        $this->assertTrue($this->admin->fresh()->hasRole(UserRole::SUPER_ADMIN));
        $this->assertTrue($user->fresh()->hasRole('regular_supplier'));
    }

    public function test_non_admin_cannot_select_another_user(): void
    {
        $user = User::factory()->create();
        Livewire::actingAs($user)->withQueryParams(['source' => 'admin', 'user_id' => $this->admin->id])
            ->test(RegisterSupplier::class)->assertNotFound();
    }

    public function test_selected_user_is_locked_against_client_changes(): void
    {
        $user = User::factory()->create();
        $this->expectException(\Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException::class);
        Livewire::actingAs($this->admin)->withQueryParams(['source' => 'admin', 'user_id' => $user->id])
            ->test(RegisterSupplier::class)->set('selectedUserId', $this->admin->id);
    }

    public function test_selector_rejects_user_with_existing_or_deleted_profile(): void
    {
        $supplier = Supplier::factory()->create();
        $supplier->delete();
        Livewire::actingAs($this->admin)->test(Create::class)
            ->set('user_id', $supplier->user_id)->call('save')->assertHasErrors('user_id');
    }

    public function test_self_registration_generates_unique_slug_and_stays_pending(): void
    {
        Supplier::factory()->create(['slug' => 'pars-tejarat', 'company_name' => 'Other Company']);
        $user = User::factory()->create();
        $component = Livewire::actingAs($user)->test(RegisterSupplier::class);
        $this->fillProfile($component)->call('register')->assertHasNoErrors()
            ->assertRedirect(route('account.pending'));
        $this->assertDatabaseHas('suppliers', ['user_id' => $user->id, 'slug' => 'pars-tejarat-2']);
    }

    public function test_profile_owner_cannot_change_published_slug(): void
    {
        $user = User::factory()->create();
        $user->assignRole('supplier');
        $supplier = Supplier::factory()->create(['user_id' => $user->id, 'slug' => 'published-supplier']);
        $component = Livewire::actingAs($user)->test(RegisterSupplier::class);
        $this->fillProfile($component)->set('slug', 'forged-slug')->call('register')->assertHasNoErrors();
        $this->assertSame('published-supplier', $supplier->fresh()->slug);
        $this->assertDatabaseCount('supplier_slug_redirects', 0);
    }

    public function test_admin_self_registration_preserves_privileged_role(): void
    {
        $component = Livewire::actingAs($this->admin)->test(RegisterSupplier::class);
        $this->assertDatabaseCount('suppliers', 0);
        $this->fillProfile($component)->call('register')->assertHasNoErrors();
        $this->assertTrue($this->admin->fresh()->hasRole(UserRole::SUPER_ADMIN));
    }

    public function test_persian_name_produces_nonempty_slug(): void
    {
        $user = User::factory()->create();
        $component = Livewire::actingAs($user)->test(RegisterSupplier::class);
        $this->fillProfile($component)->set('company_name', 'تأمین کننده پارس')
            ->call('register')->assertHasNoErrors();
        $this->assertMatchesRegularExpression('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $user->fresh()->supplier->slug);
    }

    public function test_document_deletion_path_cannot_be_forged(): void
    {
        $user = User::factory()->create();
        $user->assignRole('regular_supplier');
        Supplier::factory()->create(['user_id' => $user->id, 'slug' => 'safe-supplier']);
        $this->expectException(\Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException::class);
        Livewire::actingAs($user)->test(RegisterSupplier::class)
            ->set('existingNationalCard', 'another-users/document.pdf');
    }

    protected function fillProfile($component)
    {
        $countryId = \Illuminate\Support\Facades\DB::table('countries')->insertGetId(['name' => 'Iran']);
        $province = Province::create(['country_id' => $countryId, 'name' => 'Tehran', 'is_active' => true]);
        $city = City::create(['country_id' => $countryId, 'province_id' => $province->id, 'name' => 'Tehran', 'is_active' => true]);

        return $component->set('company_name', 'Pars Tejarat')
            ->set('mobile', '09120000010')->set('address', 'Tehran address')
            ->set('province_id', $province->id)->set('city_id', $city->id);
    }

    public function test_failed_document_write_preserves_existing_document(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $this->actingAs($this->admin);
        foreach (['national_card_file', 'business_license_file'] as $field) {
            $oldPath = 'suppliers/documents/'.$field.'.pdf';
            \Illuminate\Support\Facades\Storage::disk('public')->put($oldPath, 'original document');
            $supplier = Supplier::factory()->create(['slug' => 'supplier-'.str_replace('_', '-', $field), $field => $oldPath]);
            $component = \Mockery::mock(RegisterSupplier::class)->makePartial();
            $component->shouldReceive('validate')->once()->andReturn([]);
            $component->editMode = true;
            $component->supplier = $supplier;
            $upload = \Mockery::mock(\Livewire\Features\SupportFileUploads\TemporaryUploadedFile::class);
            $upload->shouldReceive('store')->once()->with('suppliers/documents', 'public')->andReturn(false);
            $component->{$field} = $upload;

            $component->register();

            $this->assertSame($oldPath, $supplier->fresh()->getAttribute($field));
            \Illuminate\Support\Facades\Storage::disk('public')->assertExists($oldPath);
            $this->assertTrue(session()->has('error'));
        }
    }
}
