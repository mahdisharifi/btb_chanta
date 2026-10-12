<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ProductCompletionSubmissionStatus;
use App\Enums\ProductCompletionTaskStatus;
use App\Enums\ProductCompletionTaskType;
use App\Enums\UserRole;
use App\Exceptions\ProductCompletionConflictException;
use App\Livewire\Outsourcer\Task\Workspace;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Product;
use App\Models\ProductCompletionTask;
use App\Models\User;
use App\Services\ProductCompletionApprovalService;
use App\Services\ProductCompletionConflictResolutionService;
use App\Services\ProductCompletionFinalizationService;
use App\Services\ProductCompletionMediaService;
use App\Services\ProductCompletionSnapshotService;
use App\Services\ProductCompletionSubmissionService;
use App\Services\ProductImageService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class OutsourcingWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function createApplication()
    {
        $app = parent::createApplication();
        if (! $app->environment('testing') || $app['config']->get('database.connections.mysql.database') !== 'b2b_marketplace_test') {
            throw new \RuntimeException('Workspace tests require the isolated test database.');
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

    public function test_super_admin_publication_updates_the_page_and_ignores_duplicate_requests(): void
    {
        [$task] = $this->task(['short_description' => true], ['short_description' => 'Ready.']);
        $task->update(['status' => ProductCompletionTaskStatus::DRAFT, 'published_at' => null, 'assigned_to' => null]);
        $admin = User::factory()->create()->assignRole(UserRole::SUPER_ADMIN->value);
        $component = Livewire::actingAs($admin)->test(\App\Livewire\Admin\Outsourcing\TaskShow::class, ['task' => $task])
            ->assertSee('wire:submit="publish"', false)
            ->call('publish')->assertHasNoErrors()
            ->assertDontSee('wire:submit="publish"', false)
            ->assertSee('منتشر شده')->assertSee('تاریخ انتشار');
        $this->assertSame(ProductCompletionTaskStatus::AVAILABLE, $task->refresh()->status);
        $publishedAt = $task->published_at->toAtomString();
        $version = $task->lock_version;
        $component->call('publish')->assertHasNoErrors()->assertDontSee('wire:submit="publish"', false);
        $this->assertSame($publishedAt, $task->refresh()->published_at->toAtomString());
        $this->assertSame($version, $task->lock_version);
        $task->update(['status' => ProductCompletionTaskStatus::IN_PROGRESS]);
        $component->call('publish')->assertHasNoErrors();
        $this->assertSame(ProductCompletionTaskStatus::IN_PROGRESS, $task->refresh()->status);
    }

    public function test_publication_cannot_reopen_an_assigned_task_even_for_super_admin(): void
    {
        [$task, $actor] = $this->task(['short_description' => true], ['short_description' => 'Ready.']);
        $task->update(['status' => ProductCompletionTaskStatus::ASSIGNED, 'published_at' => now(), 'assignment_expires_at' => now()->addDay()]);
        $admin = User::factory()->create()->assignRole(UserRole::SUPER_ADMIN->value);
        try {
            app(\App\Services\ProductCompletionTaskService::class)->publish($admin, $task);
            $this->fail('An assigned task must not be published again.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('task', $exception->errors());
        }
        $this->assertSame(ProductCompletionTaskStatus::ASSIGNED, $task->refresh()->status);
        $this->assertSame($actor->id, $task->assigned_to);
        Livewire::actingAs($actor)->test(Workspace::class, ['task' => $task])
            ->call('accept')->assertHasNoErrors()
            ->call('start')->assertHasNoErrors();
        $this->assertSame(ProductCompletionTaskStatus::IN_PROGRESS, $task->refresh()->status);
    }

    public function test_assignment_brief_and_selected_attributes_preserve_proposed_values(): void
    {
        [$task, $actor] = $this->task(['attributes' => true, 'short_description' => true], ['attributes' => []]);
        $attribute = Attribute::factory()->create(['name' => 'جنس بدنه', 'is_multiple' => true]);
        DB::table('attribute_category')->insert(['attribute_id' => $attribute->id, 'category_id' => $task->product->category_id, 'is_required' => false]);
        $value = AttributeValue::query()->create(['attribute_id' => $attribute->id, 'value' => 'فلزی']);
        $task->update(['status' => ProductCompletionTaskStatus::ASSIGNED, 'assignment_expires_at' => now()->addDay()]);
        $component = Livewire::actingAs($actor)->test(Workspace::class, ['task' => $task])
            ->assertSee($task->product->title)->assertSee('چه بخش‌هایی باید تکمیل شوند؟')
            ->assertSee('مهلت پذیرش')->assertSee('جنس بدنه')
            ->call('accept')->call('start')->assertHasNoErrors()
            ->call('toggleAttribute', $attribute->id, $value->id, true)
            ->set('newAttributeValues.'.$attribute->id, 'پیشنهاد تازه')
            ->call('addProposedAttributeValue', $attribute->id, true)
            ->assertSee('انتخاب‌های شما:')->assertSee('جستجو یا پیشنهاد مقدار جدید برای جنس بدنه')
            ->assertDispatched('attribute-value-proposed')
            ->call('removeAttributeValue', $attribute->id, $value->id)
            ->call('saveDraft')->assertHasNoErrors();
        $this->assertSame([], $task->refresh()->current_draft_json['attributes'][0]['value_ids']);
        $this->assertSame(['پیشنهاد تازه'], $task->current_draft_json['attributes'][0]['proposed_values']);
        $this->assertFalse(AttributeValue::query()->where('attribute_id', $attribute->id)->where('value', 'پیشنهاد تازه')->exists());
    }

    public function test_workspace_rejects_existing_and_repeated_proposals_without_changing_selection(): void
    {
        [$task, $actor] = $this->task(['attributes' => true], ['attributes' => []]);
        $attribute = Attribute::factory()->create(['is_multiple' => false]);
        DB::table('attribute_category')->insert(['attribute_id' => $attribute->id, 'category_id' => $task->product->category_id, 'is_required' => false]);
        $existing = AttributeValue::query()->create(['attribute_id' => $attribute->id, 'value' => 'کیف چرمی']);
        $component = Livewire::actingAs($actor)->test(Workspace::class, ['task' => $task])
            ->call('toggleAttribute', $attribute->id, $existing->id, false);
        foreach (['کیف چرمی', '  كيف   چرمي  '] as $duplicate) {
            $component->set('newAttributeValues.'.$attribute->id, $duplicate)
                ->call('addProposedAttributeValue', $attribute->id, false)
                ->assertHasErrors('newAttributeValues.'.$attribute->id)
                ->assertSee('این مقدار از قبل وجود دارد');
            $this->assertSame([$existing->id], $component->get('draft.attributes')[0]['value_ids']);
            $this->assertSame([], $component->get('draft.attributes')[0]['proposed_values']);
        }
        $component->set('newAttributeValues.'.$attribute->id, 'کیف پارچه‌ای')
            ->call('addProposedAttributeValue', $attribute->id, false)->assertHasNoErrors()
            ->set('newAttributeValues.'.$attribute->id, 'كيف پارچه اي')
            ->call('addProposedAttributeValue', $attribute->id, false)
            ->assertHasErrors('newAttributeValues.'.$attribute->id);
        $this->assertSame(['کیف پارچه‌ای'], $component->get('draft.attributes')[0]['proposed_values']);
        $this->assertSame(1, AttributeValue::where('attribute_id', $attribute->id)->count());
    }

    public function test_admin_product_edit_rejects_existing_values_and_still_adds_fresh_values(): void
    {
        [$task] = $this->task(['attributes' => true], ['attributes' => []]);
        $attribute = Attribute::factory()->create(['is_multiple' => false]);
        DB::table('attribute_category')->insert(['attribute_id' => $attribute->id, 'category_id' => $task->product->category_id, 'is_required' => false]);
        AttributeValue::query()->create(['attribute_id' => $attribute->id, 'value' => 'کیف چرمی']);
        $admin = User::factory()->create()->assignRole(UserRole::SUPER_ADMIN->value);
        $component = Livewire::actingAs($admin)->test(\App\Livewire\Admin\Products\Create::class, ['product' => $task->product]);
        $selection = $component->get('selectedAttributeValues');
        foreach (['کیف چرمی', '  كيف   چرمي  '] as $duplicate) {
            $component->set('newAttributeValues.'.$attribute->id, $duplicate)
                ->call('saveNewAttributeValue', $attribute->id)
                ->assertHasErrors('newAttributeValues.'.$attribute->id)
                ->assertSet('newAttributeValues.'.$attribute->id, $duplicate)
                ->assertSee('این مقدار از قبل وجود دارد');
            $this->assertSame($selection, $component->get('selectedAttributeValues'));
            $this->assertSame(1, AttributeValue::where('attribute_id', $attribute->id)->count());
        }
        $component->set('newAttributeValues.'.$attribute->id, 'کیف پارچه‌ای')
            ->call('saveNewAttributeValue', $attribute->id)->assertHasNoErrors()
            ->assertDispatched('attribute-value-added')
            ->assertSet('newAttributeValues.'.$attribute->id, '');
        $newValue = AttributeValue::where('attribute_id', $attribute->id)->where('value', 'کیف پارچه‌ای')->firstOrFail();
        $component->assertSet('selectedAttributeValues.'.$attribute->id, (string) $newValue->id);
    }

    public function test_draft_can_be_incomplete_and_submission_displays_the_missing_field(): void
    {
        [$task, $actor] = $this->task(['short_description' => true], ['short_description' => null]);
        Livewire::actingAs($actor)->test(Workspace::class, ['task' => $task])
            ->call('saveDraft')->assertHasNoErrors()
            ->call('submit')->assertHasErrors('short_description')
            ->assertSee('توضیحات کوتاه الزامی است.');
        $this->assertSame(ProductCompletionTaskStatus::IN_PROGRESS, $task->refresh()->status);
        $this->assertSame(0, $task->submissions()->count());
    }

    public function test_submit_saves_the_current_form_without_a_separate_draft_click(): void
    {
        [$task, $actor] = $this->task(['short_description' => true], ['short_description' => null]);
        Livewire::actingAs($actor)->test(Workspace::class, ['task' => $task])
            ->set('draft.short_description', 'New form description.')
            ->call('submit')->assertHasNoErrors();
        $this->assertSame(ProductCompletionTaskStatus::SUBMITTED, $task->refresh()->status);
        $this->assertSame('New form description.', $task->lastSubmission->payload_json['short_description']);
    }

    public function test_legacy_seo_and_specifications_are_hidden_ignored_and_not_saved(): void
    {
        [$task, $actor] = $this->task(['short_description' => true], ['short_description' => 'Ready.']);
        $legacy = ['schema_version' => 1, 'short_description' => 'Ready.', 'seo' => ['title' => 'Legacy SEO'], 'specifications' => ['Technical' => 'Old']];
        DB::table('product_completion_tasks')->where('id', $task->id)->update([
            'scope_json' => json_encode(['short_description' => true, 'specifications' => true, 'seo' => ['title' => true]]),
            'current_draft_json' => json_encode($legacy),
        ]);
        $task->refresh();
        $this->assertArrayNotHasKey('seo', $task->current_draft_json);
        $this->assertArrayNotHasKey('specifications', $task->scope_json);
        Livewire::actingAs($actor)->test(Workspace::class, ['task' => $task])
            ->assertDontSee('مشخصات فنی')->assertDontSee('selectSeo')
            ->set('draft', $legacy)->call('submit')->assertHasNoErrors();
        $raw = json_decode(DB::table('product_completion_tasks')->where('id', $task->id)->value('current_draft_json'), true);
        $this->assertArrayNotHasKey('seo', $raw);
        $this->assertArrayNotHasKey('specifications', $raw);
        $this->assertArrayNotHasKey('seo', $task->refresh()->lastSubmission->payload_json);
        $this->assertArrayNotHasKey('seo', ProductCompletionTaskType::options());
    }

    public function test_existing_images_are_rendered_and_preview_is_authorized_for_the_product(): void
    {
        [$task, $actor] = $this->task(['gallery' => $this->galleryScope()], ['gallery' => ['items' => []]]);
        $gallery = $task->product->galleries()->create([
            'file_path' => 'products/existing.png', 'file_name' => 'existing.png', 'file_type' => 'image', 'sort_order' => 1, 'is_primary' => true,
        ]);
        $image = UploadedFile::fake()->image('existing.png', 800, 800);
        Storage::disk('public')->put($gallery->file_path, file_get_contents($image->getRealPath()));
        $this->snapshot($task);
        $task->update(['current_draft_json' => app(ProductCompletionSnapshotService::class)->draftFromSnapshot($task->initial_snapshot_json)]);
        $url = route('outsourcing.gallery.preview', [$task, $gallery]);
        Livewire::actingAs($actor)->test(Workspace::class, ['task' => $task])->assertSee($url, false);
        $this->actingAs($actor)->get($url)->assertOk()->assertHeader('Content-Type', 'image/png');
        $other = Product::factory()->create()->galleries()->create(['file_path' => 'other.png', 'file_type' => 'image']);
        $this->get(route('outsourcing.gallery.preview', [$task, $other]))->assertNotFound();
        $stranger = User::factory()->create()->assignRole(UserRole::OUTSOURCER->value);
        $this->actingAs($stranger)->get($url)->assertForbidden();
    }

    public function test_media_only_task_stages_and_submits_an_image_without_content_fields(): void
    {
        [$task, $actor] = $this->task(['gallery' => $this->galleryScope()], ['gallery' => ['items' => []]]);
        Livewire::actingAs($actor)->test(Workspace::class, ['task' => $task])
            ->set('upload', UploadedFile::fake()->image('new.png', 800, 800))
            ->call('uploadMedia')->assertHasNoErrors()
            ->call('submit')->assertHasNoErrors();
        $media = $task->media()->firstOrFail();
        Storage::disk($media->disk)->assertExists($media->temporary_path);
        $this->assertSame('under_review', $media->status->value);
        $this->assertSame(0, $task->product->galleries()->count());
        $payload = $task->refresh()->lastSubmission->payload_json;
        $this->assertArrayNotHasKey('is_seo_image', $payload['gallery']['items'][0]);
        $this->assertArrayNotHasKey('specifications', $payload);
    }

    public function test_upload_with_a_stale_draft_rolls_back_record_and_file(): void
    {
        [$task, $actor] = $this->task(['gallery' => $this->galleryScope()], ['gallery' => ['items' => []]]);
        $component = Livewire::actingAs($actor)->test(Workspace::class, ['task' => $task]);
        $task->update(['draft_version' => 1]);
        $component->set('upload', UploadedFile::fake()->image('new.png', 800, 800))
            ->call('uploadMedia')->assertHasErrors('draft_version');
        $this->assertSame(0, $task->media()->count());
        $this->assertSame([], Storage::disk('local')->allFiles('product-completion'));
        $this->assertSame([], $task->refresh()->current_draft_json['gallery']['items']);
    }

    public function test_proposed_single_value_replaces_existing_selection_and_survives_image_upload(): void
    {
        [$task, $actor] = $this->task(['attributes' => true, 'gallery' => $this->galleryScope()], ['attributes' => [], 'gallery' => ['items' => []]]);
        DB::table('attribute_category')->where('category_id', $task->product->category_id)->delete();
        $attribute = Attribute::factory()->create(['name' => 'Carton count', 'is_multiple' => false]);
        DB::table('attribute_category')->insert(['attribute_id' => $attribute->id, 'category_id' => $task->product->category_id, 'is_required' => false]);
        $existing = AttributeValue::query()->create(['attribute_id' => $attribute->id, 'value' => 'pcs:100']);
        $component = Livewire::actingAs($actor)->test(Workspace::class, ['task' => $task]);
        $component->call('toggleAttribute', $attribute->id, $existing->id, false)
            ->call('saveDraft')->assertHasNoErrors()
            ->set('newAttributeValues.'.$attribute->id, 'pcs:41')
            ->call('addProposedAttributeValue', $attribute->id, false)
            ->call('saveDraft')->assertHasNoErrors()
            ->set('upload', UploadedFile::fake()->image('new.png', 800, 800))
            ->call('uploadMedia')->assertHasNoErrors();

        $expected = [['attribute_id' => $attribute->id, 'value_ids' => [], 'proposed_values' => ['pcs:41']]];
        $this->assertSame($expected, $task->refresh()->current_draft_json['attributes']);
        Livewire::actingAs($actor)->test(Workspace::class, ['task' => $task])->assertSet('draft.attributes', $expected);
        $this->assertCount(1, $task->current_draft_json['gallery']['items']);
    }

    public function test_reviewer_sees_named_values_and_image_previews_for_a_task_without_a_deadline(): void
    {
        [$task, $actor] = $this->task(['attributes' => true, 'short_description' => true, 'gallery' => $this->galleryScope()], [
            'attributes' => [], 'short_description' => null, 'gallery' => ['items' => []],
        ]);
        DB::table('attribute_category')->where('category_id', $task->product->category_id)->delete();
        $attribute = Attribute::factory()->create(['name' => 'Carton count', 'is_multiple' => false]);
        DB::table('attribute_category')->insert(['attribute_id' => $attribute->id, 'category_id' => $task->product->category_id, 'is_required' => false]);
        $existingValue = AttributeValue::query()->create(['attribute_id' => $attribute->id, 'value' => 'pcs:100']);
        $task->product->attributeValues()->sync([$existingValue->id]);
        $gallery = $task->product->galleries()->create([
            'file_path' => 'products/old.png', 'file_type' => 'image', 'sort_order' => 1, 'is_primary' => true,
        ]);
        $oldImage = UploadedFile::fake()->image('old.png', 800, 800);
        Storage::disk('public')->put($gallery->file_path, file_get_contents($oldImage->getRealPath()));
        $this->snapshot($task);
        $reviewer = User::factory()->create()->assignRole(UserRole::REVIEWER->value);
        $task->update([
            'reviewer_id' => $reviewer->id, 'due_at' => null,
            'current_draft_json' => app(ProductCompletionSnapshotService::class)->draftFromSnapshot($task->initial_snapshot_json),
        ]);
        Livewire::actingAs($actor)->test(Workspace::class, ['task' => $task])
            ->set('newAttributeValues.'.$attribute->id, 'pcs:41')
            ->call('addProposedAttributeValue', $attribute->id, false)
            ->set('draft.short_description', '<p>Readable description.</p><img src=x onerror=alert(1)>')
            ->set('upload', UploadedFile::fake()->image('new.png', 800, 800))
            ->call('uploadMedia')->assertHasNoErrors()
            ->call('submit')->assertHasNoErrors();

        $task->refresh();
        $media = $task->media()->sole();
        Livewire::actingAs($reviewer)->test(\App\Livewire\Reviewer\Task\Review::class, ['task' => $task])
            ->assertSee('Carton count')->assertSee('pcs:100')->assertSee('pcs:41')
            ->assertSee('Readable description.')->assertDontSee('onerror=alert(1)', false)
            ->assertSee(route('outsourcing.gallery.preview', [$task, $gallery]), false)
            ->assertSee(route('outsourcing.media.preview', $media), false)
            ->assertDontSee('attribute_id')->assertDontSee('value_ids')->assertDontSee('media_public_id')
            ->assertSeeHtml('value="attributes"');
        $this->actingAs($reviewer)->get(route('outsourcing.media.preview', $media))->assertOk();
        $this->get(route('outsourcing.gallery.preview', [$task, $gallery]))->assertOk();
        $stranger = User::factory()->create()->assignRole(UserRole::REVIEWER->value);
        $this->actingAs($stranger)->get(route('outsourcing.media.preview', $media))->assertForbidden();

        app(\App\Services\ProductCompletionRevisionService::class)->request(
            $reviewer, $task->lastSubmission, ['short_description'], 'Provide a revised introduction.',
            \App\Enums\ProductCompletionRevisionClassification::CONTRACTOR_ERROR,
        );
        Livewire::actingAs($actor)->test(Workspace::class, ['task' => $task->refresh()])
            ->call('resumeRevision')->assertHasNoErrors()
            ->set('draft.short_description', 'Revised introduction.')
            ->call('submit')->assertHasNoErrors();
        Livewire::actingAs($reviewer)->test(\App\Livewire\Reviewer\Task\Review::class, ['task' => $task->refresh()])
            ->assertSee('Revised introduction.')->assertSee('Carton count')->assertSee('pcs:41')
            ->assertSee(route('outsourcing.media.preview', $media), false)
            ->assertSee('مشاهده کل محتوای ارسالی، شامل بخش‌های بدون تغییر');
        $this->assertSame(['short_description'], array_keys($task->lastSubmission->diff_json));
    }

    public function test_proposed_required_value_stays_private_until_approved_content_is_applied(): void
    {
        [$task, $actor] = $this->task(['attributes' => true], ['attributes' => []]);
        DB::table('attribute_category')->where('category_id', $task->product->category_id)->delete();
        $attribute = Attribute::factory()->create(['name' => 'Charging connector', 'is_multiple' => false]);
        DB::table('attribute_category')->insert(['attribute_id' => $attribute->id, 'category_id' => $task->product->category_id, 'is_required' => true]);
        $reviewer = User::factory()->create()->assignRole(UserRole::REVIEWER->value);
        $task->update(['reviewer_id' => $reviewer->id]);
        $component = Livewire::actingAs($actor)->test(Workspace::class, ['task' => $task]);
        $component->call('submit')->assertHasErrors('attributes.required')->assertSee('Charging connector');
        $component->set('newAttributeValues.'.$attribute->id, 'USB-C proposed')
            ->call('addProposedAttributeValue', $attribute->id, false)
            ->call('submit')->assertHasNoErrors();
        $this->assertSame(0, AttributeValue::where('attribute_id', $attribute->id)->where('value', 'USB-C proposed')->count());
        $task->refresh();
        app(ProductCompletionApprovalService::class)->approve($reviewer, $task->lastSubmission);
        app(ProductCompletionApprovalService::class)->applyApprovedContent($task->refresh());
        app(ProductCompletionApprovalService::class)->applyApprovedContent($task->refresh());
        app(ProductCompletionMediaService::class)->publishApprovedMedia($task->refresh());
        $this->assertNotNull($task->refresh()->media_published_at);
        $value = AttributeValue::where('attribute_id', $attribute->id)->where('value', 'USB-C proposed')->sole();
        $this->assertTrue($task->product->attributeValues()->where('attribute_values.id', $value->id)->exists());
    }

    public function test_contractor_sees_revision_instructions_until_resubmission(): void
    {
        [$task, $actor] = $this->task(['short_description' => true], ['short_description' => 'Initial description.']);
        $reviewer = User::factory()->create()->assignRole(UserRole::REVIEWER->value);
        $task->update(['reviewer_id' => $reviewer->id, 'due_at' => null]);
        $submission = app(ProductCompletionSubmissionService::class)->submit($actor, $task, 0, 'revision-instructions');
        app(\App\Services\ProductCompletionRevisionService::class)->request(
            $reviewer, $submission, ['short_description'], 'Replace the short description with a product introduction.',
            \App\Enums\ProductCompletionRevisionClassification::CONTRACTOR_ERROR,
        );
        $component = Livewire::actingAs($actor)->test(Workspace::class, ['task' => $task->refresh()]);
        $component->assertSee('Replace the short description with a product introduction.')
            ->assertSee('درخواست اصلاح بازبین')->assertSee('توضیحات کوتاه')
            ->call('resumeRevision')->assertHasNoErrors()
            ->assertSee('Replace the short description with a product introduction.')
            ->set('draft.short_description', 'Revised product introduction.')
            ->call('submit')->assertHasNoErrors()
            ->assertDontSee('Replace the short description with a product introduction.');
        $this->assertSame(ProductCompletionTaskStatus::RESUBMITTED, $task->refresh()->status);
        $this->assertSame('Revised product introduction.', $task->lastSubmission->payload_json['short_description']);
        $this->assertNull($task->revisions()->sole()->open_task_id);
    }

    public function test_returning_a_real_conflict_allows_reapproval_with_a_new_baseline(): void
    {
        [$task, $actor] = $this->task(['short_description' => true], ['short_description' => 'Contractor edit.']);
        $task->product->update(['short_description' => 'Original.']);
        $this->snapshot($task);
        $reviewer = User::factory()->create()->assignRole(UserRole::REVIEWER->value);
        $task->update(['reviewer_id' => $reviewer->id]);
        app(ProductCompletionSubmissionService::class)->submit($actor, $task, 0, 'first-conflict');
        $task->product->update(['short_description' => 'Concurrent edit.', 'content_version' => 2]);
        try {
            app(ProductCompletionApprovalService::class)->approve($reviewer, $task->refresh()->lastSubmission);
            $this->fail('Concurrent changes must be reviewed.');
        } catch (ProductCompletionConflictException $e) {
            $this->assertSame(ProductCompletionTaskStatus::CONFLICTED, $task->refresh()->status);
        }
        $admin = User::factory()->create()->assignRole(UserRole::ADMIN->value);
        app(ProductCompletionConflictResolutionService::class)->requestRevision($admin, $task, $task->lock_version, 'Review against the current product.');
        $this->assertSame('Concurrent edit.', $task->refresh()->initial_snapshot_json['short_description']);
        $this->assertSame('Original.', $task->commission_content_snapshot_json['short_description']);
        $this->assertSame('Contractor edit.', $task->current_draft_json['short_description']);
        Livewire::actingAs($actor)->test(Workspace::class, ['task' => $task])
            ->call('resumeRevision')->assertHasNoErrors()
            ->set('draft.short_description', 'Reviewed replacement.')->call('submit')->assertHasNoErrors();
        $this->assertSame(ProductCompletionTaskStatus::RESUBMITTED, $task->refresh()->status);
        app(ProductCompletionApprovalService::class)->approve($reviewer, $task->lastSubmission);
        app(ProductCompletionApprovalService::class)->applyApprovedContent($task->refresh());
        $this->assertSame('Reviewed replacement.', $task->product->refresh()->short_description);
        $this->assertSame(ProductCompletionSubmissionStatus::APPROVED, $task->lastSubmission->refresh()->status);
    }

    public function test_approval_prices_new_content_at_full_rate_even_in_a_correction_task_after_resubmission(): void
    {
        [$task, $actor] = $this->task(['short_description' => true], ['short_description' => 'First new description.'], [
            'commission_rule_snapshot_json' => ['pricing_schema_version' => 2, 'revision_policy' => 'none', 'max_amount' => null,
                'items' => [['content_type' => 'description', 'unit_amount' => '10000.00', 'max_payable_units' => 1, 'is_enabled' => true]],
            ],
        ]);
        $task->product->update(['short_description' => null]);
        $this->snapshot($task);
        $reviewer = User::factory()->create()->assignRole(UserRole::REVIEWER->value);
        $task->update([
            'task_type' => ProductCompletionTaskType::CORRECTION, 'reviewer_id' => $reviewer->id,
            'due_at' => null, 'commission_rate_percentage' => '30.00',
        ]);
        $first = app(ProductCompletionSubmissionService::class)->submit($actor, $task, 0, 'new-content-origin');
        app(\App\Services\ProductCompletionRevisionService::class)->request(
            $reviewer, $first, ['short_description'], 'Improve the new description.',
            \App\Enums\ProductCompletionRevisionClassification::CONTRACTOR_ERROR,
        );
        Livewire::actingAs($actor)->test(Workspace::class, ['task' => $task->refresh()])
            ->call('resumeRevision')->set('draft.short_description', 'Improved new description.')
            ->call('submit')->assertHasNoErrors();
        $task->refresh();
        Livewire::actingAs($reviewer)->test(\App\Livewire\Reviewer\Task\Review::class, ['task' => $task])
            ->assertSee('مبنای خودکار پورسانت')->assertSee('اطلاعات تازه')
            ->assertDontSee('استثنا: نرخ کامل برای مقادیر اصلاح‌شده')
            ->call('approve')->assertHasNoErrors();
        $this->assertSame(ProductCompletionTaskStatus::COMPLETED, $task->refresh()->status);
        $this->assertSame('100.00', $task->commission_rate_percentage);
        $this->assertNull($task->full_rate_approved_by);
        $this->assertNull($task->commission_content_snapshot_json['short_description']);
        $calculated = app(\App\Services\ProductCompletionCommissionCalculator::class)->calculate($task, $task->lastSubmission);
        $this->assertSame('10000.00', $calculated['base_amount']);
        $this->assertSame('10000.00', $task->commission->total_amount);
    }

    public function test_new_images_publish_once_after_approval_and_keep_existing_images(): void
    {
        [$task, $actor] = $this->task(['gallery' => $this->galleryScope()], ['gallery' => ['items' => []]]);
        $existing = $task->product->galleries()->create([
            'file_path' => 'products/existing.png', 'file_name' => 'existing.png', 'file_type' => 'image',
            'sort_order' => 1, 'is_primary' => true, 'is_seo_image' => true,
        ]);
        $this->snapshot($task);
        $task->update(['current_draft_json' => app(ProductCompletionSnapshotService::class)->draftFromSnapshot($task->initial_snapshot_json)]);
        $reviewer = User::factory()->create()->assignRole(UserRole::REVIEWER->value);
        $task->update(['reviewer_id' => $reviewer->id]);
        Livewire::actingAs($actor)->test(Workspace::class, ['task' => $task])
            ->set('upload', UploadedFile::fake()->image('new.png', 800, 800))
            ->call('uploadMedia')->assertHasNoErrors()->call('submit')->assertHasNoErrors();
        $media = $task->media()->sole();
        // Image encoding is replaced here; file staging, manifest, database publication and retries are real.
        $this->mock(ProductImageService::class, function ($mock) use ($media): void {
            $mock->shouldReceive('process')->once()->withArgs(function ($source, $slug, $index, $pdf, $base) use ($media): bool {
                return is_file($source) && $index === $media->id && $pdf === true && $base === pathinfo($media->safe_name, PATHINFO_FILENAME);
            })->andReturnUsing(function () use ($media): array {
                $path = 'products/gallery/'.pathinfo($media->safe_name, PATHINFO_FILENAME).'.webp';
                Storage::disk('public')->put($path, 'processed image fixture');

                return ['file_path' => $path, 'file_size' => 100, 'width' => 800, 'height' => 800, 'versions' => ['full' => ['path' => $path]]];
            });
        });
        app(ProductCompletionApprovalService::class)->approve($reviewer, $task->refresh()->lastSubmission);
        app(ProductCompletionFinalizationService::class)->finalize($task->refresh());
        app(ProductCompletionFinalizationService::class)->finalize($task->refresh());
        $this->assertSame('published', $media->refresh()->status->value);
        Storage::disk('public')->assertExists($media->final_path);
        $this->assertSame(2, $task->product->galleries()->count());
        $this->assertTrue((bool) $existing->refresh()->is_seo_image);
        $this->assertSame(1, $task->commission()->count());
    }

    public function test_a_real_concurrent_attribute_change_remains_a_conflict(): void
    {
        [$task] = $this->task(['attributes' => true], ['attributes' => []]);
        $attribute = Attribute::factory()->create();
        $before = AttributeValue::query()->create(['attribute_id' => $attribute->id, 'value' => 'before']);
        $current = AttributeValue::query()->create(['attribute_id' => $attribute->id, 'value' => 'current']);
        $submitted = AttributeValue::query()->create(['attribute_id' => $attribute->id, 'value' => 'submitted']);
        $task->product->attributeValues()->sync([$before->id]);
        $this->snapshot($task);
        $task->product->attributeValues()->sync([$current->id]);
        $conflict = app(\App\Services\ProductCompletionConflictService::class)->detect($task, $task->product, [
            'schema_version' => 1, 'attributes' => [['attribute_id' => $attribute->id, 'value_ids' => [$submitted->id], 'proposed_values' => []]],
        ]);
        $this->assertArrayHasKey('attributes', $conflict->conflicts);
    }

    public function test_admin_can_extend_expired_deadline_and_contractor_cannot(): void
    {
        [$task, $actor] = $this->task(['short_description' => true], ['short_description' => 'Ready.']);
        $task->update(['due_at' => now()->subHour()]);
        $this->assertFalse(\Illuminate\Support\Facades\Gate::forUser($actor)->allows('submitTask', $task));
        $admin = User::factory()->create()->assignRole(UserRole::ADMIN->value);
        $component = Livewire::actingAs($admin)->test(\App\Livewire\Admin\Outsourcing\TaskShow::class, ['task' => $task]);
        $component->set('newDueAt', jdate(now()->subDay())->format('Y/m/d H:i'))
            ->call('extendDeadline')->assertHasErrors('newDueAt');
        $component->set('newDueAt', jdate(now()->addDay())->format('Y/m/d H:i'))
            ->call('extendDeadline')->assertHasNoErrors();
        $this->assertTrue($task->refresh()->due_at->isFuture());
        $this->assertTrue(\Illuminate\Support\Facades\Gate::forUser($actor)->allows('submitTask', $task));
        Livewire::actingAs($actor)->test(\App\Livewire\Admin\Outsourcing\TaskShow::class, ['task' => $task])
            ->set('newDueAt', jdate(now()->addDays(2))->format('Y/m/d H:i'))
            ->call('extendDeadline')->assertForbidden();
    }

    public function test_deadline_accepts_persian_digits_and_rejects_invalid_jalali_dates(): void
    {
        config(['app.timezone' => 'Asia/Tehran']);
        $this->travelTo(\Carbon\Carbon::parse('2026-10-11 15:00:00', 'Asia/Tehran'));
        [$task] = $this->task(['short_description' => true], ['short_description' => 'Ready.']);
        $task->update(['due_at' => \Carbon\Carbon::parse('2026-10-12 18:00:00', 'Asia/Tehran')]);
        $admin = User::factory()->create()->assignRole(UserRole::ADMIN->value);
        $component = Livewire::actingAs($admin)->test(\App\Livewire\Admin\Outsourcing\TaskShow::class, ['task' => $task]);
        $component->assertSet('newDueAt', '1405/07/20 18:00');

        foreach (['1405/07/31 18:00', '1405/12/30 18:00', '1405/07/20 24:00'] as $invalidDate) {
            $component->set('newDueAt', $invalidDate)->call('extendDeadline')->assertHasErrors('newDueAt');
        }
        $this->assertSame('2026-10-12 18:00', $task->refresh()->due_at->timezone('Asia/Tehran')->format('Y-m-d H:i'));

        $component->set('newDueAt', '۱۴۰۵/۰۷/۲۱ ۱۸:۳۰')->call('extendDeadline')->assertHasNoErrors();
        $this->assertSame('2026-10-13 18:30:00', $task->refresh()->due_at->timezone('Asia/Tehran')->format('Y-m-d H:i:s'));
        $this->travelBack();
    }

    private function task(array $scope, array $draft, array $taskOverrides = []): array
    {
        $actor = User::factory()->create()->assignRole(UserRole::OUTSOURCER->value);
        $task = ProductCompletionTask::factory()->inProgress($actor)->create([
            'scope_json' => $scope, 'current_draft_json' => ['schema_version' => 1, ...$draft],
            ...$taskOverrides,
        ]);
        $this->snapshot($task);

        return [$task, $actor];
    }

    private function snapshot(ProductCompletionTask $task): void
    {
        $task->update([
            'initial_snapshot_json' => app(ProductCompletionSnapshotService::class)->build($task->product->refresh(), $task->scope_json),
            'product_version_at_assignment' => $task->product->content_version,
        ]);
    }

    private function galleryScope(): array
    {
        return ['add' => true, 'reorder' => true, 'select_primary' => true, 'propose_removal' => true];
    }
}
