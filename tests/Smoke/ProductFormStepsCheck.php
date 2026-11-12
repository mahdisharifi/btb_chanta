<?php

// Run with: php tests/Smoke/ProductFormStepsCheck.php
// All data lives in an isolated SQLite memory database.
$completed = false;
register_shutdown_function(function () use (&$completed): void {
    if (!$completed) exit(1);
});

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config([
    'database.default' => 'wizard_check',
    'database.connections.wizard_check' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
    'cache.default' => 'array',
    'session.driver' => 'array',
]);
Illuminate\Support\Facades\DB::purge('wizard_check');
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

Schema::create('categories', function ($t) { $t->id(); $t->string('name')->nullable(); $t->integer('parent_id')->nullable(); $t->boolean('is_active')->default(true); $t->softDeletes(); });
Schema::create('suppliers', function ($t) { $t->id(); $t->string('company_name')->nullable(); $t->string('branch_name')->nullable(); $t->integer('user_id')->default(1); $t->boolean('is_active')->default(true); $t->integer('parent_supplier_id')->nullable(); $t->softDeletes(); });
Schema::create('products', function ($t) {
    $t->id();
    foreach (['title', 'slug', 'sku', 'status', 'packaging_unit_type', 'short_description', 'full_description', 'seo_title', 'seo_description', 'seo_keywords', 'seo_canonical_url'] as $field) $t->text($field)->nullable();
    foreach (['supplier_id', 'category_id', 'family_product_id', 'quantity_per_packaging'] as $field) $t->integer($field)->nullable();
    $t->text('quantity_tiers')->nullable(); $t->boolean('is_active')->default(false); $t->integer('content_version')->default(0); $t->timestamp('content_updated_at')->nullable(); $t->timestamps(); $t->softDeletes();
});
Schema::create('attribute_values', function ($t) { $t->id(); $t->integer('attribute_id')->nullable(); $t->string('value')->nullable(); $t->softDeletes(); });
Schema::create('attribute_product', function ($t) { $t->integer('product_id'); $t->integer('attribute_value_id'); $t->string('value')->nullable(); $t->timestamps(); });
Schema::create('product_galleries', function ($t) {
    $t->id(); $t->integer('product_id');
    foreach (['file_type', 'base_name', 'file_name', 'file_path', 'mime_type', 'title', 'alt_text', 'poster_path'] as $field) $t->string($field)->nullable();
    foreach (['sort_order', 'width', 'height', 'file_size'] as $field) $t->integer($field)->nullable();
    $t->boolean('is_primary')->default(false); $t->boolean('is_seo_image')->default(false); $t->text('versions')->nullable(); $t->timestamps(); $t->softDeletes();
});
Schema::create('catalog_snapshot_revisions', function ($t) { $t->string('scope_type'); $t->integer('scope_id'); $t->integer('revision')->default(0); $t->unique(['scope_type', 'scope_id']); });
Schema::create('product_supplier', function ($t) { $t->id(); $t->integer('product_id'); $t->integer('supplier_id'); $t->boolean('is_active')->default(false); $t->softDeletes(); });
Schema::create('product_prices', function ($t) { $t->id(); $t->integer('product_id'); $t->integer('product_supplier_id')->nullable(); $t->integer('tier_index')->default(0); $t->integer('price_type_id')->nullable(); $t->boolean('is_active')->default(true); });
Schema::create('attributes', function ($t) { $t->id(); $t->string('name'); $t->string('type')->default('text'); $t->boolean('is_multiple')->default(false); $t->softDeletes(); });
Schema::create('attribute_category', function ($t) { $t->integer('category_id'); $t->integer('attribute_id'); $t->integer('display_order')->default(0); $t->boolean('is_required')->default(true); });
DB::table('categories')->insert(['id' => 1]);
DB::table('suppliers')->insert(['id' => 1, 'company_name' => 'Wizard check']);
DB::table('attributes')->insert(['id' => 1, 'name' => 'Required attribute']);
DB::table('attribute_category')->insert(['attribute_id' => 1, 'category_id' => 1, 'is_required' => true]);
DB::table('attribute_values')->insert(['id' => 1, 'attribute_id' => 1, 'value' => 'Existing value']);

class WizardUser extends App\Models\User {
    public function supplier(): Illuminate\Database\Eloquent\Relations\HasOne { return $this->hasOne(App\Models\Supplier::class, 'user_id')->whereNull('parent_supplier_id'); }
    public function currentSupplier(): App\Models\Supplier { return App\Models\Supplier::findOrFail(1); }
}
$user = new WizardUser(); $user->id = 1;
$user->setRelation('roles', new Illuminate\Database\Eloquent\Collection());
auth()->guard()->setUser($user);
Illuminate\Support\Facades\Gate::before(fn () => true);
app()->instance(App\Services\ProductImageService::class, new class extends App\Services\ProductImageService {
    public function process(string $sourcePath, string $slug, int $index, bool $withPdf = false, ?string $baseName = null): array {
        return ['base_name' => $baseName, 'file_name' => $baseName.'.webp', 'file_path' => 'wizard/'.$baseName.'.webp', 'file_size' => 10, 'versions' => []];
    }
});

class ProductWizardCheck extends App\Livewire\Admin\Products\Create
{
    public int $saveCalls = 0;

    public function save()
    {
        $this->saveCalls++;
        return parent::save();
    }

    protected function isSupplierDraftModeContext(): bool { return $this->supplierDraftMode; }

    public function mount(?App\Models\Product $product = null, bool $editing = false, bool $supplier = false)
    {
        $this->title = 'Wizard check';
        $this->slug = 'wizard-check-'.($editing ? 'edit' : ($supplier ? 'supplier' : 'admin'));
        $this->category_id = 1;
        $this->supplier_id = 1;
        $this->status = App\Enums\ProductStatus::DRAFT->value;
        $this->isEdit = $editing;
        $this->supplierDraftMode = $supplier;
        $this->quantity_per_packaging = 12;
        $this->quantity_tiers = $this->generateAutoTiers();
        $this->tiersApplied = true;
        $this->loadCategoryAttributes();
        if ($editing) {
            $stored = App\Models\Product::create(['title' => $this->title, 'slug' => $this->slug, 'category_id' => 1, 'supplier_id' => 1, 'sku' => App\Models\Product::generateUniqueSku(), 'status' => 'draft', 'short_description' => 'Existing description']);
            $stored->attributeValues()->attach(1, ['value' => 'Existing value']);
            $this->productId = $stored->id;
            $this->sku = $stored->sku;
        }
    }

    protected function authorizeProductFormStep(): void {}
}

function verify($condition, $message): void {
    if (!$condition) throw new RuntimeException($message);
}

Livewire\Livewire::component('product-wizard-check', ProductWizardCheck::class);
// This suite isolates the parent wizard; the price editor has its own integration tests.
class EmbeddedPricingWizardCheck extends Livewire\Component
{
    public function mount(App\Models\Product $product): void {}
    public function render() { return '<div data-embedded-pricing>Pricing editor</div>'; }
}
Livewire\Livewire::component('supplier.products.edit-price', EmbeddedPricingWizardCheck::class);
$unsavedWizard = new ProductWizardCheck();
$unsavedWizard->supplierDraftMode = true;
$pricingDenied = false;
try { $unsavedWizard->goToPricingStep(); }
catch (Symfony\Component\HttpKernel\Exception\HttpException $exception) { $pricingDenied = $exception->getStatusCode() === 403; }
verify($pricingDenied, 'Unsaved wizard could open pricing');
foreach ([[false, false], [true, false], [false, true]] as [$editing, $supplier]) {
    $component = Livewire\Livewire::test('product-wizard-check', compact('editing', 'supplier'));
    if ($supplier) {
        $component->set('primary_image', Illuminate\Http\UploadedFile::fake()->image('wizard.jpg'));
    }
    verify($component->get('formStep') === 1, 'Initial step');
    verify(str_contains($component->html(), 'id="field-packaging"'), 'Packaging missing in step one');
    verify(!str_contains($component->html(), 'id="field-ai-description"'), 'Descriptions visible in step one');
    $before = DB::table('products')->count();
    $component->call('submitProductForm');
    $savedId = $component->get('productId');
    verify($savedId && $component->get('firstFormStepSaved'), 'First step did not persist');
    verify(DB::table('products')->count() === $before + ($editing ? 0 : 1), 'Incorrect product count after first step');
    verify(App\Models\Product::find($savedId)->quantity_per_packaging === 12, 'Packaging not saved');
    if ($editing) {
        verify(App\Models\Product::find($savedId)->short_description === 'Existing description', 'Step one overwrote description');
        verify(DB::table('attribute_product')->where('product_id', $savedId)->count() === 1, 'Step one erased existing attributes');
    }
    $sku = $component->get('sku');
    $mediaCount = DB::table('product_galleries')->where('product_id', $savedId)->count();
    verify($component->get('formStep') === 2, 'Continue failed');
    verify(!str_contains($component->html(), 'id="field-media"'), 'Media visible in step two');
    verify(str_contains($component->html(), 'id="field-ai-description"'), 'Descriptions missing in step two');
    verify(str_contains($component->html(), 'id="field-seo"') === !$supplier, 'SEO role visibility');
    verify($component->get('saveCalls') === 0, 'First step called full save');
    verify(DB::table('attribute_product')->where('product_id', $savedId)->count() === ($editing ? 1 : 0), 'Required attribute blocked optional step');
    $component->call('previousFormStep')->call('submitProductForm');
    verify($component->get('productId') === $savedId && $component->get('sku') === $sku, 'Repeat save changed identity');
    verify(DB::table('product_galleries')->where('product_id', $savedId)->count() === $mediaCount, 'Repeat save duplicated media');
    verify(DB::table('products')->count() === $before + ($editing ? 0 : 1), 'Repeat save duplicated product');
    $component->call('submitProductForm');
    verify($component->get('saveCalls') === 1, 'Second step did not call save');
    if ($supplier) {
        verify($component->get('formStep') === 3, 'Saved optional step did not open embedded pricing');
        verify(!isset($component->effects['redirect']), 'Stage two redirected instead of embedding pricing');
        verify(str_contains($component->html(), 'data-embedded-pricing'), 'Pricing editor not embedded');
        verify(!str_contains($component->html(), '<form novalidate'), 'Parent form wraps embedded pricing');
        $component->call('previousFormStep');
    }
    $component->set('short_description', 'Keep this description')->call('previousFormStep');
    verify($component->get('short_description') === 'Keep this description', 'Description lost on back');
    verify($component->get('quantity_tiers')[0]['max_quantity'] === 11, 'Packaging tier lost');
    verify(DB::table('products')->count() === $before + ($editing ? 0 : 1), 'Stage two duplicated product');
    $component->set('title', '')->call('nextFormStep');
    verify($component->get('formStep') === 1, 'Invalid first step advanced');
    verify(DB::table('products')->count() === $before + ($editing ? 0 : 1), 'Invalid first stage created a product');
    echo ($supplier ? 'Supplier create' : ($editing ? 'Admin edit' : 'Admin create'))." passed\n";
}

class SupplierEditWizardCheck extends App\Livewire\Supplier\Products\Edit
{
    public function mount(App\Models\Product $product)
    {
        $this->product = $product;
        $this->productId = $product->id;
        $this->sku = $product->sku;
        $this->title = 'Supplier edit check';
        $this->slug = 'supplier-edit-check';
        $this->category_id = 1;
        $this->supplier_id = 1;
        $this->quantity_per_packaging = 12;
        $this->quantity_tiers = $this->generateAutoTiers();
        $this->tiersApplied = true;
        $this->loadCategoryAttributes();
    }

    protected function authorizeProductFormStep(): void {}
}

Livewire\Livewire::component('supplier-edit-wizard-check', SupplierEditWizardCheck::class);
$component = Livewire\Livewire::test('supplier-edit-wizard-check', ['product' => App\Models\Product::create(['title' => 'Supplier edit check', 'slug' => 'supplier-edit-check', 'supplier_id' => 1, 'category_id' => 1, 'status' => 'draft'])]);
verify(str_contains($component->html(), 'id="field-packaging"'), 'Supplier edit packaging missing');
$component->call('submitProductForm');
verify($component->get('formStep') === 2, 'Supplier edit continue failed');
verify(str_contains($component->html(), 'wire:model="full_description"'), 'Supplier edit descriptions missing');
$component->set('full_description', 'Preserved edit')->call('previousFormStep');
verify($component->get('full_description') === 'Preserved edit', 'Supplier edit description lost');
verify(App\Models\Product::find($component->get('productId'))->quantity_per_packaging === 12, 'Supplier edit did not save first stage');
echo "Supplier edit passed\n";

// Skip required attributes without saving unfinished optional values.
$savedDescription = App\Models\Product::find($component->get('productId'))->full_description;
$component->call('nextFormStep')->set('attributeValues', [1 => 9999])->call('goToPricingStep');
verify($component->get('formStep') === 3, 'Required attribute blocked skipping step two');
verify(!isset($component->effects['redirect']), 'Skip redirected instead of embedding pricing');
verify(App\Models\Product::find($component->get('productId'))->full_description === $savedDescription, 'Skip saved unfinished optional data');
$component->call('previousFormStep');
verify($component->get('formStep') === 2, 'Pricing back button missed step two');
echo "Embedded pricing and optional step skip passed\n";

request()->query->set('step', 3);
$legacyProduct = App\Models\Product::create(['title' => 'Legacy draft', 'slug' => 'legacy-draft', 'supplier_id' => 1, 'category_id' => 1, 'status' => 'draft']);
$legacyWizard = new App\Livewire\Supplier\Products\Edit();
$legacyWizard->mount($legacyProduct);
verify($legacyWizard->formStep === 1, 'Legacy draft without persisted tiers opened pricing');
$readyWizard = new App\Livewire\Supplier\Products\Edit();
$readyWizard->mount(App\Models\Product::findOrFail($component->get('productId')));
verify($readyWizard->formStep === 3, 'Ready draft did not open pricing');
request()->query->remove('step');
echo "Existing and legacy draft pricing entry passed\n";

// Optional data, including invalid unfinished fields, cannot block stage one.
$optional = Livewire\Livewire::test('product-wizard-check');
$optional->set('slug', 'optional-unfinished-fields')->set('seo_canonical_url', 'unfinished-url')->set('selectedAttributeValues', [1 => 9999])->call('submitProductForm');
verify($optional->get('formStep') === 2 && $optional->get('firstFormStepSaved'), 'Optional fields blocked initial persistence');
echo "Optional unfinished fields passed\n";

// Catalog eligibility is restricted to draft owners, without making products public.
$draftId = $component->get('productId');
verify(App\Models\Product::forSupplierCatalog([1])->whereKey($draftId)->exists(), 'Owner draft excluded from catalog');
verify(!App\Models\Product::forSupplierCatalog([2])->whereKey($draftId)->exists(), 'Other supplier can see draft in catalog');
verify(!App\Models\Product::forSupplierCatalog()->whereKey($draftId)->exists(), 'Admin aggregate unexpectedly includes drafts');
verify(App\Models\Product::find($draftId)->status === App\Enums\ProductStatus::DRAFT, 'Draft was publicly approved');
DB::table('product_supplier')->insert(['product_id' => $draftId, 'supplier_id' => 1, 'is_active' => true]);
$offerId = DB::table('product_supplier')->where('product_id', $draftId)->value('id');
DB::table('product_prices')->insert(['product_id' => $draftId, 'product_supplier_id' => $offerId, 'price_type_id' => 1, 'is_active' => true]);
verify(App\Models\Product::forSupplierCatalog([1])->whereKey($draftId)->whereHas('productSuppliers', function ($offers) {
    $offers->where('supplier_id', 1)->where('is_active', true)->whereHas('prices', fn ($prices) => $prices->where('is_active', true));
})->exists(), 'Priced draft unavailable to catalog query');
echo "Catalog owner scope and priced draft passed\n";

// Selection exposes only this owner's branch drafts, including inactive branches for review.
DB::table('suppliers')->insert([
    ['id' => 2, 'user_id' => 1, 'parent_supplier_id' => 1, 'branch_name' => 'Second branch', 'is_active' => true],
    ['id' => 3, 'user_id' => 2, 'parent_supplier_id' => null, 'branch_name' => 'Foreign owner', 'is_active' => true],
    ['id' => 4, 'user_id' => 1, 'parent_supplier_id' => 1, 'branch_name' => 'Inactive branch', 'is_active' => false],
]);
$branchDraft = App\Models\Product::create(['title' => 'Branch draft visible', 'slug' => 'branch-draft-visible', 'supplier_id' => 2, 'category_id' => 1, 'status' => 'draft']);
$foreignDraft = App\Models\Product::create(['title' => 'Foreign draft hidden', 'slug' => 'foreign-draft-hidden', 'supplier_id' => 3, 'category_id' => 1, 'status' => 'draft']);
$inactiveDraft = App\Models\Product::create(['title' => 'Inactive draft visible', 'slug' => 'inactive-draft-visible', 'supplier_id' => 4, 'category_id' => 1, 'status' => 'draft']);
$selection = Livewire\Livewire::test(App\Livewire\Supplier\ProductSelection::class);
verify(str_contains($selection->html(), 'Branch draft visible'), 'Branch draft missing from selection');
verify(str_contains($selection->html(), 'Inactive draft visible'), 'Inactive branch draft missing from review');
verify(!str_contains($selection->html(), 'Foreign draft hidden'), 'Foreign draft leaked');
$selection->set('draftBranchId', '2');
verify(!str_contains($selection->html(), 'Inactive draft visible'), 'Draft branch filter failed');
$selection->call('openDraftProduct', $branchDraft->id, 3);
verify(session('supplier.active_branch_id') === 2, 'Draft did not select its owning branch');
verify(str_contains($selection->effects['redirect'] ?? '', 'step=3'), 'Draft did not link to embedded pricing');
foreach ([$foreignDraft, $inactiveDraft] as $deniedDraft) {
    $denied = false;
    try { (new App\Livewire\Supplier\ProductSelection())->openDraftProduct($deniedDraft->id); }
    catch (Illuminate\Database\Eloquent\ModelNotFoundException|Symfony\Component\HttpKernel\Exception\HttpException $exception) { $denied = true; }
    verify($denied, 'Foreign or inactive draft could be opened');
    verify(session('supplier.active_branch_id') === 2, 'Denied draft changed active branch');
}
echo "Branch draft selection, filtering, owner switch and access isolation passed\n";
$completed = true;
