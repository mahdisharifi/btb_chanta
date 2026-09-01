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
                if (str_contains($header, 'شناسه کالا')) {
                    $headerMap['product_id'] = $index;
                } elseif (str_contains($header, 'SKU')) {
                    $headerMap['sku'] = $index;
                } elseif (str_contains($header, 'نام کالا')) {
                    $headerMap['product_name'] = $index;
                } elseif (str_contains($header, 'وضعیت')) {
                    $headerMap['status'] = $index;
                } elseif (str_contains($header, 'موجودی')) {
                    $headerMap['stock_quantity'] = $index;
                } elseif (str_contains($header, 'تعداد')) {
                    $headerMap['min_order_quantity'] = $index;
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
                $newPricesMatrix = [];
                foreach ($priceColumns as $colIndex => $priceInfo) {
                    $cellValue = $row[$colIndex] ?? null;
                    
                    if ($cellValue !== null && trim((string)$cellValue) !== '') {
                        $cleanedVal = preg_replace('/[^\d]/', '', (string)$cellValue);
                        
                        if (is_numeric($cleanedVal) && $cleanedVal > 0) {
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
                'prices.checkPeriod'
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
        $checkPeriods = \App\Models\CheckPeriod::where(function($q) use ($supplier) {
            $q->whereNull('supplier_id')->orWhere('supplier_id', $supplier->id);
        })->active()->ordered()->get();

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
        
        foreach ($priceTypes as $priceType) {
            if ($priceType->code === 'warranty') {
                continue; // گارانتی رو نمایش نمی‌دیم
            }
            
            if ($priceType->code === 'check' && $checkPeriods->isNotEmpty()) {
                // برای چکی، هر دوره چک یک ستون
                foreach ($checkPeriods as $checkPeriod) {
                    $headerText = sprintf('%s %s', $priceType->name, $checkPeriod->display_label);
                    $priceHeaders[] = [
                        'text' => $headerText,
                        'price_type_id' => $priceType->id,
                        'check_period_id' => $checkPeriod->id,
                    ];
                }
            } else {
                // نقدی
                $priceHeaders[] = [
                    'text' => $priceType->name,
                    'price_type_id' => $priceType->id,
                    'check_period_id' => null,
                ];
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
        // پر کردن داده‌های محصولات
        // ═══════════════════════════════════════════════════════════
        $rowIndex = 2;
        
        foreach ($productSuppliers as $ps) {
            $product = $ps->product;
            if (!$product) continue;

            // اطلاعات تیراژ
            $quantityTiers = $product->quantity_tiers ?? [];
            
            // اگر تیراژ خاص انتخاب شده
            if ($tierIndex !== null) {
                if (!isset($quantityTiers[$tierIndex])) {
                    continue; // این محصول این تیراژ رو نداره
                }
                $currentTier = $quantityTiers[$tierIndex];
                $tierName = $currentTier['name'] ?? "تیراژ " . ($tierIndex + 1);
                
                // محدوده تیراژ برای نمایش
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
                // فایل جامع همه تیراژها
                $tierDisplay = 'همه تیراژها';
            }

            // کدگذاری شناسه با متد جدید (کوتاه‌تر)
            $encodedId = SecureIdEncoder::encode($ps->id);
            
            // اگر تیراژ خاص انتخاب شده، metadata رو اضافه کن
            if ($tierIndex !== null) {
                $encodedId = "T{$tierIndex}:" . $encodedId;
            }

            // ستون‌های ثابت
            $sheet->setCellValue('A' . $rowIndex, $ps->sku ?? '');
            $sheet->setCellValue('B' . $rowIndex, $product->title ?? 'بدون نام');
            
            // ستون تیراژ (فقط نمایش - غیرقابل ویرایش)
            $sheet->setCellValue('C' . $rowIndex, $tierDisplay);
            $sheet->getStyle('C' . $rowIndex)->applyFromArray([
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'F3F4F6'], // gray-100
                ],
                'font' => ['color' => ['rgb' => '6B7280']], // gray-500
            ]);
            $sheet->getStyle('C' . $rowIndex)
                ->getProtection()
                ->setLocked(\PhpOffice\PhpSpreadsheet\Style\Protection::PROTECTION_PROTECTED);
            
            $sheet->setCellValue('D' . $rowIndex, $ps->min_order_quantity ?? 1);
            $sheet->setCellValue('E' . $rowIndex, $ps->is_active ? 1 : 0);
            $sheet->setCellValue('F' . $rowIndex, $ps->stock_quantity ?? 0);
            $sheet->getStyle('F' . $rowIndex)->getNumberFormat()->setFormatCode('#,##0');

            // ساخت نقشه قیمت‌های موجود
            $priceMap = [];
            foreach ($ps->prices as $price) {
                // فقط قیمت‌های مربوط به تیراژ انتخابی
                if ($tierIndex !== null && $price->tier_index !== $tierIndex) {
                    continue;
                }
                
                $key = sprintf(
                    'PT%d|CP%d',
                    $price->price_type_id,
                    $price->check_period_id ?? 0
                );
                $priceMap[$key] = $price->price;
            }

            // پر کردن ستون‌های قیمت
            $colIndex = 7; // شروع از ستون G
            foreach ($priceHeaders as $ph) {
                $colLetter = Coordinate::stringFromColumnIndex($colIndex);
                $key = sprintf('PT%d|CP%d', $ph['price_type_id'], $ph['check_period_id'] ?? 0);
                $priceValue = $priceMap[$key] ?? '';
                
                $sheet->setCellValue($colLetter . $rowIndex, $priceValue);
                if ($priceValue !== '') {
                    $sheet->getStyle($colLetter . $rowIndex)->getNumberFormat()->setFormatCode('#,##0');
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
        }

        // ═══════════════════════════════════════════════════════════
        // تنظیمات نهایی شیت
        // ═══════════════════════════════════════════════════════════
        
        // تنظیم ارتفاع سطر اول (هدرها)
        $sheet->getRowDimension(1)->setRowHeight(40);

        // تنظیم عرض خودکار ستون‌ها
        $highestColumn = $sheet->getHighestColumn();
        $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);
        for ($i = 1; $i <= $highestColumnIndex; $i++) {
            $colLetter = Coordinate::stringFromColumnIndex($i);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        // Freeze اولین سطر (هدر) و سه ستون اول
        $sheet->freezePane('D2');
        
        // قفل کردن ستون تیراژ و شناسه
        $sheet->getProtection()->setSheet(true);
        $sheet->getProtection()->setPassword('B2BMarket2024');
        
        // فقط ستون‌های قیمت و موجودی قابل ویرایش
        for ($i = 4; $i <= 6; $i++) { // D, E, F
            $colLetter = Coordinate::stringFromColumnIndex($i);
            for ($r = 2; $r <= $rowIndex - 1; $r++) {
                $sheet->getStyle($colLetter . $r)
                    ->getProtection()
                    ->setLocked(\PhpOffice\PhpSpreadsheet\Style\Protection::PROTECTION_UNPROTECTED);
            }
        }
        
        // ستون‌های قیمت (از G به بعد تا قبل از آخرین ستون)
        for ($i = 7; $i < $highestColumnIndex; $i++) {
            $colLetter = Coordinate::stringFromColumnIndex($i);
            for ($r = 2; $r <= $rowIndex - 1; $r++) {
                $sheet->getStyle($colLetter . $r)
                    ->getProtection()
                    ->setLocked(\PhpOffice\PhpSpreadsheet\Style\Protection::PROTECTION_UNPROTECTED);
            }
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

    public function render()
    {
        return view('livewire.supplier.fast-pricing.import');
    }
}
