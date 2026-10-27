<?php

namespace Tests\Feature;

use App\Http\Middleware\RedirectLegacySupplierSlug;
use App\Livewire\Admin\Suppliers\Edit;
use App\Models\Supplier;
use App\Models\User;
use App\Services\SupplierSlugService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class SupplierSlugRedirectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'RoleSeeder']);
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        $this->actingAs($admin);
        Route::get('/_slug-test/{slug}', fn () => response('passed'))
            ->middleware(RedirectLegacySupplierSlug::class);
    }

    public function test_changes_are_audited_and_redirect_directly_to_current_slug(): void
    {
        $supplier = Supplier::factory()->create(['slug' => 'first']);
        $service = app(SupplierSlugService::class);
        $service->change($supplier, 'second');
        $service->change($supplier, 'third');

        $this->assertDatabaseHas('supplier_slug_redirects', [
            'supplier_id' => $supplier->id, 'old_slug' => 'first',
            'new_slug' => 'second', 'created_by' => auth()->id(),
        ]);
        $this->get('/_slug-test/first')->assertStatus(301)
            ->assertRedirect(route('suppliers.show', 'third'));
        $this->get('/suppliers/first')->assertStatus(301)
            ->assertRedirect(route('suppliers.show', 'third'));
        $this->get('/_slug-test/second')->assertStatus(301)
            ->assertRedirect(route('suppliers.show', 'third'));
        $this->get('/_slug-test/third')->assertOk()->assertSee('passed');
        $this->get('/_slug-test/missing')->assertOk()->assertSee('passed');
    }

    public function test_inactive_and_deleted_suppliers_do_not_redirect(): void
    {
        $supplier = Supplier::factory()->create(['slug' => 'old']);
        app(SupplierSlugService::class)->change($supplier, 'current');
        $supplier->refresh()->update(['is_active' => false]);
        $this->get('/_slug-test/old')->assertOk();
        $supplier->update(['is_active' => true]);
        $supplier->delete();
        $this->get('/_slug-test/old')->assertOk();
    }

    public function test_historical_and_deleted_current_slugs_remain_reserved(): void
    {
        $supplier = Supplier::factory()->create(['slug' => 'reserved']);
        app(SupplierSlugService::class)->change($supplier, 'current');
        $other = Supplier::factory()->create(['slug' => 'other']);
        foreach ([$supplier, $other] as $target) {
            try {
                app(SupplierSlugService::class)->change($target, 'reserved');
                $this->fail('Historical slug was reused.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('slug', $exception->errors());
            }
        }
        $supplier->delete();
        $this->expectException(ValidationException::class);
        app(SupplierSlugService::class)->change($other, 'current');
    }

    public function test_service_requires_authenticated_super_admin_even_for_noop(): void
    {
        $supplier = Supplier::factory()->create(['slug' => 'unchanged']);
        $user = User::factory()->create();
        $user->assignRole('supplier');
        $this->actingAs($user);
        $this->expectException(AuthorizationException::class);
        app(SupplierSlugService::class)->change($supplier, 'unchanged');
    }

    public function test_guest_cannot_change_slug(): void
    {
        $supplier = Supplier::factory()->create(['slug' => 'old']);
        auth()->logout();
        $this->expectException(AuthorizationException::class);
        app(SupplierSlugService::class)->change($supplier, 'new');
    }

    public function test_ordinary_admin_cannot_change_slug_through_service(): void
    {
        $supplier = Supplier::factory()->create(['slug' => 'old']);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);

        $this->expectException(AuthorizationException::class);
        app(SupplierSlugService::class)->change($supplier, 'new');
    }

    public function test_noop_does_not_create_history_and_empty_slug_is_invalid(): void
    {
        $supplier = Supplier::factory()->create(['slug' => 'same']);
        app(SupplierSlugService::class)->change($supplier, 'same');
        $this->assertDatabaseCount('supplier_slug_redirects', 0);
        $this->expectException(ValidationException::class);
        app(SupplierSlugService::class)->change($supplier, '!!!');
    }

    public function test_outer_transaction_rolls_back_slug_and_audit(): void
    {
        $supplier = Supplier::factory()->create(['slug' => 'old']);
        try {
            DB::transaction(function () use ($supplier): void {
                app(SupplierSlugService::class)->change($supplier, 'new');
                throw new \RuntimeException('rollback');
            });
        } catch (\RuntimeException) {
            $this->assertSame('old', $supplier->fresh()->slug);
            $this->assertDatabaseCount('supplier_slug_redirects', 0);
        }
    }

    public function test_action_checks_profile_policy_before_validation(): void
    {
        $supplier = Supplier::factory()->create(['slug' => 'old']);
        $this->actingAs(User::factory()->create());
        $edit = new Edit;
        $edit->supplier = $supplier;
        $this->expectException(AuthorizationException::class);
        $edit->register();
    }

    public function test_owner_cannot_change_slug_through_action(): void
    {
        $supplier = Supplier::factory()->create(['slug' => 'old']);
        $supplier->user->assignRole('supplier');
        $this->actingAs($supplier->user);
        $edit = new Edit;
        $edit->supplier = $supplier;
        $edit->slug = 'new';
        $this->expectException(AuthorizationException::class);
        $edit->register();
    }

    public function test_action_surfaces_service_validation_and_rolls_back_profile(): void
    {
        Storage::fake('public');
        $oldNationalCard = 'suppliers/documents/old-national-card.pdf';
        $oldBusinessLicense = 'suppliers/documents/old-business-license.pdf';
        $unrelatedNationalCard = 'unrelated/national-card.pdf';
        $unrelatedBusinessLicense = 'unrelated/business-license.pdf';
        foreach ([$oldNationalCard, $oldBusinessLicense, $unrelatedNationalCard, $unrelatedBusinessLicense] as $path) {
            Storage::disk('public')->put($path, 'original document');
        }
        $supplier = Supplier::factory()->create([
            'slug' => 'old',
            'national_card_file' => $oldNationalCard,
            'business_license_file' => $oldBusinessLicense,
        ]);
        $edit = Mockery::mock(Edit::class)->makePartial();
        $edit->shouldReceive('validate')->once()->andReturn([]);
        $edit->supplier = $supplier;
        $edit->slug = 'new';
        $edit->company_name = 'Changed profile';
        $edit->existingNationalCard = $unrelatedNationalCard;
        $edit->existingBusinessLicense = $unrelatedBusinessLicense;
        $edit->national_card_file = UploadedFile::fake()->create('national-card.pdf', 10, 'application/pdf');
        $edit->business_license_file = UploadedFile::fake()->create('business-license.pdf', 10, 'application/pdf');
        $service = Mockery::mock(SupplierSlugService::class);
        $service->shouldReceive('change')->once()->andReturnUsing(function (Supplier $target, string $slug): void {
            $this->assertCount(4, Storage::disk('public')->allFiles('suppliers/documents'));
            (new SupplierSlugService)->change($target, $slug);
            throw ValidationException::withMessages(['slug' => 'Reserved']);
        });
        $this->app->instance(SupplierSlugService::class, $service);
        try {
            $edit->register();
            $this->fail('Validation was swallowed.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('slug', $exception->errors());
            $this->assertSame($supplier->company_name, $supplier->fresh()->company_name);
            $this->assertSame('old', $supplier->fresh()->slug);
            $this->assertDatabaseCount('supplier_slug_redirects', 0);
            $this->assertSame($oldNationalCard, $supplier->fresh()->national_card_file);
            $this->assertSame($oldBusinessLicense, $supplier->fresh()->business_license_file);
            Storage::disk('public')->assertExists([
                $oldNationalCard, $oldBusinessLicense, $unrelatedNationalCard, $unrelatedBusinessLicense,
            ]);
            $this->assertCount(2, Storage::disk('public')->allFiles('suppliers/documents'));
        }
    }

    public function test_existing_history_is_never_reassigned(): void
    {
        $owner = Supplier::factory()->create(['slug' => 'owner']);
        $supplier = Supplier::factory()->create(['slug' => 'conflicting']);
        \App\Models\SupplierSlugRedirect::create([
            'supplier_id' => $owner->id, 'old_slug' => 'conflicting',
            'new_slug' => 'owner', 'created_by' => auth()->id(),
        ]);
        try {
            app(SupplierSlugService::class)->change($supplier, 'different');
            $this->fail('Conflicting history was overwritten.');
        } catch (ValidationException) {
            $this->assertDatabaseHas('supplier_slug_redirects', [
                'old_slug' => 'conflicting', 'supplier_id' => $owner->id, 'new_slug' => 'owner',
            ]);
            $this->assertSame('conflicting', $supplier->fresh()->slug);
        }
    }

    public function test_current_slug_collision_does_not_create_history(): void
    {
        Supplier::factory()->create(['slug' => 'taken']);
        $supplier = Supplier::factory()->create(['slug' => 'original']);
        try {
            app(SupplierSlugService::class)->change($supplier, 'taken');
            $this->fail('Current slug was reused.');
        } catch (ValidationException) {
            $this->assertSame('original', $supplier->fresh()->slug);
            $this->assertDatabaseCount('supplier_slug_redirects', 0);
        }
    }
}
