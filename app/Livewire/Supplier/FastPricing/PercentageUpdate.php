<?php

namespace App\Livewire\Supplier\FastPricing;

use App\Helpers\PriceRoundingHelper;
use App\Models\PriceUpdateBatch;
use App\Models\PriceUpdateItem;
use App\Models\ProductPrice;
use App\Models\ProductSupplier;
use App\Models\Supplier;
use App\Models\SupplierPriceRoundingRange;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use RuntimeException;
use Throwable;

#[Layout('components.layouts.supplier')]
class PercentageUpdate extends Component
{
    use WithPagination;

    private const OPERATION_TYPE = 'fixed_percentage';
    private const MIN_PERCENTAGE = -99.99;
    private const MAX_PERCENTAGE = 1000;
    private const MAX_PRICE = 999999999999;
    private const MAX_PRODUCTS = 2000;

    public string $percentage = '';
    public ?int $batchId = null;
    public string $notice = '';
    public array $priceErrors = [];

    public function mount(): void
    {
        $supplierId = $this->supplierId();

        $pendingBatch = PriceUpdateBatch::query()
            ->where('supplier_id', $supplierId)
            ->where('operation_type', self::OPERATION_TYPE)
            ->where('status', 'pending')
            ->latest('id')
            ->first();

        if ($pendingBatch) {
            $this->batchId = (int) $pendingBatch->id;
            $this->percentage = $this->formatPercentage((float) $pendingBatch->percentage);
        }
    }

    protected function rules(): array
    {
        return [
            'percentage' => [
                'required',
                'regex:/^-?\d{1,4}(?:[\.,]\d{1,2})?$/',
                'numeric',
                'min:' . self::MIN_PERCENTAGE,
                'max:' . self::MAX_PERCENTAGE,
            ],
        ];
    }

    protected function messages(): array
    {
        return [
            'percentage.required' => 'درصد تغییر قیمت را وارد کنید.',
            'percentage.regex' => 'درصد باید عددی با حداکثر دو رقم اعشار باشد.',
            'percentage.numeric' => 'درصد واردشده معتبر نیست.',
            'percentage.min' => 'درصد کاهش نمی‌تواند کمتر از ۹۹٫۹۹- باشد.',
            'percentage.max' => 'درصد افزایش نمی‌تواند بیشتر از ۱۰۰۰ باشد.',
        ];
    }

    public function generatePreview(): void
    {
        $this->percentage = $this->normalizePercentage($this->percentage);
        $validated = $this->validate();
        $percentage = (float) $validated['percentage'];
        $supplierId = $this->supplierId();

        try {
            $batchId = DB::transaction(function () use ($supplierId, $percentage): int {
                Supplier::query()->whereKey($supplierId)->lockForUpdate()->firstOrFail();

                $existingBatch = PriceUpdateBatch::query()
                    ->where('supplier_id', $supplierId)
                    ->where('status', 'pending')
                    ->lockForUpdate()
                    ->first();

                if ($existingBatch) {
                    if ($existingBatch->operation_type === self::OPERATION_TYPE) {
                        $this->percentage = $this->formatPercentage((float) $existingBatch->percentage);

                        return (int) $existingBatch->id;
                    }

                    throw ValidationException::withMessages([
                        'percentage' => 'ابتدا عملیات اکسلِ در انتظار تأیید را بررسی یا لغو کنید.',
                    ]);
                }

                $productCount = ProductSupplier::query()
                    ->where('supplier_id', $supplierId)
                    ->whereHas('prices', fn ($query) => $query->where('price', '>', 0))
                    ->count();

                if ($productCount > self::MAX_PRODUCTS) {
                    throw ValidationException::withMessages([
                        'percentage' => 'تعداد کالاهای قابل بروزرسانی بیش از سقف ۲۰۰۰ کالا است. لطفاً با پشتیبانی تماس بگیرید.',
                    ]);
                }

                $batch = PriceUpdateBatch::create([
                    'supplier_id' => $supplierId,
                    'operation_type' => self::OPERATION_TYPE,
                    'percentage' => $percentage,
                    'status' => 'pending',
                ]);

                $roundingSettings = $this->roundingSettings($supplierId);
                $createdItems = 0;

                ProductSupplier::query()
                    ->where('supplier_id', $supplierId)
                    ->whereHas('prices', fn ($query) => $query->where('price', '>', 0))
                    ->with([
                        'product:id,title,quantity_tiers',
                        'prices' => fn ($query) => $query
                            ->where('price', '>', 0)
                            ->with(['priceType:id,name,code', 'checkPeriod:id,name,label,days'])
                            ->orderBy('tier_index')
                            ->orderBy('price_type_id')
                            ->orderBy('check_period_id'),
                    ])
                    ->orderBy('id')
                    ->chunkById(100, function ($productSuppliers) use (
                        $batch,
                        $percentage,
                        $roundingSettings,
                        &$createdItems
                    ): void {
                        foreach ($productSuppliers as $productSupplier) {
                            $oldPrices = [];
                            $newPrices = [];
                            $tiers = $productSupplier->product?->quantity_tiers ?? [];

                            foreach ($productSupplier->prices as $price) {
                                $key = 'P' . (int) $price->id;
                                $tierName = $tiers[$price->tier_index]['name']
                                    ?? 'تیراژ ' . ((int) $price->tier_index + 1);
                                $priceTypeName = $price->priceType?->name ?? 'نوع قیمت نامشخص';
                                $checkPeriodName = $price->checkPeriod?->display_label;
                                $oldPrice = (int) $price->price;
                                $calculatedPrice = $oldPrice * (1 + ($percentage / 100));
                                $newPrice = (int) $this->roundPrice($calculatedPrice, $roundingSettings);

                                if ($newPrice < 1 || $newPrice > self::MAX_PRICE) {
                                    throw ValidationException::withMessages([
                                        'percentage' => "درصد واردشده برای کالای «{$productSupplier->product?->title}» قیمت نامعتبر ایجاد می‌کند.",
                                    ]);
                                }

                                $snapshot = [
                                    'product_price_id' => (int) $price->id,
                                    'price' => $oldPrice,
                                    'tier_index' => (int) $price->tier_index,
                                    'price_type_id' => (int) $price->price_type_id,
                                    'check_period_id' => $price->check_period_id ? (int) $price->check_period_id : null,
                                    'tier_name' => (string) $tierName,
                                    'price_type_name' => (string) $priceTypeName,
                                    'check_period_name' => $checkPeriodName ? (string) $checkPeriodName : null,
                                    'is_active' => (bool) $price->is_active,
                                ];

                                $oldPrices[$key] = $snapshot;
                                $newPrices[$key] = array_replace($snapshot, ['price' => $newPrice]);
                            }

                            PriceUpdateItem::create([
                                'price_update_batch_id' => $batch->id,
                                'product_supplier_id' => $productSupplier->id,
                                'old_prices' => $oldPrices,
                                'new_prices' => $newPrices,
                                'old_stock' => $productSupplier->stock_quantity,
                                'new_stock' => null,
                                'old_min_order_quantity' => $productSupplier->min_order_quantity,
                                'new_min_order_quantity' => null,
                                'old_is_active' => (bool) $productSupplier->is_active,
                                'new_is_active' => (bool) $productSupplier->is_active,
                                'status' => 'valid',
                                'error_message' => null,
                            ]);
                            $createdItems++;
                        }
                    }, 'id');

                if ($createdItems === 0) {
                    throw ValidationException::withMessages([
                        'percentage' => 'هیچ کالای دارای قیمت برای بروزرسانی یافت نشد.',
                    ]);
                }

                return (int) $batch->id;
            }, 3);

            $this->batchId = $batchId;
            $this->notice = 'پیش‌نمایش قیمت‌ها آماده شد.';
            $this->resetPage();
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('Fixed percentage preview generation failed.', [
                'supplier_id' => $supplierId,
                'exception' => $exception,
            ]);
            $this->addError('percentage', 'ساخت پیش‌نمایش با خطا مواجه شد. دوباره تلاش کنید.');
        }
    }

    public function updatePrice(int $itemId, string $priceKey, mixed $value): void
    {
        $this->notice = '';
        $errorKey = $itemId . '-' . $priceKey;
        $normalizedPrice = $this->normalizePrice($value);

        $validator = Validator::make(
            ['price' => $normalizedPrice],
            ['price' => ['required', 'integer', 'min:1', 'max:' . self::MAX_PRICE]],
            [
                'price.required' => 'قیمت جدید الزامی است.',
                'price.integer' => 'قیمت جدید باید عدد صحیح باشد.',
                'price.min' => 'قیمت جدید باید حداقل ۱ تومان باشد.',
                'price.max' => 'قیمت جدید از حداکثر مجاز بیشتر است.',
            ]
        );

        if ($validator->fails()) {
            $this->priceErrors[$errorKey] = $validator->errors()->first('price');
            return;
        }

        unset($this->priceErrors[$errorKey]);

        DB::transaction(function () use ($itemId, $priceKey, $normalizedPrice): void {
            $batch = $this->lockOwnedPendingBatch();
            $item = PriceUpdateItem::query()
                ->whereKey($itemId)
                ->where('price_update_batch_id', $batch->id)
                ->lockForUpdate()
                ->firstOrFail();
            $newPrices = $item->new_prices ?? [];

            if (!isset($newPrices[$priceKey]) || !is_array($newPrices[$priceKey])) {
                abort(403, 'قیمت موردنظر متعلق به این پیش‌نویس نیست.');
            }

            $priceId = (int) ($newPrices[$priceKey]['product_price_id'] ?? 0);
            $ownsPrice = ProductPrice::query()
                ->whereKey($priceId)
                ->where('product_supplier_id', $item->product_supplier_id)
                ->whereHas('productSupplier', fn ($query) => $query->where('supplier_id', $this->supplierId()))
                ->exists();

            if (!$ownsPrice) {
                abort(403, 'شما مجاز به تغییر این قیمت نیستید.');
            }

            $newPrices[$priceKey]['price'] = (int) $normalizedPrice;
            $item->update(['new_prices' => $newPrices]);
        }, 3);

        $this->notice = 'قیمت جدید در پیش‌نویس ذخیره شد.';
    }

    public function updateProductStatus(int $itemId, int|string|bool $active): void
    {
        $normalizedStatus = filter_var($active, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($normalizedStatus === null) {
            abort(422, 'وضعیت کالا معتبر نیست.');
        }

        DB::transaction(function () use ($itemId, $normalizedStatus): void {
            $batch = $this->lockOwnedPendingBatch();
            $item = PriceUpdateItem::query()
                ->whereKey($itemId)
                ->where('price_update_batch_id', $batch->id)
                ->lockForUpdate()
                ->firstOrFail();
            $item->update(['new_is_active' => $normalizedStatus]);
        }, 3);

        $this->notice = 'وضعیت کالا در پیش‌نویس ذخیره شد.';
    }

    public function commitChanges()
    {
        if ($this->priceErrors !== []) {
            $this->addError('commit', 'ابتدا خطاهای قیمت را اصلاح کنید و از ذخیره شدن همه مقادیر مطمئن شوید.');
            return;
        }

        $supplierId = $this->supplierId();

        try {
            DB::transaction(function () use ($supplierId): void {
                Supplier::query()->whereKey($supplierId)->lockForUpdate()->firstOrFail();
                $batch = $this->lockOwnedPendingBatch();
                $batch->update(['status' => 'processing']);

                $items = $batch->items()
                    ->where('status', 'valid')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();
                if ($items->isEmpty()) {
                    throw new RuntimeException('هیچ تغییر معتبری برای ثبت وجود ندارد.');
                }

                foreach ($items as $item) {
                    $productSupplier = ProductSupplier::query()
                        ->whereKey($item->product_supplier_id)
                        ->where('supplier_id', $supplierId)
                        ->lockForUpdate()
                        ->first();

                    if (!$productSupplier) {
                        throw new RuntimeException('یکی از کالاها دیگر در دسترس شما نیست.');
                    }

                    if ((bool) $productSupplier->is_active !== (bool) $item->old_is_active) {
                        throw new RuntimeException('وضعیت یکی از کالاها پس از ساخت پیش‌نمایش تغییر کرده است. پیش‌نویس را لغو و دوباره ایجاد کنید.');
                    }

                    $oldPrices = $item->old_prices ?? [];
                    $newPrices = $item->new_prices ?? [];
                    $expectedPriceIds = collect($oldPrices)
                        ->pluck('product_price_id')
                        ->map(fn ($id): int => (int) $id)
                        ->sort()
                        ->values();
                    $currentPrices = ProductPrice::query()
                        ->where('product_supplier_id', $productSupplier->id)
                        ->where('price', '>', 0)
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get()
                        ->keyBy('id');

                    if ($currentPrices->keys()->map(fn ($id): int => (int) $id)->sort()->values()->all() !== $expectedPriceIds->all()) {
                        throw new RuntimeException('فهرست قیمت‌های یکی از کالاها پس از ساخت پیش‌نمایش تغییر کرده است. پیش‌نویس را لغو و دوباره ایجاد کنید.');
                    }

                    foreach ($newPrices as $key => $priceData) {
                        $this->assertValidSnapshot($key, $priceData, $oldPrices);
                        $oldPriceData = $oldPrices[$key];
                        $price = $currentPrices->get((int) $priceData['product_price_id']);

                        if (!$price || !$this->snapshotMatchesPrice($oldPriceData, $price)) {
                            throw new RuntimeException('یکی از قیمت‌ها پس از ساخت پیش‌نمایش تغییر کرده است. پیش‌نویس را لغو و دوباره ایجاد کنید.');
                        }

                        $price->update(['price' => (int) $priceData['price']]);
                    }

                    $productSupplier->is_active = (bool) $item->new_is_active;
                    $productSupplier->save();
                }

                $batch->update(['status' => 'completed']);
            }, 3);

            session()->flash('success', 'قیمت‌ها و وضعیت کالاها با موفقیت بروزرسانی شدند.');
            return $this->redirect(route('supplier.product-selection'), navigate: true);
        } catch (Throwable $exception) {
            Log::error('Fixed percentage price update failed.', [
                'supplier_id' => $supplierId,
                'batch_id' => $this->batchId,
                'exception' => $exception,
            ]);
            $this->addError('commit', $exception instanceof RuntimeException
                ? $exception->getMessage()
                : 'ثبت نهایی با خطا مواجه شد. هیچ تغییری اعمال نشد.');
        }
    }

    public function cancelBatch(): void
    {
        DB::transaction(function (): void {
            Supplier::query()->whereKey($this->supplierId())->lockForUpdate()->firstOrFail();
            $batch = $this->lockOwnedPendingBatch();
            $batch->update(['status' => 'cancelled']);
        }, 3);

        $this->batchId = null;
        $this->percentage = '';
        $this->notice = 'پیش‌نویس بروزرسانی لغو شد.';
        $this->priceErrors = [];
        $this->resetPage();
        $this->resetErrorBag();
    }

    public function render()
    {
        $items = new LengthAwarePaginator([], 0, 15);
        $totalProducts = 0;
        $totalPrices = 0;

        if ($this->batchId) {
            $batch = $this->ownedBatch();
            $items = $batch->items()
                ->with('productSupplier.product:id,title')
                ->orderBy('id')
                ->paginate(15);
            $totalProducts = $batch->items()->count();
            $totalPrices = $batch->items()->get(['new_prices'])->sum(
                fn (PriceUpdateItem $item): int => count($item->new_prices ?? [])
            );
        }

        return view('livewire.supplier.fast-pricing.percentage-update', compact(
            'items',
            'totalProducts',
            'totalPrices'
        ));
    }

    private function supplierId(): int
    {
        $supplierId = Auth::user()?->supplier?->id;
        abort_unless($supplierId, 403, 'تأمین‌کننده یافت نشد.');

        return (int) $supplierId;
    }

    private function ownedBatch(): PriceUpdateBatch
    {
        return PriceUpdateBatch::query()
            ->whereKey($this->batchId)
            ->where('supplier_id', $this->supplierId())
            ->where('operation_type', self::OPERATION_TYPE)
            ->where('status', 'pending')
            ->firstOrFail();
    }

    private function lockOwnedPendingBatch(): PriceUpdateBatch
    {
        return PriceUpdateBatch::query()
            ->whereKey($this->batchId)
            ->where('supplier_id', $this->supplierId())
            ->where('operation_type', self::OPERATION_TYPE)
            ->where('status', 'pending')
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function snapshotMatchesPrice(array $snapshot, ProductPrice $price): bool
    {
        return (int) ($snapshot['product_price_id'] ?? 0) === (int) $price->id
            && (int) ($snapshot['price'] ?? 0) === (int) $price->price
            && (int) ($snapshot['tier_index'] ?? -1) === (int) $price->tier_index
            && (int) ($snapshot['price_type_id'] ?? 0) === (int) $price->price_type_id
            && (($snapshot['check_period_id'] ?? null) === null
                ? $price->check_period_id === null
                : (int) $snapshot['check_period_id'] === (int) $price->check_period_id)
            && (bool) ($snapshot['is_active'] ?? false) === (bool) $price->is_active;
    }

    private function ownedItem(int $itemId): PriceUpdateItem
    {
        return PriceUpdateItem::query()
            ->whereKey($itemId)
            ->whereHas('batch', function ($query): void {
                $query->whereKey($this->batchId)
                    ->where('supplier_id', $this->supplierId())
                    ->where('operation_type', self::OPERATION_TYPE)
                    ->where('status', 'pending');
            })
            ->firstOrFail();
    }

    private function assertValidSnapshot(string $key, mixed $priceData, array $oldPrices): void
    {
        if (
            !isset($oldPrices[$key])
            || !is_array($priceData)
            || !isset($priceData['product_price_id'], $priceData['price'])
            || (int) $priceData['product_price_id'] !== (int) ($oldPrices[$key]['product_price_id'] ?? 0)
            || filter_var($priceData['price'], FILTER_VALIDATE_INT) === false
            || (int) $priceData['price'] < 1
            || (int) $priceData['price'] > self::MAX_PRICE
        ) {
            throw new RuntimeException('اطلاعات یکی از قیمت‌ها نامعتبر یا دستکاری شده است.');
        }
    }

    private function normalizePercentage(mixed $value): string
    {
        $value = strtr((string) $value, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '٫' => '.', ',' => '.',
        ]);

        return trim($value);
    }

    private function normalizePrice(mixed $value): string
    {
        $value = strtr((string) $value, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);

        return str_replace([',', '،', ' '], '', trim($value));
    }

    private function roundingSettings(int $supplierId): array
    {
        $supplier = Supplier::query()->findOrFail($supplierId);
        $ranges = SupplierPriceRoundingRange::query()
            ->forSupplier($supplierId)
            ->active()
            ->ordered()
            ->get(['min_price', 'max_price', 'rounding_method', 'rounding_step'])
            ->map(fn (SupplierPriceRoundingRange $range): array => [
                'min_price' => (float) $range->min_price,
                'max_price' => $range->max_price === null ? null : (float) $range->max_price,
                'method' => (string) $range->rounding_method,
                'step' => (int) $range->rounding_step,
            ])
            ->all();

        return [
            'type' => (string) ($supplier->price_rounding_type ?? 'nearest_1'),
            'ranges' => $ranges,
        ];
    }

    private function roundPrice(float $price, array $settings): float
    {
        foreach ($settings['ranges'] as $range) {
            if ($price >= $range['min_price'] && ($range['max_price'] === null || $price < $range['max_price'])) {
                $step = max(1, $range['step']);
                $division = round($price / $step, 8);

                return match ($range['method']) {
                    'ceil' => ceil($division) * $step,
                    'floor' => floor($division) * $step,
                    'nearest' => round($division) * $step,
                    default => $price,
                };
            }
        }

        return PriceRoundingHelper::roundByType($price, $settings['type']);
    }

    private function formatPercentage(float $percentage): string
    {
        return rtrim(rtrim(number_format($percentage, 2, '.', ''), '0'), '.');
    }
}
