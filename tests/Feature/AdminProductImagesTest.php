<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Admin\Products\Create;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ProductImageService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AdminProductImagesTest extends TestCase
{
    use RefreshDatabase;

    public function createApplication()
    {
        $app = parent::createApplication();
        if (! $app->environment('testing') || $app['config']->get('database.connections.mysql.database') !== 'b2b_marketplace_test') {
            throw new \RuntimeException('Image tests require the isolated test database.');
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');
        Storage::fake('public');
    }

    private function tiers(): array
    {
        return [
            ['name' => 'Small', 'min_quantity' => 1, 'max_quantity' => 9],
            ['name' => 'Medium', 'min_quantity' => 10, 'max_quantity' => 19],
            ['name' => 'Large', 'min_quantity' => 20, 'max_quantity' => null],
        ];
    }

    private function admin(): User
    {
        return User::factory()->create()->assignRole(UserRole::SUPER_ADMIN->value);
    }

    private function image(Product $product, int $index, bool $primary = false)
    {
        $upload = UploadedFile::fake()->image('old-'.$index.'.png', 1000, 800 + $index * 10);
        $metadata = app(ProductImageService::class)->process($upload->getRealPath(), $product->slug, $index, withPdf: $primary);

        return $product->galleries()->create([
            ...$metadata,
            'file_type' => 'image',
            'is_primary' => $primary,
            'sort_order' => $index,
        ]);
    }

    public function test_creation_generates_all_variants_and_repeated_save_does_not_duplicate_uploads(): void
    {
        $component = Livewire::actingAs($this->admin())->test(Create::class)
            ->set('title', 'Image product')->set('slug', 'image-product')
            ->set('category_id', Category::factory()->create()->id)
            ->set('supplier_id', Supplier::factory()->create()->id)
            ->set('quantity_tiers', $this->tiers())
            ->set('primary_image', UploadedFile::fake()->image('main.png', 1200, 900))
            ->set('gallery_images', [UploadedFile::fake()->image('extra.png', 900, 900)])
            ->call('save')->assertHasNoErrors()->assertSet('primary_image', null)->assertSet('gallery_images', []);
        $product = Product::findOrFail($component->get('productId'));
        $this->assertSame(2, $product->galleries()->count());
        foreach ($product->galleries as $gallery) {
            foreach (['xs', 'sm', 'md', 'lg', 'xl', 'original'] as $size) {
                Storage::disk('public')->assertExists($gallery->versions[$size]['path']);
            }
            if ($gallery->is_primary) {
                Storage::disk('public')->assertExists($gallery->versions['pdf']['path']);
            }
        }
        $component->call('save')->assertHasNoErrors();
        $this->assertSame(1, Product::count());
        $this->assertSame(2, $product->galleries()->count());
    }

    public function test_primary_replacement_replaces_every_variant_and_preserves_gallery(): void
    {
        $product = Product::factory()->create(['quantity_tiers' => $this->tiers()]);
        $primary = $this->image($product, 1, true);
        $gallery = $this->image($product, 2);
        $primary->update(['sort_order' => 2]);
        $gallery->update(['sort_order' => 1]);
        $oldVersions = $primary->versions;
        $galleryHash = md5_file(Storage::disk('public')->path($gallery->file_path));
        Livewire::actingAs($this->admin())->test(Create::class, ['product' => $product])
            ->set('primary_image', UploadedFile::fake()->image('replacement.png', 1200, 600))
            ->call('save')->assertHasNoErrors()->assertSet('primary_image', null);
        $primary->refresh();
        $this->assertSame(2, $product->galleries()->count());
        $this->assertSame(1, $product->galleries()->where('is_primary', true)->count());
        $this->assertSame(1, $primary->sort_order);
        $this->assertSame(2, $gallery->refresh()->sort_order);
        foreach ($oldVersions as $size => $oldVersion) {
            Storage::disk('public')->assertMissing($oldVersion['path']);
            Storage::disk('public')->assertExists($primary->versions[$size]['path']);
            $this->assertNotSame($oldVersion['path'], $primary->versions[$size]['path']);
            $this->assertEqualsWithDelta(2, $primary->versions[$size]['width'] / $primary->versions[$size]['height'], 0.03);
        }
        $this->assertSame($galleryHash, md5_file(Storage::disk('public')->path($gallery->file_path)));
    }

    public function test_gallery_addition_after_deletion_preserves_old_files_and_is_not_replayed(): void
    {
        $product = Product::factory()->create(['quantity_tiers' => $this->tiers()]);
        $this->image($product, 1, true);
        $deleted = $this->image($product, 2);
        $retained = $this->image($product, 3);
        $retainedHashes = collect($retained->versions)->map(fn ($version) => md5_file(Storage::disk('public')->path($version['path'])));
        $component = Livewire::actingAs($this->admin())->test(Create::class, ['product' => $product])
            ->call('markGalleryForDeletion', $deleted->id)
            ->set('gallery_images', [UploadedFile::fake()->image('new.png', 1200, 600)])
            ->call('save')->assertHasNoErrors()->assertSet('gallery_images', []);
        $this->assertSame(3, $product->galleries()->count());
        foreach ($retained->versions as $size => $version) {
            $this->assertSame($retainedHashes[$size], md5_file(Storage::disk('public')->path($version['path'])));
        }
        Storage::disk('public')->assertMissing($deleted->file_path);
        $component->call('save')->assertHasNoErrors();
        $this->assertSame(3, $product->galleries()->count());
        $component->set('gallery_images', [UploadedFile::fake()->image('another.png', 800, 900)])
            ->call('save')->assertHasNoErrors();
        $this->assertSame(4, $product->galleries()->count());
        $this->assertSame(4, $product->galleries()->pluck('file_path')->unique()->count());
    }

    public function test_primary_replacement_preserves_files_still_used_by_another_gallery_image(): void
    {
        $product = Product::factory()->create(['quantity_tiers' => $this->tiers()]);
        $primary = $this->image($product, 1, true);
        $oldVersions = $primary->versions;
        $product->galleries()->create([
            'file_path' => $primary->file_path,
            'file_type' => 'image',
            'is_primary' => false,
            'sort_order' => 2,
            'versions' => $oldVersions,
        ]);
        Livewire::actingAs($this->admin())->test(Create::class, ['product' => $product])
            ->set('primary_image', UploadedFile::fake()->image('replacement.png', 1200, 600))
            ->call('save')->assertHasNoErrors();
        foreach ($oldVersions as $version) {
            Storage::disk('public')->assertExists($version['path']);
        }
        $this->assertNotSame($oldVersions['original']['path'], $primary->refresh()->file_path);
    }
}
