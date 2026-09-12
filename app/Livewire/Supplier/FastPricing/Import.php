<?php

namespace App\Livewire\Supplier\FastPricing;

use App\Enums\PriceType;
use App\Helpers\SecureIdEncoder;
use App\Models\PriceUpdateBatch;
use App\Models\PriceUpdateItem;
use App\Models\ProductSupplier;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('components.layouts.supplier')]
class Import extends Component
{
    use WithFileUploads;

    public $file;
    public $isProcessing = false;
    public $processedBatchId = null;
    public $successfulRows = 0;
    public $errorRows = [];
    public $importErrors = [];

    protected $rules = [
        'file' => 'required|mimes:xlsx,csv,txt|max:5120', // حداکثر 5 مگابایت - پشتیبانی از CSV هم اضافه شد
    ];

    protected $messages = [
        'file.required' => 'لطفاً یک فایل انتخاب کنید.',
        'file.mimes' => 'فقط فرمت اکسل (xlsx) و CSV مجاز است.',
        'file.max' => 'حجم فایل نباید بیشتر از 5 مگابایت باشد.',
    ];

    public function mount()
    {
        // در صورت نیاز به مقداردهی اولیه
    }

    public function processFile()
    {
        $this->validate();
        $this->isProcessing = true;
        $this->errorRows = [];
        $this->importErrors = [];

        $supplier = Auth::user()->supplier;
        if (!$supplier) {
            $this->addError('file', 'تامین کننده یافت نشد.');
            $this->isProcessing = false;
            return;
        }

        DB::beginTransaction();
        try {
            // حذف دسته‌های در انتظار قبلی
            PriceUpdateBatch::where('supplier_id', $supplier->id)
                ->where('status', 'pending')
                ->delete();

            $batch = PriceUpdateBatch::create([
                'supplier_id' => $supplier->id,
                'status' => 'pending',
                'created_by' => Auth::id(),
            ]);

            // ═══════════════════════════════════════════════════════════
            // خواندن فایل (Excel یا CSV)
            // ═══════════════════════════════════════════════════════════
            $extension = strtolower($this->file->getClientOriginalExtension());
            
            if ($extension === 'csv' || $extension === 'txt') {
                // خواندن فایل CSV
                $content = file_get_contents($this->file->getRealPath());
                $lines = explode("\n", $content);
                $rows = [];
                
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '') continue;
                    
                    // تشخیص جداکننده (Tab یا کاما)
                    if (str_contains($line, "\t")) {
                        $rows[] = explode("\t", $line);
                    } else {
                        $rows[] = str_getcsv($line, ',');
                    }
                }
            } else {
                // خواندن فایل Excel
                $reader = new XlsxReader();
                $reader->setReadDataOnly(true);
                $spreadsheet = $reader->load($this->file->getRealPath());
                $sheet = $spreadsheet->getActiveSheet();
                $rows = $sheet->toArray();
            }

            if (count($rows) <= 2) {
                throw new \Exception("فایل خالی است یا فقط هدر دارد.");
            }

            if (count($rows) > 2001) { // +1 برای سطر metadata
                throw new \Exception("تعداد ردیف‌ها بیش از حد مجاز (2000) است.");
            }

            // ═══════════════════════════════════════════════════════════
            // Parse کردن هدرها و متادیتا
            // ═══════════════════════════════════════════════════════════
            $headers = array_shift($rows); // سطر اول: هدرها
            $metadata = array_shift($rows); // سطر دوم: متادیتا (T0|PT1|CP2)
            
            $headerMap = [];
            $priceColumns = []; // فرمت: [colIndex] => ['tier_index' => 0, 'price_type_id' => 1, 'check_period_id' => 2]

            Log::info('Fast Pricing Matrix - Headers detected:', ['headers' => $headers]);
            Log::info('Fast Pricing Matrix - Metadata detected:', ['metadata' => $metadata]);
            
            foreach ($headers as $index => $header) {
                if ($header === null || trim($header) === '') continue;
                $header = trim($header);

                // ستون‌های ثابت
                if (str_contains($header, 'SKU')) {
                    $headerMap['sku'] = $index;
                } elseif (str_contains($header, 'نام کالا')) {
                    $headerMap['product_name'] = $index;
                } elseif (str_contains($header, 'تیراژ')) {
                    $headerMap['tier_display'] = $index;
                } elseif (str_contains($header, 'وضعیت')) {
                    $headerMap['status'] = $index;
                } elseif (str_contains($header, 'موجودی')) {
                    $headerMap['stock_quantity'] = $index;
                } elseif (str_contains($header, 'تعداد')) {
                    $headerMap['min_order_quantity'] = $index;
                } elseif (str_contains($header, 'شناسه کالا')) {
                    $headerMap['product_id'] = $index;
                } else {
                    // ستون‌های قیمت: parse متادیتا
                    $meta = $metadata[$index] ?? null;
                    if ($meta && preg_match('/T(\d+)\|PT(\d+)\|CP(\d+)/', $meta, $matches)) {
                        $priceColumns[$index] = [
                            'tier_index' => (int)$matches[1],
                            'price_type_id' => (int)$matches[2],
                            'check_period_id' => (int)$matches[3] === 0 ? null : (int)$matches[3],
                            'header_text' => $header,
                        ];
                        Log::info("Price column detected at index $index", $priceColumns[$index]);
                    }
                }
            }

            Log::info('Fast Pricing Matrix - Parsed structure:', [
                'headerMap' => $headerMap,
                'priceColumnsCount' => count($priceColumns)
            ]);

            if (!isset($headerMap['product_id'])) {
                throw new \Exception("ستون 'شناسه کالا' یافت نشد. لطفاً از قالب استاندارد سیستم استفاده کنید.");
            }

            // ═══════════════════════════════════════════════════════════
            // پردازش ردیف‌های داده
            // ═══════════════════════════════════════════════════════════
            $validItemsCount = 0;

            foreach ($rows as $rowIndex => $row) {
                $actualRowNumber = $rowIndex + 3; // +1 برای هدر، +1 برای metadata، +1 برای zero-index

                // بررسی خالی بودن ردیف
                $isEmptyRow = true;
                foreach ($row as $cell) {
                    if ($cell !== null && trim((string)$cell) !== '') {
                        $isEmptyRow = false;
                        break;
                    }
                }
                if ($isEmptyRow) continue;

                $rowErrors = [];
                $encodedId = $row[$headerMap['product_id']] ?? null;
                $productName = isset($headerMap['product_name']) ? ($row[$headerMap['product_name']] ?? 'نامشخص') : 'نامشخص';

                if (empty($encodedId)) {
                    $rowErrors[] = "ستون شناسه کالا خالی است.";
                    $this->errorRows[] = [
                        'row' => $actualRowNumber,
                        'product_name' => $productName,
                        'errors' => $rowErrors,
                    ];
                    continue;
                }

                // رمزگشایی با SecureIdEncoder
                try {
                    $productSupplierId = SecureIdEncoder::decode($encodedId);
                } catch (\InvalidArgumentException $e) {
                    $rowErrors[] = "شناسه کالا نامعتبر یا دستکاری شده: " . $e->getMessage();
                    $this->errorRows[] = [
                        'row' => $actualRowNumber,
                        'product_name' => $productName,
                        'errors' => $rowErrors,
                    ];
                    continue;
                }

                // بارگذاری ProductSupplier با قیمت‌های موجود
                $productSupplier = ProductSupplier::where('supplier_id', $supplier->id)
                    ->where('id', $productSupplierId)
                    ->with(['product', 'prices.priceType', 'prices.checkPeriod'])
                    ->first();

                if (!$productSupplier) {
                    $rowErrors[] = "کالای مورد نظر برای شما یافت نشد.";
                    $this->errorRows[] = [
                        'row' => $actualRowNumber,
                        'product_name' => $productName,
                        'errors' => $rowErrors,
                    ];
                    continue;
                }

                // ═══════════════════════════════════════════════════════════
                // ساخت نقشه قیمت‌های قدیم (به فرمت ماتریس)
                // ═══════════════════════════════════════════════════════════
                $oldPricesMatrix = [];
                foreach ($productSupplier->prices as $priceObj) {
                    $key = sprintf(
                        'T%d|PT%d|CP%d',
                        $priceObj->tier_index,
                        $priceObj->price_type_id,
                        $priceObj->check_period_id ?? 0
                    );
                    $oldPricesMatrix[$key] = [
                        'price' => (int)$priceObj->price,
                        'tier_index' => $priceObj->tier_index,
                        'price_type_id' => $priceObj->price_type_id,
                        'check_period_id' => $priceObj->check_period_id,
                    ];
                }

                // ═══════════════════════════════════════════════════════════
                // خواندن قیمت‌های جدید از فایل
                // ═══════════════════════════════════════════════════════════
                
                // ✅ دریافت تیراژهای فعال برای این ProductSupplier
                $tierEnabled = $productSupplier->tier_enabled ?? [];
                $product = $productSupplier->product;
                $quantityTiers = $product->quantity_tiers ?? [];
                
                // فیلتر تیراژهای فعال
                $activeTiers = [];
                if (empty($tierEnabled)) {
                    // اگر تنظیمات نباشد، همه فعال
                    $activeTiers = array_keys($quantityTiers);
                } else {
                    foreach ($tierEnabled as $tier => $enabled) {
                        if ($enabled) {
                            $activeTiers[] = (int)$tier;
                        }
                    }
                }
                
                $newPricesMatrix = [];
                foreach ($priceColumns as $colIndex => $priceInfo) {
                    $cellValue = $row[$colIndex] ?? null;
                    
                    if ($cellValue !== null && trim((string)$cellValue) !== '') {
                        $cleanedVal = preg_replace('/[^\d]/', '', (string)$cellValue);
                        
                        if (is_numeric($cleanedVal) && $cleanedVal > 0) {
                            // ✅ بررسی اینکه این تیراژ فعال است یا نه
                            $tierIndex = $priceInfo['tier_index'];
                            if (!in_array($tierIndex, $activeTiers)) {
                                $tierName = $quantityTiers[$phTierIndex]['name'] ?? "تیراژ " . ($phTierIndex + 1);
                                $rowErrors[] = "تیراژ '{$tierName}' برای این محصول غیرفعال است. لطفاً ابتدا آن را در تنظیمات محصول فعال کنید.";
                                continue;
                            }
                            
                            $key = sprintf(
                                'T%d|PT%d|CP%d',
                                $priceInfo['tier_index'],
                                $priceInfo['price_type_id'],
                                $priceInfo['check_period_id'] ?? 0
                            );
                            
                            $newPricesMatrix[$key] = [
                                'price' => (int)$cleanedVal,
                                'tier_index' => $priceInfo['tier_index'],
                                'price_type_id' => $priceInfo['price_type_id'],
                                'check_period_id' => $priceInfo['check_period_id'],
                            ];
                        } elseif ($cleanedVal !== '') {
                            $rowErrors[] = "فرمت قیمت نامعتبر در ستون '{$priceInfo['header_text']}'.";
                        }
                    }
                }

                // ═══════════════════════════════════════════════════════════
                // خواندن فیلدهای دیگر (موجودی، تعداد، وضعیت)
                // ═══════════════════════════════════════════════════════════
                $oldStock = $productSupplier->stock_quantity;
                $newStock = null;
                if (isset($headerMap['stock_quantity'])) {
                    $stockVal = $row[$headerMap['stock_quantity']] ?? null;
                    if ($stockVal !== null && trim((string)$stockVal) !== '') {
                        $cleanedStock = preg_replace('/[^\d]/', '', (string)$stockVal);
                        if (is_numeric($cleanedStock)) {
                            $newStock = (int)$cleanedStock;
                        } else {
                            $rowErrors[] = "فرمت موجودی نامعتبر است.";
                        }
                    }
                }

                $newMinOrderQuantity = null;
                if (isset($headerMap['min_order_quantity'])) {
                    $quantityVal = $row[$headerMap['min_order_quantity']] ?? null;
                    if ($quantityVal !== null && trim((string)$quantityVal) !== '') {
                        $cleanedQuantity = preg_replace('/[^\d]/', '', (string)$quantityVal);
                        if (is_numeric($cleanedQuantity)) {
                            $newMinOrderQuantity = (int)$cleanedQuantity;
                        } else {
                            $rowErrors[] = "فرمت تعداد حداقل نامعتبر است.";
                        }
                    }
                }

                $newIsActive = null;
                if (isset($headerMap['status'])) {
                    $statusVal = $row[$headerMap['status']] ?? null;
                    if ($statusVal !== null && trim((string)$statusVal) !== '') {
                        $statusStr = trim((string)$statusVal);
                        if ($statusStr === '1' || $statusStr === 'فعال' || strtolower($statusStr) === 'true') {
                            $newIsActive = true;
                        } else {
                            $newIsActive = false;
                        }
                    }
                }

                // بررسی اینکه حداقل یک تغییر وجود داشته باشد
                if (empty($newPricesMatrix) && $newStock === null && $newMinOrderQuantity === null && $newIsActive === null) {
                    $rowErrors[] = "هیچ قیمت، موجودی، تعداد یا وضعیتی برای بروزرسانی وارد نشده است.";
                }

                if (!empty($rowErrors)) {
                    $this->errorRows[] = [
                        'row' => $actualRowNumber,
                        'product_name' => $productSupplier->product->title ?? $productName,
                        'errors' => $rowErrors,
                    ];
                    continue;
                }

                // ایجاد PriceUpdateItem با فرمت ماتریس جدید
                PriceUpdateItem::create([
                    'price_update_batch_id' => $batch->id,
                    'product_supplier_id' => $productSupplier->id,
                    'old_prices' => $oldPricesMatrix,
                    'new_prices' => $newPricesMatrix,
                    'old_stock' => $oldStock,
                    'new_stock' => $newStock,
                    'old_min_order_quantity' => $productSupplier->min_order_quantity,
                    'new_min_order_quantity' => $newMinOrderQuantity,
                    'old_is_active' => $productSupplier->is_active,
                    'new_is_active' => $newIsActive,
                    'status' => 'valid',
                    'error_message' => null,
                ]);

                $validItemsCount++;
            }

            if ($validItemsCount === 0 && empty($this->errorRows)) {
                throw new \Exception("هیچ داده معتبری در فایل یافت نشد.");
            }

            DB::commit();

            if (!empty($this->errorRows)) {
                $this->successfulRows = $validItemsCount;
                $this->processedBatchId = $batch->id;
                $this->isProcessing = false;
                return;
            }

            return redirect()->route('supplier.fast-pricing.review', ['batchId' => $batch->id]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Fast Pricing Matrix Import Error: ' . $e->getMessage(), [
                'file' => $this->file ? $this->file->getClientOriginalName() : 'no file',
                'trace' => $e->getTraceAsString()
            ]);
            $this->addError('file', 'خطا در پردازش فایل: ' . $e->getMessage());
            $this->isProcessing = false;
        }
    }

    /**
     * دانلود فایل قیمت‌ها برای یک تیراژ خاص
     * 
     * @param int|null $tierIndex اگر null باشد، همه تیراژها در یک فایل
     */
    /**
     * دانلود فایل قیمت‌ها برای یک تیراژ خاص
     * 
     * @param int|null $tierIndex اگر null باشد، همه تیراژها در یک فایل
     */
    public function downloadTemplate($tierIndex = null)
    {
        $supplier = Auth::user()->supplier;
        if (!$supplier) {
            abort(403, 'تامین کننده یافت نشد.');
        }

        // دریافت تمامی کالاها با روابط
        $productSuppliersQuery = ProductSupplier::where('supplier_id', $supplier->id)
            ->with([
                'product',
                'prices.priceType',
                'prices.checkPeriod',
                'supplier' // ✅ برای دسترسی به price_rounding_type
            ]);

        // ═══════════════════════════════════════════════════════════
        // فیلتر کردن محصولات بر اساس تیراژ
        // ═══════════════════════════════════════════════════════════
        if ($tierIndex !== null) {
            // فقط محصولاتی که این تیراژ رو دارن
            $productSuppliersQuery->whereHas('product', function($q) use ($tierIndex) {
                $q->whereNotNull('quantity_tiers')
                  ->whereRaw("JSON_LENGTH(quantity_tiers) > ?", [$tierIndex]);
            });
        }

        $productSuppliers = $productSuppliersQuery->get();

        if ($productSuppliers->isEmpty()) {
            abort(404, 'هیچ محصولی برای این تیراژ یافت نشد.');
        }

        // دریافت انواع قیمت و دوره‌های چک فعال
        $priceTypes = \App\Models\PriceType::active()->ordered()->get();
        
        // ✅ دریافت دوره‌های چکی فعال (عمومی + اختصاصی دسته‌بندی)
        // 1. دوره‌های عمومی که برای supplier فعال شده‌اند
        $generalCheckPeriodIds = \DB::table('supplier_check_period')
            ->where('supplier_id', $supplier->id)
            ->where('is_active', true)
            ->pluck('check_period_id')
            ->toArray();
        
        // 2. دوره‌های اختصاصی دسته‌بندی که برای این supplier فعال شده‌اند
        $categoryCheckPeriodIds = \DB::table('category_check_period')
            ->where('supplier_id', $supplier->id)
            ->where('is_active', true)
            ->distinct()
            ->pluck('check_period_id')
            ->toArray();
        
        // 3. ترکیب دو لیست (unique)
        $allActiveCheckPeriodIds = array_unique(array_merge($generalCheckPeriodIds, $categoryCheckPeriodIds));
        
        // 4. دریافت اطلاعات کامل دوره‌های فعال
        $checkPeriods = \App\Models\CheckPeriod::whereIn('id', $allActiveCheckPeriodIds)
            ->with(['suppliers' => function($query) use ($supplier) {
                $query->where('supplier_id', $supplier->id);
            }])
            ->active()
            ->ordered()
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // راست‌چین کردن شیت
        $sheet->setRightToLeft(true);

        // ═══════════════════════════════════════════════════════════
        // ساخت هدرها
        // ═══════════════════════════════════════════════════════════
        $headers = [
            'SKU اختصاصی',
            'نام کالا',
            'تیراژ (فقط نمایش)',
            'تعداد حداقل',
            'وضعیت (1/0)',
            'موجودی',
        ];

        // ساخت هدرهای قیمت بر اساس تیراژ انتخابی
        $priceHeaders = [];
        
        // ✅ دریافت اجتماع تیراژهای همه محصولات
        // هر محصول ممکن است تیراژهای متفاوتی داشته باشد
        $allPossibleTiers = [];
        foreach ($productSuppliers as $ps) {
            $product = $ps->product;
            if (!$product) continue;
            
            $tierEnabled = $ps->tier_enabled ?? [];
            $quantityTiers = $product->quantity_tiers ?? [];
            
            // فیلتر تیراژهای فعال برای این محصول
            $activeTiers = [];
            if (empty($tierEnabled)) {
                $activeTiers = array_keys($quantityTiers);
            } else {
                foreach ($tierEnabled as $tier => $enabled) {
                    if ($enabled) {
                        $activeTiers[] = (int)$tier;
                    }
                }
            }
            
            // اضافه کردن به اجتماع تیراژها
            foreach ($activeTiers as $tier) {
                $allPossibleTiers[$tier] = true;
            }
        }
        
        // تعیین تیراژهایی که باید در فایل باشند
        $tiersToInclude = [];
        if ($tierIndex !== null) {
            // فقط تیراژ انتخابی
            $tiersToInclude = [$tierIndex];
        } else {
            // همه تیراژهای موجود (مرتب شده)
            $tiersToInclude = array_keys($allPossibleTiers);
            sort($tiersToInclude);
        }
        
        // ✅ دریافت نام تیراژها از اولین محصولی که آن تیراژ را دارد
        $tierNames = [];
        foreach ($tiersToInclude as $tierIdx) {
            foreach ($productSuppliers as $ps) {
                $product = $ps->product;
                if (!$product) continue;
                
                $quantityTiers = $product->quantity_tiers ?? [];
                if (isset($quantityTiers[$tierIdx]['name'])) {
                    $tierNames[$tierIdx] = $quantityTiers[$tierIdx];
                    break;
                }
            }
            
            // اگر نامی پیدا نشد، نام پیش‌فرض
            if (!isset($tierNames[$tierIdx])) {
                $tierNames[$tierIdx] = [
                    'name' => "تیراژ " . ($tierIdx + 1),
                    'min_quantity' => null,
                    'max_quantity' => null,
                ];
            }
        }
        
        // مقدار quantityTiers برای استفاده در بخش‌های دیگر
        $quantityTiers = $tierNames;
        
        // ═══════════════════════════════════════════════════════════
        // ✅ ساخت هدرهای قیمت: ابتدا همه نقدی‌ها، سپس همه چکی‌ها به ترتیب روز
        // ═══════════════════════════════════════════════════════════
        
        // مرتب کردن دوره‌های چک بر اساس تعداد روز (کمترین به بیشترین)
        $sortedCheckPeriods = $checkPeriods->sortBy('days');
        
        // پیدا کردن price type نقدی و چکی
        $cashPriceType = $priceTypes->firstWhere('code', 'cash');
        $checkPriceType = $priceTypes->firstWhere('code', 'check');
        
        // ✅ شناسایی تیراژ مرجع (برای قرار دادن در اولین ستون)
        // تیراژ مرجع معمولاً تیراژ 1 (index 1) است = عمده‌فروشی کوچک / کارتن
        $referenceTierIndex = 1; // پیش‌فرض
        
        // بررسی تنظیمات محصول اول برای یافتن تیراژ مرجع
        $firstProductSupplier = $productSuppliers->first();
        if ($firstProductSupplier) {
            $tierSettings = \App\Models\SupplierTierPercentage::where('supplier_id', $supplier->id)
                ->where('product_id', $firstProductSupplier->product_id)
                ->get();
            
            if ($tierSettings->isNotEmpty()) {
                $referenceSetting = $tierSettings->firstWhere('is_reference', true);
                if ($referenceSetting) {
                    $referenceTierIndex = $referenceSetting->tier_index;
                }
            }
        }
        
        // مرتب‌سازی تیراژها: اول مرجع، بعد بقیه به ترتیب index
        $sortedTiers = [];
        
        // اول تیراژ مرجع را اضافه کن
        if (in_array($referenceTierIndex, $tiersToInclude)) {
            $sortedTiers[] = $referenceTierIndex;
        }
        
        // سپس بقیه تیراژها به ترتیب index (0، 1، 2، 3، 4، ...)
        sort($tiersToInclude); // مرتب‌سازی عددی
        foreach ($tiersToInclude as $tier) {
            if ($tier !== $referenceTierIndex) {
                $sortedTiers[] = $tier;
            }
        }
        
        // 1️⃣ ابتدا همه نقدی‌ها (با تیراژ مرجع در اول)
        if ($cashPriceType) {
            foreach ($sortedTiers as $tier) {
                $tierName = $quantityTiers[$tier]['name'] ?? "T{$tier}";
                $headerText = sprintf('%s - %s', $tierName, $cashPriceType->name);
                $priceHeaders[] = [
                    'text' => $headerText,
                    'tier_index' => $tier,
                    'price_type_id' => $cashPriceType->id,
                    'check_period_id' => null,
                ];
            }
        }
        
        // 2️⃣ سپس همه چکی‌ها به ترتیب دوره (روز کم به زیاد) - با تیراژ مرجع در اول
        if ($checkPriceType && $sortedCheckPeriods->isNotEmpty()) {
            foreach ($sortedCheckPeriods as $checkPeriod) {
                foreach ($sortedTiers as $tier) {
                    $tierName = $quantityTiers[$tier]['name'] ?? "T{$tier}";
                    $headerText = sprintf('%s - %s %s', $tierName, $checkPriceType->name, $checkPeriod->display_label);
                    $priceHeaders[] = [
                        'text' => $headerText,
                        'tier_index' => $tier,
                        'price_type_id' => $checkPriceType->id,
                        'check_period_id' => $checkPeriod->id,
                    ];
                }
            }
        }

        // اضافه کردن هدرهای قیمت
        foreach ($priceHeaders as $ph) {
            $headers[] = $ph['text'];
        }

        // شناسه کالا در انتها
        $headers[] = 'شناسه کالا (تغییر ندهید)';

        // ═══════════════════════════════════════════════════════════
        // نوشتن هدرها در سطر اول
        // ═══════════════════════════════════════════════════════════
        $colIndex = 1;
        foreach ($headers as $header) {
            $colLetter = Coordinate::stringFromColumnIndex($colIndex);
            $sheet->setCellValue($colLetter . '1', $header);
            
            // استایل هدر
            $sheet->getStyle($colLetter . '1')->applyFromArray([
                'font' => ['bold' => true, 'size' => 11],
                'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4F46E5'], // indigo-600
                ],
                'font' => ['color' => ['rgb' => 'FFFFFF'], 'bold' => true],
            ]);
            
            $colIndex++;
        }

        // ═══════════════════════════════════════════════════════════
        // نوشتن سطر metadata (سطر دوم)
        // ═══════════════════════════════════════════════════════════
        $metadataRow = [];
        
        // ستون‌های ثابت: خالی
        $metadataRow[] = ''; // SKU
        $metadataRow[] = ''; // نام کالا
        $metadataRow[] = ''; // تیراژ
        $metadataRow[] = ''; // تعداد حداقل
        $metadataRow[] = ''; // وضعیت
        $metadataRow[] = ''; // موجودی
        
        // ستون‌های قیمت: فرمت T{tier}|PT{priceType}|CP{checkPeriod}
        foreach ($priceHeaders as $ph) {
            $metadata = sprintf(
                'T%d|PT%d|CP%d',
                $ph['tier_index'],
                $ph['price_type_id'],
                $ph['check_period_id'] ?? 0
            );
            $metadataRow[] = $metadata;
        }
        
        $metadataRow[] = ''; // شناسه کالا
        
        // نوشتن metadata در سطر 2
        $colIndex = 1;
        foreach ($metadataRow as $meta) {
            $colLetter = Coordinate::stringFromColumnIndex($colIndex);
            $sheet->setCellValue($colLetter . '2', $meta);
            
            // استایل metadata (خاکستری کم‌رنگ)
            $sheet->getStyle($colLetter . '2')->applyFromArray([
                'font' => ['size' => 8, 'color' => ['rgb' => '9CA3AF'], 'italic' => true],
                'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'F3F4F6'], // gray-100
                ],
            ]);
            
            $colIndex++;
        }

        // ═══════════════════════════════════════════════════════════
        // پر کردن داده‌های محصولات
        // ═══════════════════════════════════════════════════════════
        $rowIndex = 3; // سطر سوم (بعد از هدر و metadata)
        $productsWritten = 0;
        
        foreach ($productSuppliers as $ps) {
            $product = $ps->product;
            if (!$product) continue;

            // ✅ دریافت تیراژهای فعال برای این supplier
            $tierEnabled = $ps->tier_enabled ?? [];
            $quantityTiers = $product->quantity_tiers ?? [];
            
            // فیلتر تیراژهای فعال
            $activeTiers = [];
            if (empty($tierEnabled)) {
                // اگر تنظیمات نباشد، همه فعال
                $activeTiers = array_keys($quantityTiers);
            } else {
                foreach ($tierEnabled as $tier => $enabled) {
                    if ($enabled) {
                        $activeTiers[] = $tier;
                    }
                }
            }
            
            // اگر تیراژ خاص انتخاب شده و این محصول آن تیراژ را ندارد یا غیرفعال است
            if ($tierIndex !== null) {
                if (!in_array($tierIndex, $activeTiers)) {
                    continue; // این محصول این تیراژ را ندارد یا غیرفعال است
                }
            }

            // کدگذاری شناسه
            $encodedId = SecureIdEncoder::encode($ps->id);

            // ستون‌های ثابت
            $sheet->setCellValue('A' . $rowIndex, $ps->sku ?? '');
            $sheet->setCellValue('B' . $rowIndex, $product->title ?? 'بدون نام');
            
            // ستون تیراژ (فقط نمایش)
            $tierDisplay = '';
            if ($tierIndex !== null && isset($quantityTiers[$tierIndex])) {
                $currentTier = $quantityTiers[$tierIndex];
                $tierName = $currentTier['name'] ?? "تیراژ " . ($tierIndex + 1);
                $minQty = $currentTier['min_quantity'] ?? null;
                $maxQty = $currentTier['max_quantity'] ?? null;
                if ($minQty && $maxQty) {
                    $tierDisplay = "{$tierName} ({$minQty}-{$maxQty})";
                } elseif ($minQty) {
                    $tierDisplay = "{$tierName} ({$minQty}+)";
                } else {
                    $tierDisplay = $tierName;
                }
            } else {
                // نمایش تعداد تیراژهای فعال
                $tierDisplay = count($activeTiers) . ' تیراژ فعال';
            }
            
            $sheet->setCellValue('C' . $rowIndex, $tierDisplay);
            $sheet->getStyle('C' . $rowIndex)->applyFromArray([
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'F3F4F6'], // gray-100
                ],
                'font' => ['color' => ['rgb' => '6B7280']], // gray-500
            ]);
            
            $sheet->setCellValue('D' . $rowIndex, $ps->min_order_quantity ?? 1);
            $sheet->setCellValue('E' . $rowIndex, $ps->is_active ? 1 : 0);
            $sheet->setCellValue('F' . $rowIndex, $ps->stock_quantity ?? 0);
            $sheet->getStyle('F' . $rowIndex)->getNumberFormat()->setFormatCode('#,##0');

            // ساخت نقشه قیمت‌های موجود
            $priceMap = [];
            foreach ($ps->prices as $price) {
                // ✅ فقط قیمت‌های تیراژهای فعال
                if (!in_array($price->tier_index, $activeTiers)) {
                    continue;
                }
                
                // اگر تیراژ خاص انتخاب شده
                if ($tierIndex !== null && $price->tier_index !== $tierIndex) {
                    continue;
                }
                
                $key = sprintf(
                    'T%d|PT%d|CP%d',
                    $price->tier_index,
                    $price->price_type_id,
                    $price->check_period_id ?? 0
                );
                $priceMap[$key] = $price->price;
            }

            // پر کردن ستون‌های قیمت
            $colIndex = 7; // شروع از ستون G
            
            // ✅ دریافت تنظیمات auto-calculate
            $autoCalculate = $ps->auto_calculate_tiers ?? false;
            $priceRounding = $ps->supplier->price_rounding_type ?? 'nearest_1';
            
            // ✅ محاسبه درصدهای مؤثر با اولویت‌بندی
            // اولویت: 1. درصد اختصاصی محصول > 2. دسته‌بندی > 3. پیشفرض
            $productTierPercentages = $ps->tier_percentages;
            if (is_string($productTierPercentages)) {
                $productTierPercentages = json_decode($productTierPercentages, true) ?? [];
            }
            
            $tierPercentages = $this->getEffectiveTierPercentages(
                $ps->supplier_id,
                $ps->product->category_id ?? null,
                $productTierPercentages ?? []
            );
            
            // ✅ شناسایی ستون‌های نقدی (برای مرجع فرمول)
            $cashColumnMap = []; // [tier_index] => column_letter
            
            foreach ($priceHeaders as $ph) {
                $colLetter = Coordinate::stringFromColumnIndex($colIndex);
                $key = sprintf(
                    'T%d|PT%d|CP%d',
                    $ph['tier_index'],
                    $ph['price_type_id'],
                    $ph['check_period_id'] ?? 0
                );
                $priceValue = $priceMap[$key] ?? '';
                
                // ✅ اگر قیمت نقدی است، ستون را ذخیره کن
                $priceType = $priceTypes->firstWhere('id', $ph['price_type_id']);
                if ($priceType && $priceType->code === 'cash') {
                    $cashColumnMap[$ph['tier_index']] = $colLetter;
                }
                
                $sheet->setCellValue($colLetter . $rowIndex, $priceValue);
                if ($priceValue !== '') {
                    $sheet->getStyle($colLetter . $rowIndex)->getNumberFormat()->setFormatCode('#,##0');
                }
                $colIndex++;
            }
            
            // ✅ حالا دوباره loop کنیم تا قیمت‌های خودکار را فرمول بزنیم
            $colIndex = 7;
            
            // ✅ شناسایی تیراژ مرجع از جدول supplier_tier_percentages
            $referenceTierIndex = 1; // پیش‌فرض: تیراژ 1 (کارتن)
            $tierSettings = \App\Models\SupplierTierPercentage::where('supplier_id', $ps->supplier_id)
                ->where('product_id', $ps->product_id)
                ->get();
            
            if ($tierSettings->isNotEmpty()) {
                $referenceSetting = $tierSettings->firstWhere('is_reference', true);
                if ($referenceSetting) {
                    $referenceTierIndex = $referenceSetting->tier_index;
                }
            }
            
            // ✅ دریافت ستون نقدی تیراژ مرجع از cashColumnMap
            $referenceCashColumn = $cashColumnMap[$referenceTierIndex] ?? null;
            
            // ✅ دریافت دوره‌های چکی فعال برای هر تیراژ (بررسی رکورد در دیتابیس)
            $enabledCheckPeriods = [];
            foreach ($ps->prices as $price) {
                if ($price->check_period_id !== null && $price->price > 0) {
                    $key = $price->tier_index . '_' . $price->check_period_id;
                    $enabledCheckPeriods[$key] = true;
                }
            }
            
            foreach ($priceHeaders as $ph) {
                $colLetter = Coordinate::stringFromColumnIndex($colIndex);
                $phTierIndex = $ph['tier_index'];
                $priceTypeId = $ph['price_type_id'];
                $checkPeriodId = $ph['check_period_id'];
                
                $priceType = $priceTypes->firstWhere('id', $priceTypeId);
                $isAutoCalculated = false;
                $isDisabled = false; // ✅ برای تیراژها/دوره‌های غیرفعال
                $formulaComment = '';
                
                // ✅ بررسی: آیا این تیراژ فعال است؟
                $isTierActive = in_array($phTierIndex, $activeTiers);
                
                // ✅ شرط 0: اگر تیراژ غیرفعال است
                if (!$isTierActive) {
                    $isDisabled = true;
                    $tierName = $quantityTiers[$phTierIndex]['name'] ?? "تیراژ " . ($phTierIndex + 1);
                    
                    // سلول را خالی بگذار و با رنگ خاکستری مشخص کن
                    $sheet->setCellValue($colLetter . $rowIndex, '');
                    $sheet->getStyle($colLetter . $rowIndex)->applyFromArray([
                        'fill' => [
                            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'F3F4F6'], // gray-100
                        ],
                        'font' => ['color' => ['rgb' => '9CA3AF'], 'italic' => true], // gray-400
                    ]);
                    
                    // کامنت توضیحی
                    $formulaComment = sprintf(
                        "⚠️ تیراژ غیرفعال\n\nاین تیراژ (%s) برای این محصول غیرفعال است.\n\nبرای فعال‌سازی، به صفحه ویرایش قیمت محصول مراجعه کنید.",
                        $tierName
                    );
                }
                // ✅ شرط 1: قیمت‌های نقدی غیر-مرجع (اگر auto_calculate فعال باشد)
                elseif ($autoCalculate && $priceType && $priceType->code === 'cash') {
                    // اگر این تیراژ، مرجع نیست → فرمول بزن
                    if ($phTierIndex !== $referenceTierIndex && $referenceCashColumn && isset($tierPercentages[$phTierIndex])) {
                        $tierPercentage = $tierPercentages[$phTierIndex];
                        
                        // ✅ فرمول: قیمت نقدی = قیمت مرجع × (1 + tier_percentage / 100)
                        $baseFormula = sprintf('%s%d*(1+%s/100)', $referenceCashColumn, $rowIndex, $tierPercentage);
                        
                        // رند کردن به عدد صحیح (بدون اعشار)
                        $formula = sprintf('=ROUND(%s, 0)', $baseFormula);
                        
                        $sheet->setCellValue($colLetter . $rowIndex, $formula);
                        $sheet->getStyle($colLetter . $rowIndex)->getNumberFormat()->setFormatCode('#,##0');
                        
                        $isAutoCalculated = true;
                        
                        $tierName = $quantityTiers[$phTierIndex]['name'] ?? "تیراژ " . ($phTierIndex + 1);
                        $referenceTierName = $quantityTiers[$referenceTierIndex]['name'] ?? "تیراژ " . ($referenceTierIndex + 1);
                        $sign = $tierPercentage >= 0 ? '+' : '';
                        
                        $formulaComment = sprintf(
                            "🔒 قیمت خودکار (قفل شده)\n\n📊 محاسبه:\n  • قیمت مرجع (%s)\n  • %s%s%%\n\n🎯 رند: عدد صحیح\n\n💡 فرمول: %s\n\n⚠️ این سلول قابل ویرایش نیست",
                            $referenceTierName,
                            $sign,
                            number_format($tierPercentage, 1),
                            $formula
                        );
                    }
                }
                
                // ✅ شرط 2: قیمت‌های چکی
                elseif ($priceType && $priceType->code === 'check' && $checkPeriodId) {
                    // ✅ بررسی: آیا این دوره چک برای این تیراژ فعال است؟
                    $checkKey = $phTierIndex . '_' . $checkPeriodId;
                    $isCheckPeriodEnabled = isset($enabledCheckPeriods[$checkKey]);
                    
                    if (!$isCheckPeriodEnabled) {
                        // ✅ این دوره چک برای این تیراژ غیرفعال است
                        $isDisabled = true;
                        $tierName = $quantityTiers[$phTierIndex]['name'] ?? "تیراژ " . ($phTierIndex + 1);
                        $checkPeriod = $checkPeriods->firstWhere('id', $checkPeriodId);
                        $periodName = $checkPeriod->display_label ?? 'نامشخص';
                        
                        // سلول را خالی بگذار و با رنگ خاکستری مشخص کن
                        $sheet->setCellValue($colLetter . $rowIndex, '');
                        $sheet->getStyle($colLetter . $rowIndex)->applyFromArray([
                            'fill' => [
                                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                                'startColor' => ['rgb' => 'F3F4F6'], // gray-100
                            ],
                            'font' => ['color' => ['rgb' => '9CA3AF'], 'italic' => true], // gray-400
                        ]);
                        
                        // کامنت توضیحی
                        $formulaComment = sprintf(
                            "⚠️ غیرفعال\n\nاین دوره چک (%s) برای این تیراژ (%s) فعال نیست.\n\nبرای فعال‌سازی، به صفحه ویرایش قیمت محصول مراجعه کنید.",
                            $periodName,
                            $tierName
                        );
                    } elseif ($autoCalculate && isset($cashColumnMap[$phTierIndex])) {
                        // ✅ دوره فعال است و auto_calculate روشن است → فرمول بزن
                        $cashCol = $cashColumnMap[$phTierIndex];
                        
                        // ✅ دریافت درصد اختصاصی این دوره چک از pivot
                        $checkPeriod = $checkPeriods->firstWhere('id', $checkPeriodId);
                        $checkPercentage = 0;
                        
                        if ($checkPeriod && $checkPeriod->suppliers->isNotEmpty()) {
                            $pivotData = $checkPeriod->suppliers->first()->pivot;
                            $checkPercentage = $pivotData->percentage_increase ?? 0;
                        }
                        
                        // ✅ فرمول: قیمت چک = قیمت نقدی همان تیراژ × (1 + درصد چک)
                        $baseFormula = sprintf('%s%d*(1+%s/100)', $cashCol, $rowIndex, $checkPercentage);
                        
                        // رند کردن به عدد صحیح (بدون اعشار)
                        $formula = sprintf('=ROUND(%s, 0)', $baseFormula);
                    
                    // اعمال فرمول و قفل
                    $sheet->setCellValue($colLetter . $rowIndex, $formula);
                    $sheet->getStyle($colLetter . $rowIndex)->getNumberFormat()->setFormatCode('#,##0');
                    
                    $isAutoCalculated = true;
                    
                    $periodName = $checkPeriod->display_label ?? '';
                    
                    $formulaComment = sprintf(
                        "🔒 قیمت خودکار (قفل شده)\n\n📊 محاسبه:\n  • قیمت نقدی این تیراژ\n  • + درصد دوره چک (%s): %s%%\n\n🎯 رند: عدد صحیح\n\n💡 فرمول: %s\n\n⚠️ این سلول قابل ویرایش نیست",
                        $periodName,
                        number_format($checkPercentage, 1),
                        $formula
                    );
                    }
                }
                
                // ✅ قفل کردن سلول‌های خودکار
                if ($isAutoCalculated) {
                    $sheet->getStyle($colLetter . $rowIndex)
                        ->getProtection()
                        ->setLocked(\PhpOffice\PhpSpreadsheet\Style\Protection::PROTECTION_PROTECTED);
                    
                    // رنگ پس‌زمینه متفاوت برای نشان دادن خودکار بودن
                    $sheet->getStyle($colLetter . $rowIndex)->applyFromArray([
                        'fill' => [
                            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'E0E7FF'], // indigo-100
                        ],
                        'font' => ['color' => ['rgb' => '4F46E5'], 'italic' => true], // indigo-600
                    ]);
                    
                    // اضافه کردن کامنت
                    if ($formulaComment) {
                        $sheet->getComment($colLetter . $rowIndex)
                            ->getText()
                            ->createTextRun($formulaComment);
                        $sheet->getComment($colLetter . $rowIndex)
                            ->setWidth('300pt')
                            ->setHeight('80pt');
                    }
                }
                
                // ✅ استایل سلول‌های غیرفعال (دوره چک غیرفعال برای تیراژ)
                if ($isDisabled) {
                    $sheet->getStyle($colLetter . $rowIndex)
                        ->getProtection()
                        ->setLocked(\PhpOffice\PhpSpreadsheet\Style\Protection::PROTECTION_PROTECTED);
                    
                    // اضافه کردن کامنت توضیحی
                    if ($formulaComment) {
                        $sheet->getComment($colLetter . $rowIndex)
                            ->getText()
                            ->createTextRun($formulaComment);
                        $sheet->getComment($colLetter . $rowIndex)
                            ->setWidth('300pt')
                            ->setHeight('60pt');
                    }
                }
                
                $colIndex++;
            }

            // شناسه کالا در ستون آخر
            $lastColLetter = Coordinate::stringFromColumnIndex($colIndex);
            $sheet->setCellValue($lastColLetter . $rowIndex, $encodedId);
            
            // استایل ستون شناسه (خاکستری و کوچک)
            $sheet->getStyle($lastColLetter . $rowIndex)->applyFromArray([
                'font' => ['size' => 8, 'color' => ['rgb' => '9CA3AF']],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'F9FAFB'],
                ],
            ]);

            $rowIndex++;
            $productsWritten++;
        }

        // ═══════════════════════════════════════════════════════════
        // تنظیمات نهایی شیت
        // ═══════════════════════════════════════════════════════════
        
        // تنظیم ارتفاع سطرهای اول و دوم
        $sheet->getRowDimension(1)->setRowHeight(40);
        $sheet->getRowDimension(2)->setRowHeight(20);

        // تنظیم عرض خودکار ستون‌ها
        $highestColumn = $sheet->getHighestColumn();
        $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);
        for ($i = 1; $i <= $highestColumnIndex; $i++) {
            $colLetter = Coordinate::stringFromColumnIndex($i);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        // Freeze هدر و metadata (دو سطر اول) و سه ستون اول
        $sheet->freezePane('D3');
        
        // ═══════════════════════════════════════════════════════════
        // Data Validation برای جلوگیری از ویرایش سلول‌های مهم
        // ═══════════════════════════════════════════════════════════
        
        // ✅ Data Validation برای سطر metadata (سطر 2)
        // جلوگیری از ویرایش با پیام خطا
        $metadataRange = 'A2:' . $lastColLetter . '2';
        $metadataValidation = $sheet->getCell('A2')->getDataValidation();
        $metadataValidation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_CUSTOM);
        $metadataValidation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
        $metadataValidation->setAllowBlank(false);
        $metadataValidation->setShowInputMessage(true);
        $metadataValidation->setShowErrorMessage(true);
        $metadataValidation->setErrorTitle('خطا');
        $metadataValidation->setError('این سطر حاوی اطلاعات فنی است و نباید تغییر کند. لطفاً این سلول را ویرایش نکنید.');
        $metadataValidation->setPromptTitle('هشدار');
        $metadataValidation->setPrompt('⚠️ این سطر حاوی metadata است. تغییر ندهید!');
        $metadataValidation->setFormula1('FALSE()'); // همیشه invalid
        
        // اعمال validation روی کل سطر metadata
        for ($i = 1; $i <= $highestColumnIndex; $i++) {
            $colLetter = Coordinate::stringFromColumnIndex($i);
            $sheet->setDataValidation($colLetter . '2', clone $metadataValidation);
        }
        
        // ✅ Data Validation برای ستون شناسه کالا (ستون آخر)
        if ($rowIndex > 3) {
            $idValidation = $sheet->getCell($lastColLetter . '3')->getDataValidation();
            $idValidation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_CUSTOM);
            $idValidation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
            $idValidation->setAllowBlank(false);
            $idValidation->setShowInputMessage(true);
            $idValidation->setShowErrorMessage(true);
            $idValidation->setErrorTitle('خطا');
            $idValidation->setError('این ستون حاوی شناسه رمزشده کالا است و نباید تغییر کند.');
            $idValidation->setPromptTitle('شناسه کالا');
            $idValidation->setPrompt('⚠️ این شناسه را تغییر ندهید!');
            $idValidation->setFormula1('FALSE()');
            
            // اعمال روی تمام سلول‌های ستون شناسه
            for ($r = 3; $r <= $rowIndex - 1; $r++) {
                $sheet->setDataValidation($lastColLetter . $r, clone $idValidation);
            }
        }
        
        // ✅ اضافه کردن کامنت هشدار برای سطر metadata
        $sheet->getComment('A2')
            ->setWidth('400pt')
            ->setHeight('60pt');
        $sheet->getComment('A2')
            ->getText()
            ->createTextRun("⚠️ هشدار مهم\n\nاین سطر حاوی اطلاعات فنی است که سیستم برای تشخیص ستون‌ها استفاده می‌کند.\n\n❌ لطفاً این سطر را تغییر ندهید\n❌ سلول‌های این سطر را حذف یا ویرایش نکنید\n\nدر غیر این صورت فایل قابل آپلود نخواهد بود.");
        
        // ✅ اضافه کردن کامنت برای ستون شناسه
        if ($rowIndex > 3) {
            $sheet->getComment($lastColLetter . '3')
                ->setWidth('300pt')
                ->setHeight('50pt');
            $sheet->getComment($lastColLetter . '3')
                ->getText()
                ->createTextRun("⚠️ شناسه کالا\n\nاین ستون حاوی شناسه رمزشده کالاست.\n\n❌ لطفاً این ستون را تغییر ندهید\n\nسیستم از این شناسه برای تشخیص کالاها استفاده می‌کند.");
        }

        // نام فایل
        $tierSuffix = $tierIndex !== null ? "_tier_{$tierIndex}" : "_all_tiers";
        $fileName = 'fast_pricing' . $tierSuffix . '_' . now()->format('Y-m-d_H-i') . '.xlsx';

        return new StreamedResponse(function () use ($spreadsheet) {
            $writer = new XlsxWriter($spreadsheet);
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * محاسبه درصدهای مؤثر تیراژ با اولویت‌بندی
     * 
     * اولویت:
     * 1. درصد اختصاصی محصول (edit-price)
     * 2. تنظیمات اختصاصی دسته‌بندی
     * 3. تنظیمات پیشفرض تیراژ
     * 
     * @param int $supplierId
     * @param int|null $categoryId
     * @param array $productTierPercentages
     * @return array [tier_index => percentage]
     */
    private function getEffectiveTierPercentages(int $supplierId, ?int $categoryId, array $productTierPercentages): array
    {
        $result = [];
        
        // دریافت تنظیمات پیشفرض
        $defaultDiscounts = \App\Models\SupplierTierDiscount::getAllForSupplier($supplierId)
            ->keyBy('tier_index');
        
        // برای هر تیراژ (0, 1, 2, 3)
        for ($tierIndex = 0; $tierIndex <= 3; $tierIndex++) {
            // 1. درصد اختصاصی محصول (اگر وجود دارد)
            if (isset($productTierPercentages[$tierIndex]) && $productTierPercentages[$tierIndex] !== null) {
                $result[$tierIndex] = (float) $productTierPercentages[$tierIndex];
                continue;
            }
            
            // 2. درصد اختصاصی دسته‌بندی
            if ($categoryId) {
                $categoryDiscount = DB::table('category_tier_discount')
                    ->where('supplier_id', $supplierId)
                    ->where('category_id', $categoryId)
                    ->where('tier_index', $tierIndex)
                    ->where('is_active', true)
                    ->value('percentage_change');
                
                if ($categoryDiscount !== null) {
                    $result[$tierIndex] = (float) $categoryDiscount;
                    continue;
                }
            }
            
            // 3. درصد پیشفرض
            if (isset($defaultDiscounts[$tierIndex])) {
                $result[$tierIndex] = (float) $defaultDiscounts[$tierIndex]->percentage_change;
            } else {
                $result[$tierIndex] = 0; // پیش‌فرض نهایی
            }
        }
        
        return $result;
    }
    
    public function render()
    {
        return view('livewire.supplier.fast-pricing.import');
    }
}
