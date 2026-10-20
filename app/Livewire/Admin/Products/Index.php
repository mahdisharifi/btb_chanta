<?php

namespace App\Livewire\Admin\Products;

use App\Models\Product;
use App\Models\Category;
use App\Enums\ProductStatus;
use App\Enums\ProductCompletionTaskStatus;
use App\Enums\ProductCompletionTaskType;
use App\Models\CommissionRule;
use App\Models\ProductCompletionTask;
use App\Models\ProductCompletionTaskBatch;
use App\Services\ProductCompletionTaskService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

#[Layout('components.layouts.admin')]
class Index extends Component
{
    use WithPagination;

    private const BULK_UNDO_CHUNK_SIZE = 50;

    public $completionStatus = '';
    public array $completionCounts = [];
    public array $supplierCounts = [];
    public $taskStatus = '';
    public $taskType = '';
    public $pricingStatus = '';
    public $primaryImageStatus = '';
    public $galleryImageCounts = [];
    public $shortDescriptionStatus = '';
    public $fullDescriptionStatus = '';
    public string $bulkTaskUndoResult = '';

    public $search = '';
    public $category_id = '';
    public $status = '';
    public $sort = 'latest';
    public array $selectedProducts = [];
    public bool $selectAll = false;
    public int $perPage = 15;

    public function updatedSearch() { $this->resetFiltersAndSelection(); }
    public function updatedCategoryId() { $this->resetFiltersAndSelection(); }
    public function updatedStatus() { $this->resetFiltersAndSelection(); }
    public function updatedSort() { $this->resetFiltersAndSelection(); }
    public function updatedCompletionStatus() { $this->resetFiltersAndSelection(); }
    public function updatedCompletionCounts() { $this->resetFiltersAndSelection(); }
    public function updatedSupplierCounts() { $this->resetFiltersAndSelection(); }
    public function updatedTaskStatus() { $this->resetFiltersAndSelection(); }
    public function updatedTaskType() { $this->resetFiltersAndSelection(); }
    public function updatedPricingStatus() { $this->resetFiltersAndSelection(); }
    public function updatedPrimaryImageStatus() { $this->resetFiltersAndSelection(); }
    public function updatedGalleryImageCounts() { $this->resetFiltersAndSelection(); }
    public function updatedShortDescriptionStatus() { $this->resetFiltersAndSelection(); }
    public function updatedFullDescriptionStatus() { $this->resetFiltersAndSelection(); }

    public function updatedPerPage(): void
    {
        $this->perPage = $this->safePerPage();
        $this->resetFiltersAndSelection();
    }

    public function updatedSelectAll(bool $selected): void
    {
        if (! $selected) {
            $this->selectedProducts = [];
            return;
        }

        $this->selectedProducts = $this->getFilteredQuery()
            ->paginate($this->safePerPage())
            ->getCollection()
            ->pluck('id')
            ->map(static fn ($id): string => (string) $id)
            ->all();
    }

    public function updatedSelectedProducts(): void
    {
        $this->selectAll = false;
    }

    private function resetFiltersAndSelection(): void
    {
        $this->resetPage();
        $this->selectedProducts = [];
        $this->selectAll = false;
    }

    private function safePerPage(): int
    {
        return in_array((int) $this->perPage, [15, 30, 50, 100], true)
            ? (int) $this->perPage
            : 15;
    }

    public function toggleActive($id)
    {
        $product = Product::findOrFail($id);
        $product->update(['is_active' => !$product->is_active]);
        $this->dispatch('toast', message: 'وضعیت نمایش محصول با موفقیت تغییر کرد.', type: 'success');
    }

    public function delete($id)
    {
        $product = Product::findOrFail($id);
        $product->galleries()->delete();
        $product->attributeValues()->detach();
        $product->delete();
        $this->dispatch('toast', message: 'محصول با موفقیت حذف شد.', type: 'success');
    }

    public function createDefaultTasksForSelected(ProductCompletionTaskService $service): void
    {
        Gate::authorize('create', ProductCompletionTask::class);
        Gate::authorize('outsourcing.publish_task');

        if ($this->hasPendingBulkTaskRollback()) {
            $this->dispatch('notify', message: 'ابتدا بازگردانی گروهیِ نیمه‌تمام را ادامه دهید و کامل کنید.', type: 'warning');
            return;
        }

        $selectedIds = collect($this->selectedProducts)
            ->map(static fn ($id) => filter_var($id, FILTER_VALIDATE_INT))
            ->filter(static fn ($id) => $id !== false && $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($selectedIds === []) {
            $this->dispatch('notify', message: 'ابتدا حداقل یک محصول انتخاب کنید.', type: 'warning');
            return;
        }

        $query = $this->getFilteredQuery()
            ->whereIn('products.id', $selectedIds)
            ->whereDoesntHave('completionTasks', fn ($task) => $task->whereNotNull('active_product_id'));

        $this->createDefaultTasksFromQuery($query, count($selectedIds), $service);
    }

    public function createDefaultTasksForFilteredProducts(ProductCompletionTaskService $service): void
    {
        Gate::authorize('create', ProductCompletionTask::class);
        Gate::authorize('outsourcing.publish_task');

        if ($this->hasPendingBulkTaskRollback()) {
            $this->dispatch('notify', message: 'ابتدا بازگردانی گروهیِ نیمه‌تمام را ادامه دهید و کامل کنید.', type: 'warning');
            return;
        }

        $query = $this->getFilteredQuery()->whereDoesntHave('completionTasks', fn ($task) => $task->whereNotNull('active_product_id'));
        $this->createDefaultTasksFromQuery($query, null, $service);
    }

    private function createDefaultTasksFromQuery($query, ?int $requestedCount, ProductCompletionTaskService $service): void
    {
        $this->bulkTaskUndoResult = '';
        $taskType = ProductCompletionTaskType::FULL_COMPLETION;
        $now = now();
        $defaultRule = CommissionRule::query()
            ->where('task_type', $taskType->value)
            ->where('is_default', true)
            ->where('is_active', true)
            ->where(fn ($rules) => $rules->whereNull('effective_from')->orWhere('effective_from', '<=', $now))
            ->where(fn ($rules) => $rules->whereNull('effective_until')->orWhere('effective_until', '>=', $now))
            ->first();

        if ($defaultRule === null) {
            $this->dispatch('notify', message: 'برای نوع «تکمیل کامل» قانون پورسانت پیش‌فرض فعال و معتبر تعیین نشده است.', type: 'error');
            return;
        }

        set_time_limit(300);
        $batch = ProductCompletionTaskBatch::query()->create([
            'created_by' => auth()->id(),
            'status' => 'running',
        ]);
        $created = 0;
        $failed = 0;
        $eligible = 0;
        $actor = auth()->user();

        $query->reorder()->select('products.*')->chunkById(100, function ($products) use (
            $service,
            $actor,
            $defaultRule,
            $taskType,
            &$created,
            &$failed,
            &$eligible,
            $batch,
        ) {
            foreach ($products as $product) {
                try {
                    $started = \DB::transaction(function () use ($service, $actor, $product, $defaultRule, $taskType, $batch): bool {
                        $lockedBatch = ProductCompletionTaskBatch::query()->lockForUpdate()->findOrFail($batch->getKey());
                        if ($lockedBatch->status !== 'running') {
                            return false;
                        }

                        $task = $service->create(
                            actor: $actor,
                            product: $product,
                            commissionRule: $defaultRule,
                            taskType: $taskType,
                            completionDays: (int) config('product_completion.defaults.due_days', 3),
                            publishImmediately: true,
                        );
                        $task->forceFill(['bulk_creation_batch_id' => $batch->getKey()])->save();
                        return true;
                    }, 3);
                    if (! $started) {
                        return false;
                    }
                    $eligible++;
                    $created++;
                } catch (ValidationException $exception) {
                    $eligible++;
                    $failed++;
                } catch (\Throwable $exception) {
                    report($exception);
                    $eligible++;
                    $failed++;
                }
            }
        }, 'products.id', 'id');

        \DB::transaction(function () use ($batch, $eligible, $created, $failed): void {
            $lockedBatch = ProductCompletionTaskBatch::query()->lockForUpdate()->findOrFail($batch->getKey());
            if ($lockedBatch->status === 'running') {
                $lockedBatch->forceFill([
                    'status' => 'completed',
                    'eligible_count' => $eligible,
                    'created_count' => $created,
                    'failed_count' => $failed,
                    'completed_at' => now(),
                ])->save();
            }
        }, 3);

        $skipped = $requestedCount === null ? 0 : max(0, $requestedCount - $eligible);
        $this->selectedProducts = [];
        $this->selectAll = false;

        $message = $eligible === 0
            ? 'در نتایج فعلی محصول واجد شرایطِ بدون سابقه تسک پیدا نشد.'
            : "تسک ایجاد و منتشرشده: {$created}";
        if ($skipped > 0 || $failed > 0) {
            $message .= "؛ انتخاب‌های خارج از نتایج یا دارای سابقه تسک: {$skipped}";
            $message .= "؛ خطای ایجاد: {$failed}";
        }
        $this->dispatch('notify', message: $message, type: $created > 0 ? 'success' : 'warning');
    }

    public function undoLatestBulkTaskCreation(ProductCompletionTaskService $service): void
    {
        Gate::authorize('undoBulkCreation', ProductCompletionTask::class);

        $actor = auth()->user();
        $batch = ProductCompletionTaskBatch::query()
            ->where('created_by', $actor->getKey())
            ->whereIn('status', ['running', 'completed', 'rolling_back'])
            ->whereNull('rolled_back_at')
            ->whereHas('tasks')
            ->latest('id')
            ->first();

        if ($batch === null) {
            $this->dispatch('notify', message: 'عملیات گروهیِ قابل بازگشت پیدا نشد.', type: 'warning');
            return;
        }

        $prepared = \DB::transaction(function () use ($batch, $actor): bool {
            $lockedBatch = ProductCompletionTaskBatch::query()->lockForUpdate()->findOrFail($batch->getKey());
            if ((int) $lockedBatch->created_by !== (int) $actor->getKey()
                || ! in_array($lockedBatch->status, ['running', 'completed', 'rolling_back'], true)
                || $lockedBatch->rolled_back_at !== null) {
                return false;
            }

            if ($lockedBatch->status !== 'rolling_back') {
                $lockedBatch->forceFill(['status' => 'rolling_back'])->save();
            }

            return true;
        }, 3);

        if (! $prepared) {
            $this->bulkTaskUndoResult = 'این عملیات قبلاً بازگردانده شده یا دیگر قابل بازگردانی نیست.';
            $this->dispatch('notify', message: $this->bulkTaskUndoResult, type: 'warning');
            return;
        }

        set_time_limit(300);
        $batch = ProductCompletionTaskBatch::query()->findOrFail($batch->getKey());
        $cursor = $batch->rollback_cursor_task_id;
        $tasks = ProductCompletionTask::query()
            ->where('bulk_creation_batch_id', $batch->getKey())
            ->when($cursor !== null, fn ($query) => $query->where('id', '>', $cursor))
            ->orderBy('id')
            ->limit(self::BULK_UNDO_CHUNK_SIZE)
            ->get();

        foreach ($tasks as $task) {
            try {
                \DB::transaction(function () use ($task, $batch, $actor, $service): void {
                    $lockedBatch = ProductCompletionTaskBatch::query()->lockForUpdate()->findOrFail($batch->getKey());
                    if ($lockedBatch->status !== 'rolling_back' || $lockedBatch->rolled_back_at !== null) {
                        return;
                    }
                    if ($lockedBatch->rollback_cursor_task_id !== null
                        && $task->getKey() <= $lockedBatch->rollback_cursor_task_id) {
                        return;
                    }

                    $lockedTask = ProductCompletionTask::query()->lockForUpdate()->find($task->getKey());
                    $cancelled = $lockedTask !== null
                        && (int) $lockedTask->bulk_creation_batch_id === (int) $lockedBatch->getKey()
                        ? $service->cancelUnclaimedBulkTask($actor, $lockedTask, $lockedBatch->getKey())
                        : false;

                    if ($lockedTask === null || (int) $lockedTask->bulk_creation_batch_id !== (int) $lockedBatch->getKey()) {
                        $lockedBatch->forceFill([
                            'rollback_cursor_task_id' => $task->getKey(),
                            'rollback_processed_count' => $lockedBatch->rollback_processed_count + 1,
                            'protected_count' => $lockedBatch->protected_count + 1,
                        ])->save();
                        return;
                    }
                    $lockedBatch->forceFill([
                        'rollback_cursor_task_id' => $lockedTask->getKey(),
                        'rollback_processed_count' => $lockedBatch->rollback_processed_count + 1,
                        'cancelled_count' => $lockedBatch->cancelled_count + ($cancelled ? 1 : 0),
                        'protected_count' => $lockedBatch->protected_count + ($cancelled ? 0 : 1),
                    ])->save();
                }, 3);
            } catch (\Throwable $exception) {
                report($exception);
                $this->bulkTaskUndoResult = 'بازگردانی به‌طور موقت متوقف شد؛ دوباره اقدام کنید تا از همان مرحله ادامه پیدا کند.';
                $this->dispatch('notify', message: $this->bulkTaskUndoResult, type: 'warning');
                return;
            }
        }

        $batch = ProductCompletionTaskBatch::query()->findOrFail($batch->getKey());
        $hasMoreTasks = ProductCompletionTask::query()
            ->where('bulk_creation_batch_id', $batch->getKey())
            ->when($batch->rollback_cursor_task_id !== null, fn ($query) => $query->where('id', '>', $batch->rollback_cursor_task_id))
            ->exists();

        if ($hasMoreTasks) {
            $total = ProductCompletionTask::query()->where('bulk_creation_batch_id', $batch->getKey())->count();
            $this->bulkTaskUndoResult = "بازگردانی مرحله‌ای انجام شد: {$batch->rollback_processed_count} از {$total} تسک بررسی شد. دکمهٔ ادامه را برای مرحلهٔ بعد بزنید.";
            $this->dispatch('notify', message: $this->bulkTaskUndoResult, type: 'success');
            return;
        }

        $result = \DB::transaction(function () use ($batch, $actor): ?array {
            $lockedBatch = ProductCompletionTaskBatch::query()->lockForUpdate()->findOrFail($batch->getKey());
            if ((int) $lockedBatch->created_by !== (int) $actor->getKey()
                || $lockedBatch->status !== 'rolling_back'
                || $lockedBatch->rolled_back_at !== null) {
                return null;
            }

            $lockedBatch->forceFill([
                'status' => 'rolled_back',
                'eligible_count' => max($lockedBatch->eligible_count, $lockedBatch->rollback_processed_count),
                'created_count' => max($lockedBatch->created_count, $lockedBatch->rollback_processed_count),
                'rolled_back_at' => now(),
            ])->save();

            return [$lockedBatch->cancelled_count, $lockedBatch->protected_count];
        }, 3);

        if ($result === null) {
            $this->bulkTaskUndoResult = 'این عملیات هنگام بازگردانی تغییر کرده است؛ وضعیت را تازه‌سازی کنید.';
            $this->dispatch('notify', message: $this->bulkTaskUndoResult, type: 'warning');
            return;
        }

        [$cancelled, $protected] = $result;
        if ($cancelled === 0 && $protected === 0) {
            $this->bulkTaskUndoResult = 'تسکی برای بازگردانی پیدا نشد.';
            $this->dispatch('notify', message: $this->bulkTaskUndoResult, type: 'warning');
            return;
        }

        $this->bulkTaskUndoResult = "بازگردانی کامل شد: {$cancelled} تسک لغو شد و {$protected} تسکِ پذیرفته‌شده یا در حال رسیدگی بدون تغییر باقی ماند.";
        $this->dispatch('notify', message: $this->bulkTaskUndoResult, type: 'success');
    }
    private function hasDefaultFullCompletionRule(): bool
    {
        $now = now();

        return CommissionRule::query()
            ->where('task_type', ProductCompletionTaskType::FULL_COMPLETION->value)
            ->where('is_default', true)
            ->where('is_active', true)
            ->where(fn ($rules) => $rules->whereNull('effective_from')->orWhere('effective_from', '<=', $now))
            ->where(fn ($rules) => $rules->whereNull('effective_until')->orWhere('effective_until', '>=', $now))
            ->exists();
    }

    private function hasPendingBulkTaskRollback(): bool
    {
        return ProductCompletionTaskBatch::query()
            ->where('created_by', auth()->id())
            ->where('status', 'rolling_back')
            ->whereNull('rolled_back_at')
            ->whereHas('tasks')
            ->exists();
    }

    protected function getFilteredQuery(bool $includeCompletionCountFilter = true, bool $includeSupplierCountFilter = true, bool $includeGalleryImageCountFilter = true)
    {
        $query = Product::query();

        if (!empty($this->search)) {
            $query->where(function ($q) {
                $q->where('title', 'like', '%' . $this->search . '%')
                    ->orWhere('sku', 'like', '%' . $this->search . '%');
            });
        }

        if (!empty($this->category_id)) {
            $query->where('products.category_id', $this->category_id);
        }

        if (!empty($this->status)) {
            $query->where('products.status', $this->status);
        }

        if ($this->primaryImageStatus === 'has') {
            $query->whereHas('primaryImage');
        } elseif ($this->primaryImageStatus === 'missing') {
            $query->whereDoesntHave('primaryImage');
        }

        $shortDescriptionContent = "TRIM(REPLACE(REPLACE(REPLACE(COALESCE(products.short_description, ''), CHAR(9), ''), CHAR(10), ''), CHAR(13), ''))";
        $fullDescriptionContent = "TRIM(REPLACE(REPLACE(REPLACE(COALESCE(products.full_description, ''), CHAR(9), ''), CHAR(10), ''), CHAR(13), ''))";

        if ($this->shortDescriptionStatus === 'has') {
            $query->whereRaw($shortDescriptionContent." <> ''");
        } elseif ($this->shortDescriptionStatus === 'missing') {
            $query->whereRaw($shortDescriptionContent." = ''");
        }

        if ($this->fullDescriptionStatus === 'has') {
            $query->whereRaw($fullDescriptionContent." <> ''");
        } elseif ($this->fullDescriptionStatus === 'missing') {
            $query->whereRaw($fullDescriptionContent." = ''");
        }

        $galleryImageCounts = collect($this->galleryImageCounts)
            ->map(static fn ($count) => filter_var((string) $count, FILTER_VALIDATE_INT))
            ->filter(static fn ($count) => $count !== false && $count >= 0)
            ->unique()
            ->values();
        if ($includeGalleryImageCountFilter && $galleryImageCounts->isNotEmpty()) {
            $query->where(function ($countQuery) use ($galleryImageCounts): void {
                foreach ($galleryImageCounts as $count) {
                    $galleryConstraint = static fn ($gallery) => $gallery
                        ->reorder()
                        ->where('file_type', 'image')
                        ->where('is_primary', false);
                    if ($count === 0) {
                        $countQuery->orWhereDoesntHave('galleries', $galleryConstraint);
                    } else {
                        $countQuery->orWhereHas('galleries', $galleryConstraint, '=', $count);
                    }
                }
            });
        }

        if ($this->pricingStatus === 'priced') {
            $query->whereHas('productSuppliers', fn ($supplier) => $supplier
                ->where('is_active', true)->whereHas('validPrices'));
        } elseif ($this->pricingStatus === 'unpriced') {
            $query->whereDoesntHave('productSuppliers', fn ($supplier) => $supplier
                ->where('is_active', true)->whereHas('validPrices'));
        }

        $supplierCounts = collect($this->supplierCounts)
            ->map(static fn ($count) => filter_var((string) $count, FILTER_VALIDATE_INT))
            ->filter(static fn ($count) => $count !== false && $count >= 0)
            ->unique()
            ->values();
        if ($includeSupplierCountFilter && $supplierCounts->isNotEmpty()) {
            $query->where(function ($supplierCountQuery) use ($supplierCounts): void {
                foreach ($supplierCounts as $supplierCount) {
                    $supplierCountQuery->orWhereHas('productSuppliers', function ($supplier) {
                        $supplier->where('is_active', true)->whereHas('validPrices');
                    }, '=', $supplierCount);
                }
            });
        }

        if ($this->taskStatus === 'none') {
            $query->whereDoesntHave('completionTasks');
        } elseif ($this->taskStatus === 'active') {
            $query->whereHas('completionTasks', fn ($task) => $task->whereNotNull('active_product_id'));
        } elseif ($this->taskStatus === 'completed') {
            $query->whereHas('completionTasks', fn ($task) => $task->where('status', ProductCompletionTaskStatus::COMPLETED));
        } elseif ($this->taskStatus === 'any') {
            $query->whereHas('completionTasks');
        }

        if ($this->taskType !== '') {
            $query->whereHas('completionTasks', fn ($task) => $task->where('task_type', $this->taskType));
        }

        $completionCounts = collect($this->completionCounts)
            ->map(static fn ($count) => filter_var((string) $count, FILTER_VALIDATE_INT))
            ->filter(static fn ($count) => $count !== false && $count >= 0)
            ->unique()
            ->values();
        $hasCompletionCountFilter = $completionCounts->isNotEmpty();

        if (in_array($this->completionStatus, ['needs_completion', 'complete'], true)
            || ($includeCompletionCountFilter && $hasCompletionCountFilter)) {
            $attributeCounts = \DB::table('attribute_product')
                ->select('product_id')->selectRaw('COUNT(*) as attributes_count')->groupBy('product_id');
            $categoryAverages = \DB::table('products as category_products')
                ->leftJoinSub($attributeCounts, 'category_attribute_counts', 'category_products.id', '=', 'category_attribute_counts.product_id')
                ->select('category_products.category_id')
                ->selectRaw('AVG(COALESCE(category_attribute_counts.attributes_count, 0)
                    + CASE WHEN category_products.short_description IS NOT NULL AND category_products.short_description != "" THEN 1 ELSE 0 END
                    + CASE WHEN category_products.full_description IS NOT NULL AND category_products.full_description != "" THEN 1 ELSE 0 END) as average_attributes')
                ->whereNull('category_products.deleted_at')
                ->groupBy('category_products.category_id');

            $query->leftJoinSub($attributeCounts, 'product_attribute_counts', 'products.id', '=', 'product_attribute_counts.product_id')
                ->leftJoinSub($categoryAverages, 'category_completion_averages', 'products.category_id', '=', 'category_completion_averages.category_id');
            $actualCount = 'COALESCE(product_attribute_counts.attributes_count, 0)
                + CASE WHEN products.short_description IS NOT NULL AND products.short_description != "" THEN 1 ELSE 0 END
                + CASE WHEN products.full_description IS NOT NULL AND products.full_description != "" THEN 1 ELSE 0 END';
            $averageCount = 'COALESCE(category_completion_averages.average_attributes, 0)';

            if (in_array($this->completionStatus, ['needs_completion', 'complete'], true)) {
                $query->whereRaw($actualCount.($this->completionStatus === 'needs_completion' ? ' < ' : ' >= ').$averageCount);
            }

            if ($includeCompletionCountFilter && $hasCompletionCountFilter) {
                $query->where(function ($countQuery) use ($completionCounts, $actualCount, $averageCount): void {
                    foreach ($completionCounts as $completionCount) {
                        $countQuery->orWhere(function ($exactCountQuery) use ($completionCount, $actualCount, $averageCount): void {
                            if ($completionCount === 0) {
                                $exactCountQuery->whereRaw($actualCount.' >= '.$averageCount);
                                return;
                            }

                            $exactCountQuery->whereRaw($actualCount.' < '.$averageCount.' - ?', [$completionCount - 1])
                                ->whereRaw($actualCount.' >= '.$averageCount.' - ?', [$completionCount]);
                        });
                    }
                });
            }
        }

        switch ($this->sort) {
            case 'oldest': $query->oldest(); break;
            case 'title_asc': $query->orderBy('title', 'asc'); break;
            case 'title_desc': $query->orderBy('title', 'desc'); break;
            case 'latest':
            default: $query->latest(); break;
        }

        return $query;
    }

    /**
     * خروجی اکسل
     */
    public function exportExcel()
    {
        set_time_limit(300);
        ini_set('memory_limit', '512M');

        $fileName = 'products-export-' . now()->format('Y-m-d_H-i-s') . '.xlsx';
        $filePath = storage_path('app/public/' . $fileName);

        // بررسی ادمین اصلی
        $isMainAdmin = auth()->check() && auth()->user()->email === 'admin@chanta.ir';

        // محاسبه میانگین
        $categoryAverages = $this->getCategoryAttributeAverages();

        // تعریف هدرها
        $headers = [
            'شناسه', 'عنوان محصول', 'کد کالا', 'تأمین‌کنندگان قیمت‌گذار', 'دسته‌بندی',
            'وضعیت نمایش', 'وضعیت کالا', 'تعداد ویژگی', 'میانگین دسته',
            'نیاز به تکمیل', 'وضعیت SEO', 'نیاز به تکمیل دارد'
        ];

        // تأمین‌کنندگانی که برای هر کالا حداقل یک قیمت ثبت کرده‌اند، به‌صورت JSON در یک ستون نمایش داده می‌شوند.
        $pricedSuppliers = function (Product $product): string {
            return $product->productSuppliers
                ->filter(fn ($productSupplier) => $productSupplier->supplier !== null)
                ->map(fn ($productSupplier) => [
                    'id' => (int) $productSupplier->supplier->id,
                    'name' => (string) ($productSupplier->supplier->brand_name ?: $productSupplier->supplier->company_name),
                ])
                ->values()
                ->toJson(JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        };

        if ($isMainAdmin) {
            $headers[] = 'آخرین بروزرسانی ویژگی';
            $headers[] = 'تعداد ویژگی بروز شده';
        }

        $headers[] = 'تاریخ ایجاد';

        $writer = SimpleExcelWriter::create($filePath)->addHeader($headers);

        $this->getFilteredQuery()
            ->with([
                'category',
                'productSuppliers' => function ($query) {
                    $query->has('prices')
                        ->select(['id', 'product_id', 'supplier_id'])
                        ->with('supplier:id,company_name,brand_name');
                },
            ])
            ->leftJoin(\DB::raw('(
                SELECT
                    product_id,
                    COUNT(*) as attributes_count,
                    MAX(updated_at) as last_attribute_update
                FROM attribute_product
                GROUP BY product_id
            ) as attr_stats'), 'products.id', '=', 'attr_stats.product_id')
            ->select('products.*', 'attr_stats.attributes_count', 'attr_stats.last_attribute_update')
            ->lazy(100)
            ->each(function ($product) use ($writer, $categoryAverages, $isMainAdmin, $pricedSuppliers) {
                // وضعیت
                $statusLabel = 'نامشخص';
                if ($product->status instanceof ProductStatus) {
                    $statusLabel = ProductStatus::options()[$product->status->value] ?? $product->status->name;
                } elseif (is_scalar($product->status)) {
                    $statusLabel = ProductStatus::options()[$product->status] ?? $product->status;
                }

                // تعداد ویژگی (شامل توضیحات)
                $attributesCount = (int) ($product->attributes_count ?? 0);
                $attributesCount += (!empty($product->short_description) ? 1 : 0);
                $attributesCount += (!empty($product->full_description) ? 1 : 0);

                // میانگین دسته
                $categoryAvg = $categoryAverages[$product->category_id] ?? 0;
                $categoryAvgFormatted = number_format($categoryAvg, 1);

                // نیاز به تکمیل
                if ($attributesCount >= $categoryAvg) {
                    $needToComplete = 'کامل';
                } else {
                    $needCount = (int) ceil($categoryAvg - $attributesCount);
                    $needToComplete = "نیاز به {$needCount} ویژگی";
                }

                // SEO
                $seoStatus = $this->checkSeoCompletion($product);

                // ردیف پایه
                $row = [
                    $product->id,
                    $product->title,
                    $product->sku ?? '-',
                    $pricedSuppliers($product),
                    $product->category?->name ?? 'بدون دسته',
                    $product->is_active ? 'فعال' : 'غیرفعال',
                    $statusLabel,
                    $attributesCount,
                    $categoryAvgFormatted,
                    $needToComplete,
                    $seoStatus,
                    $attributesCount < $categoryAvg ? 'بله' : 'خیر',
                ];

                // ستون‌های مخصوص ادمین
                if ($isMainAdmin) {
                    $row[] = $product->last_attribute_update
                        ? jdate($product->last_attribute_update)->format('Y/m/d H:i')
                        : 'هرگز';

                    $recentUpdatedCount = 0;
                    if ($product->last_attribute_update) {
                        $lastUpdate = \Carbon\Carbon::parse($product->last_attribute_update);
                        $recentUpdatedCount = \DB::table('attribute_product')
                            ->where('product_id', $product->id)
                            ->whereBetween('updated_at', [
                                $lastUpdate->copy()->subMinutes(5),
                                $lastUpdate->copy()->addMinutes(5)
                            ])
                            ->count();
                    }
                    $row[] = $recentUpdatedCount;
                }

                $row[] = $product->created_at ? jdate($product->created_at)->format('Y/m/d H:i') : '-';

                $writer->addRow($row);
            });

        $writer->close();
        return response()->download($filePath)->deleteFileAfterSend(true);
    }

    /**
     * محاسبه میانگین (شامل توضیحات)
     */
    private function getCategoryAttributeAverages(): array
    {
        return Cache::remember('admin.products.category_attribute_averages', now()->addMinutes(2), static function (): array {
        $results = \DB::table('products')
            ->select('category_id')
            ->selectRaw('AVG(
                COALESCE(attr_count, 0) +
                CASE WHEN short_description IS NOT NULL AND short_description != "" THEN 1 ELSE 0 END +
                CASE WHEN full_description IS NOT NULL AND full_description != "" THEN 1 ELSE 0 END
            ) as avg_attributes')
            ->leftJoin(\DB::raw('(
                SELECT product_id, COUNT(*) as attr_count
                FROM attribute_product
                GROUP BY product_id
            ) as counts'), 'products.id', '=', 'counts.product_id')
            ->whereNull('products.deleted_at')
            ->groupBy('category_id')
            ->get();

        $averages = [];
        foreach ($results as $row) {
            $averages[$row->category_id] = (float) $row->avg_attributes;
        }
        return $averages;
        });
    }

    private function getCompletionCountOptions(array $categoryAverages, $filteredProductIds): array
    {
        $attributeCounts = \DB::table('attribute_product')
            ->select('product_id')
            ->selectRaw('COUNT(*) as attributes_count')
            ->groupBy('product_id');
        $actualCount = 'COALESCE(completion_attribute_counts.attributes_count, 0)
            + CASE WHEN products.short_description IS NOT NULL AND products.short_description != "" THEN 1 ELSE 0 END
            + CASE WHEN products.full_description IS NOT NULL AND products.full_description != "" THEN 1 ELSE 0 END';

        $countsByCategory = \DB::table('products')
            ->leftJoinSub($attributeCounts, 'completion_attribute_counts', 'products.id', '=', 'completion_attribute_counts.product_id')
            ->whereNull('products.deleted_at')
            ->whereIn('products.id', $filteredProductIds)
            ->distinct()
            ->select('products.category_id')
            ->selectRaw($actualCount.' as actual_count')
            ->get();

        return $countsByCategory
            ->map(function ($row) use ($categoryAverages) {
                $average = (float) ($categoryAverages[$row->category_id] ?? 0);
                return max(0, (int) ceil($average - (int) $row->actual_count));
            })
            ->filter(fn (int $count) => $count > 0)
            ->unique()
            ->sortDesc()
            ->values()
            ->all();
    }

    private function getSupplierCountOptions($filteredProductIds): array
    {
        $productsWithSupplierCounts = Product::query()
            ->whereIn('products.id', $filteredProductIds)
            ->select('products.id')
            ->withCount(['productSuppliers as valid_supplier_count' => fn ($supplier) => $supplier
                ->where('is_active', true)->whereHas('validPrices')]);

        return \DB::query()
            ->fromSub($productsWithSupplierCounts, 'filtered_products')
            ->select('valid_supplier_count')
            ->distinct()
            ->orderByDesc('valid_supplier_count')
            ->pluck('valid_supplier_count')
            ->map(static fn ($count): int => (int) $count)
            ->all();
    }

    private function getGalleryImageCountOptions($filteredProductIds): array
    {
        $productsWithGalleryCounts = Product::query()
            ->whereIn('products.id', $filteredProductIds)
            ->select('products.id')
            ->withCount(['galleries as gallery_image_count' => fn ($gallery) => $gallery
                ->reorder()
                ->where('file_type', 'image')
                ->where('is_primary', false)]);

        return \DB::query()
            ->fromSub($productsWithGalleryCounts, 'filtered_products')
            ->select('gallery_image_count')
            ->distinct()
            ->orderByDesc('gallery_image_count')
            ->pluck('gallery_image_count')
            ->map(static fn ($count): int => (int) $count)
            ->all();
    }

    private function checkSeoCompletion(Product $product): string
    {
        $filledCount = 0;
        if (!empty($product->seo_title)) $filledCount++;
        if (!empty($product->seo_description)) $filledCount++;
        if (!empty($product->seo_keywords)) $filledCount++;

        return match($filledCount) {
            0 => 'خالی',
            3 => 'کامل',
            default => "ناقص ({$filledCount}/3)"
        };
    }

    public function render()
    {
        $query = $this->getFilteredQuery()
            ->with([
                'category',
                'galleries' => fn ($gallery) => $gallery->where('is_primary', true),
                'productSuppliers' => fn ($supplier) => $supplier->where('is_active', true)
                    ->whereHas('validPrices')
                    ->with(['supplier:id,company_name,brand_name', 'validPrices.priceType', 'validPrices.checkPeriod']),
            ])
            ->withExists(['completionTasks as has_active_completion_task' => fn ($task) => $task->whereNotNull('active_product_id')])
            ->withCount('attributeValues');

        $categories = Cache::remember('admin.products.filter.categories', now()->addMinutes(10), static fn () =>
            Category::where('is_active', true)->doesntHave('children')->orderBy('name')->get()
        );
        $statuses = ProductStatus::options();

        $products = $query->paginate($this->safePerPage());
        $categoryAverages = $this->getCategoryAttributeAverages();
        $filteredProductIds = $this->getFilteredQuery(false, false)->reorder()->select('products.id');
        $galleryCountProductIds = $this->getFilteredQuery(false, false, false)->reorder()->select('products.id');
        $completionCountOptions = $this->getCompletionCountOptions($categoryAverages, $filteredProductIds);
        $supplierCountOptions = $this->getSupplierCountOptions($filteredProductIds);
        $galleryImageCountOptions = $this->getGalleryImageCountOptions($galleryCountProductIds);
        $products->getCollection()->transform(function (Product $product) use ($categoryAverages) {
            $actualCount = (int) ($product->attribute_values_count ?? 0);
            $actualCount += !empty($product->short_description) ? 1 : 0;
            $actualCount += !empty($product->full_description) ? 1 : 0;
            $average = (float) ($categoryAverages[$product->category_id] ?? 0);
            $product->needs_completion_count = max(0, (int) ceil($average - $actualCount));
            return $product;
        });

        return view('livewire.admin.products.index', [
            'products' => $products,
            'completionCountOptions' => $completionCountOptions,
            'supplierCountOptions' => $supplierCountOptions,
            'galleryImageCountOptions' => $galleryImageCountOptions,
            'latestUndoableTaskBatch' => ProductCompletionTaskBatch::query()
                ->where('created_by', auth()->id())
                ->whereIn('status', ['running', 'completed', 'rolling_back'])
                ->whereNull('rolled_back_at')
                ->whereHas('tasks')
                ->withCount('tasks')
                ->latest('id')
                ->first(),
            'hasPendingBulkTaskRollback' => $this->hasPendingBulkTaskRollback(),
            'canUndoBulkTaskCreation' => auth()->user()?->can('undoBulkCreation', ProductCompletionTask::class) ?? false,
            'hasDefaultFullCompletionRule' => $this->hasDefaultFullCompletionRule(),
            'categories' => $categories,
            'statuses' => $statuses,
            'taskStatuses' => [
                'none' => 'بدون سابقه تسک',
                'active' => 'دارای تسک فعال',
                'completed' => 'تسک تکمیل‌شده دارد',
                'any' => 'دارای سابقه تسک',
            ],
            'taskTypes' => ProductCompletionTaskType::options(),
        ]);
    }
}
