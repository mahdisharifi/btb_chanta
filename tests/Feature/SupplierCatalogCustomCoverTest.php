<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Supplier\Catalog\CustomCoverSettings;
use App\Models\CatalogTemplate;
use App\Models\Supplier;
use App\Services\CatalogCustomCoverService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SupplierCatalogCustomCoverTest extends TestCase
{
    private CatalogCustomCoverService $covers;

    protected function setUp(): void
    {
        parent::setUp();

        // This suite only creates disposable tables in memory; it never runs migrations.
        config(['database.connections.catalog_cover_test' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]]);
        DB::setDefaultConnection('catalog_cover_test');
        $schema = DB::connection('catalog_cover_test')->getSchemaBuilder();
        $schema->create('suppliers', function (Blueprint $table): void {
            $table->id();
        });
        $schema->create('catalog_templates', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('supplier_id');
            $table->string('name');
            $table->json('settings')->nullable();
            $table->json('config')->nullable();
            $table->timestamps();
        });
        DB::table('suppliers')->insert([['id' => 1], ['id' => 2]]);
        $this->covers = app(CatalogCustomCoverService::class);
    }

    public function test_site_default_and_custom_are_separate_from_supplier_default(): void
    {
        $this->catalog(1, true);

        foreach (['default', 'custom'] as $theme) {
            $settings = $this->covers->applyTheme(['cover_theme' => $theme], 1);
            $this->assertSame(['cover_theme' => $theme], $settings);
        }

        $own = $this->catalog(1, false, '#112233');
        $settings = $this->covers->applyTheme(['cover_theme' => 'custom'], 1, $own->settings);
        $this->assertSame($own->settings['custom_cover_table'], $settings['custom_cover_table']);
    }

    public function test_custom_default_copies_supplier_design_for_new_and_existing_catalogs(): void
    {
        $default = $this->catalog(1, true, '#123456');
        $own = $this->catalog(1, false, '#654321');
        $this->catalog(2, true, '#abcdef');

        foreach ([[], $own->settings] as $existing) {
            $settings = $this->covers->applyTheme(['cover_theme' => 'custom_default'], 1, $existing);
            $this->assertSame('custom_default', $settings['cover_theme']);
            $this->assertSame($default->settings['custom_cover_table']['settings'], $settings['custom_cover_table']['settings']);
            $this->assertSame($default->settings['custom_cover_table']['rows'], $settings['custom_cover_table']['rows']);
            $this->assertFalse($settings['custom_cover_table']['is_default_template']);
            $this->assertNull($settings['custom_cover_table']['source_catalog_id']);
        }
        $this->assertTrue($default->fresh()->settings['custom_cover_table']['is_default_template']);
    }

    public function test_saving_edited_copy_only_changes_its_own_custom_cover(): void
    {
        $default = $this->catalog(1, true);
        $copy = CatalogTemplate::create([
            'supplier_id' => 1,
            'name' => 'Copy',
            'settings' => $this->covers->applyTheme(['cover_theme' => 'custom_default', 'description' => 'Keep me'], 1),
        ]);

        $editor = new CustomCoverSettings();
        $editor->catalogTemplate = $copy;
        $editor->loadSettings();
        $editor->coverSettings['background_color'] = '#112233';
        $editor->rows = [['cells' => [['content_type' => 'text', 'content_value' => 'My design']]]];
        $editor->save();

        $settings = $copy->fresh()->settings;
        $this->assertSame('custom', $settings['cover_theme']);
        $this->assertSame('Keep me', $settings['description']);
        $this->assertSame('#112233', $settings['custom_cover_table']['settings']['background_color']);
        $this->assertSame($editor->rows, $settings['custom_cover_table']['rows']);
        $this->assertFalse($settings['custom_cover_table']['is_default_template']);
        $this->assertSame('#667eea', $default->fresh()->settings['custom_cover_table']['settings']['background_color']);
        $this->assertSame($default->id, $this->covers->defaultForSupplier(1)->id);
    }

    public function test_promoting_edited_cover_replaces_only_this_suppliers_default(): void
    {
        $previous = $this->catalog(1, true);
        $next = $this->catalog(1, false, '#112233');
        $otherSupplier = $this->catalog(2, true, '#ffffff');
        $legacy = $this->catalog(1, false, '#abcdef');
        $legacySettings = $legacy->settings;
        $legacySettings['custom_cover_table']['source_catalog_id'] = $previous->id;
        $legacy->update(['settings' => $legacySettings]);

        $this->covers->save($next, ['background_color' => '#222222'], [['cells' => []]], true);

        $this->assertFalse($previous->fresh()->settings['custom_cover_table']['is_default_template']);
        $this->assertTrue($next->fresh()->settings['custom_cover_table']['is_default_template']);
        $this->assertSame($next->id, $this->covers->defaultForSupplier(1)->id);
        $this->assertSame($otherSupplier->id, $this->covers->defaultForSupplier(2)->id);
        $this->assertSame($legacySettings, $legacy->fresh()->settings);
        $new = $this->covers->applyTheme(['cover_theme' => 'custom_default'], 1);
        $this->assertSame('#222222', $new['custom_cover_table']['settings']['background_color']);
    }

    public function test_updating_default_does_not_overwrite_saved_catalog_copies(): void
    {
        $default = $this->catalog(1, true);
        $copy = $this->covers->applyTheme(['cover_theme' => 'custom_default'], 1);
        $legacy = $this->catalog(1, false, '#abcdef');
        $legacySettings = $legacy->settings;
        $legacySettings['custom_cover_table']['source_catalog_id'] = $default->id;
        $legacy->update(['settings' => $legacySettings]);

        $this->covers->save($default, ['background_color' => '#000000'], [], true);

        $saved = $this->covers->applyTheme(['cover_theme' => 'custom_default', 'description' => 'New description'], 1, $copy);
        $this->assertSame($copy['custom_cover_table'], $saved['custom_cover_table']);
        $this->assertSame('New description', $saved['description']);
        $this->assertSame($legacySettings, $legacy->fresh()->settings);
        $new = $this->covers->applyTheme(['cover_theme' => 'custom_default'], 1);
        $this->assertSame('#000000', $new['custom_cover_table']['settings']['background_color']);
    }

    public function test_missing_supplier_default_returns_validation_error_without_using_another_supplier(): void
    {
        $this->catalog(2, true);
        $this->assertNull($this->covers->defaultForSupplier(1));

        try {
            $this->covers->applyTheme(['cover_theme' => 'custom_default'], 1);
            $this->fail('A missing custom default must not silently use the site default.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('coverTheme', $exception->errors());
        }
    }

    public function test_removing_default_still_allows_existing_copies_to_be_saved(): void
    {
        $default = $this->catalog(1, true);
        $copy = $this->covers->applyTheme(['cover_theme' => 'custom_default'], 1);
        $this->covers->save($default, ['background_color' => '#ffffff'], [], false);

        $this->assertNull($this->covers->defaultForSupplier(1));
        $saved = $this->covers->applyTheme(['cover_theme' => 'custom_default'], 1, $copy);
        $this->assertSame($copy['custom_cover_table'], $saved['custom_cover_table']);
    }

    public function test_pdf_templates_render_custom_default_design_separately_from_site_default(): void
    {
        $template = $this->catalog(1, true);
        $settings = $this->covers->applyTheme(['cover_theme' => 'custom_default'], 1);
        $data = [
            'supplierName' => 'Supplier',
            'supplier' => new Supplier(['company_name' => 'Supplier']),
            'template' => $template,
            'settings' => $settings,
            'coverTheme' => 'custom_default',
            'showCoverPage' => true,
            'supplierLogoUrl' => null,
            'supplierLogoPath' => null,
            'forPdf' => true,
            'groupedProducts' => [],
            'selectedPriceTypes' => [],
            'priceLabels' => [],
            'separateCategories' => false,
        ];

        foreach (['pdf.supplier-catalog', 'pdf.supplier-catalog2'] as $view) {
            $custom = view($view, $data)->render();
            $this->assertStringContainsString('Supplier design', $custom);
            $site = view($view, array_replace($data, ['coverTheme' => 'default']))->render();
            $this->assertStringNotContainsString('Supplier design', $site);
        }
    }

    private function catalog(int $supplierId, bool $isDefault, string $color = '#667eea'): CatalogTemplate
    {
        return CatalogTemplate::create([
            'supplier_id' => $supplierId,
            'name' => 'Catalog',
            'settings' => [
                'cover_theme' => 'custom',
                'custom_cover_table' => [
                    'source_catalog_id' => null,
                    'is_default_template' => $isDefault,
                    'settings' => ['background_color' => $color, 'padding' => 20],
                    'rows' => [[
                        'height' => 100,
                        'height_unit' => 'px',
                        'cells' => [['content_type' => 'text', 'content_value' => 'Supplier design']],
                    ]],
                ],
            ],
        ]);
    }
}
