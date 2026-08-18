<?php

namespace App\Livewire\Supplier\FastPricing;

use App\Enums\PriceType;
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

            // راه‌اندازی PhpSpreadsheet Reader
            $extension = strtolower($this->file->getClientOriginalExtension());
            
            if ($extension === 'csv' || $extension === 'txt') {
                // خواندن فایل CSV با جداکننده Tab یا کاما
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

            if (count($rows) <= 1) {
                throw new \Exception("فایل خالی است یا فقط هدر دارد.");
            }

            if (count($rows) > 2000) {
                throw new \Exception("تعداد ردیف‌ها بیش از حد مجاز (2000) است.");
            }

            $headers = array_shift($rows);
            $headerMap = [];
            $priceColumns = [];

            // شناسایی ستون‌ها
            Log::info('Fast Pricing - Headers detected:', ['headers' => $headers]);
            
            foreach ($headers as $index => $header) {
                if ($header === null) continue;
                $header = trim($header);

                Log::info("Processing header [$index]: $header");

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
                    // حذف پیشوند "قیمت " و پسوند " (تومان)" برای تطبیق بهتر
                    $cleanHeader = preg_replace('/^قیمت\s*/u', '', $header);
                    $cleanHeader = preg_replace('/\s*\(تومان\)/u', '', $cleanHeader);
                    $cleanHeader = trim($cleanHeader);

                    Log::info("Clean header: $cleanHeader");

                    // مرتب‌سازی PriceType ها بر اساس طول label (از بلند به کوتاه)
                    // تا "زیر کارتن" قبل از "کارتن" بررسی شود
                    $priceTypes = PriceType::cases();
                    usort($priceTypes, function ($a, $b) {
                        return mb_strlen($b->label()) - mb_strlen($a->label());
                    });

                    foreach ($priceTypes as $priceType) {
                        Log::info("Checking against PriceType: {$priceType->value} (label: {$priceType->label()})");
                        
                        // تطبیق دقیق
                        if ($cleanHeader === $priceType->label()) {
                            $priceColumns[$priceType->value] = $index;
                            Log::info("EXACT MATCH: {$priceType->value} at index $index");
                            break;
                        }
                        
                        // تطبیق با value
                        if (str_contains($header, $priceType->value)) {
                            $priceColumns[$priceType->value] = $index;
                            Log::info("VALUE MATCH: {$priceType->value} at index $index");
                            break;
                        }
                        
                        // تطبیق جزئی (فقط اگر هدر کامل شامل label باشد)
                        if (str_contains($header, $priceType->label())) {
                            $priceColumns[$priceType->value] = $index;
                            Log::info("PARTIAL MATCH: {$priceType->value} at index $index");
                            break;
                        }
                    }
                }
            }

            Log::info('Fast Pricing - Header Map:', ['headerMap' => $headerMap, 'priceColumns' => $priceColumns]);

            if (!isset($headerMap['product_id'])) {
                throw new \Exception("ستون 'شناسه کالا' یافت نشد. لطفاً از قالب استاندارد سیستم استفاده کنید.");
            }

            $validItemsCount = 0;

            foreach ($rows as $rowIndex => $row) {
                $actualRowNumber = $rowIndex + 2; // +1 برای صفر-بیس بودن و +1 برای هدر

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
                $encryptedId = $row[$headerMap['product_id']] ?? null;
                $productName = isset($headerMap['product_name']) ? ($row[$headerMap['product_name']] ?? 'نامشخص') : 'نامشخص';
                $sku = isset($headerMap['sku']) ? ($row[$headerMap['sku']] ?? '') : '';

                if (empty($encryptedId)) {
                    $rowErrors[] = "ستون شناسه کالا خالی است.";
                    $this->errorRows[] = [
                        'row' => $actualRowNumber,
                        'product_name' => $productName,
                        'errors' => $rowErrors,
                    ];
                    continue;
                }

                try {
                    $productId = Crypt::decryptString($encryptedId);
                } catch (\Exception $e) {
                    $rowErrors[] = "شناسه کالا دستکاری شده یا نامعتبر است.";
                    $this->errorRows[] = [
                        'row' => $actualRowNumber,
                        'product_name' => $productName,
                        'errors' => $rowErrors,
                    ];
                    continue;
                }

                $productSupplier = ProductSupplier::where('supplier_id', $supplier->id)
                    ->where('id', $productId)
                    ->with('prices')
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

                $oldPrices = [];
                foreach ($productSupplier->prices as $priceObj) {
                    $oldPrices[$priceObj->price_type->value] = $priceObj->price;
                }

                $oldStock = $productSupplier->stock_quantity;

                $newPrices = [];
                foreach ($priceColumns as $type => $colIndex) {
                    $val = $row[$colIndex] ?? null;
                    if ($val !== null && trim((string)$val) !== '') {
                        $cleanedVal = preg_replace('/[^\d]/', '', (string)$val);
                        if (is_numeric($cleanedVal)) {
                            $newPrices[$type] = (int)$cleanedVal;
                        } else {
                            $rowErrors[] = "فرمت قیمت برای ستون نامعتبر است.";
                        }
                    }
                }

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

                // خواندن ستون تعداد (min_order_quantity)
                $newMinOrderQuantity = null;
                if (isset($headerMap['min_order_quantity'])) {
                    $quantityVal = $row[$headerMap['min_order_quantity']] ?? null;
                    if ($quantityVal !== null && trim((string)$quantityVal) !== '') {
                        $cleanedQuantity = preg_replace('/[^\d]/', '', (string)$quantityVal);
                        if (is_numeric($cleanedQuantity)) {
                            $newMinOrderQuantity = (int)$cleanedQuantity;
                        } else {
                            $rowErrors[] = "فرمت تعداد نامعتبر است.";
                        }
                    }
                }

                // خواندن ستون وضعیت (1 = فعال، 0 = غیرفعال)
                $newIsActive = null;
                if (isset($headerMap['status'])) {
                    $statusVal = $row[$headerMap['status']] ?? null;
                    if ($statusVal !== null && trim((string)$statusVal) !== '') {
                        $statusStr = trim((string)$statusVal);
                        if ($statusStr === '1' || $statusStr === 'فعال' || $statusStr === 'true') {
                            $newIsActive = true;
                        } else {
                            $newIsActive = false;
                        }
                    }
                }

                if (empty($newPrices) && $newStock === null && $newMinOrderQuantity === null && $newIsActive === null) {
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

                PriceUpdateItem::create([
                    'price_update_batch_id' => $batch->id,
                    'product_supplier_id' => $productSupplier->id,
                    'old_prices' => $oldPrices,
                    'new_prices' => $newPrices,
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
            Log::error('Fast Pricing Import Error: ' . $e->getMessage(), [
                'file' => $this->file ? $this->file->getClientOriginalName() : 'no file',
                'trace' => $e->getTraceAsString()
            ]);
            $this->addError('file', 'خطا در پردازش فایل: ' . $e->getMessage());
            $this->isProcessing = false;
        }
    }

    public function downloadTemplate()
    {
        $supplier = Auth::user()->supplier;
        if (!$supplier) {
            abort(403, 'تامین کننده یافت نشد.');
        }

        // دریافت تمامی کالاها
        $productSuppliers = ProductSupplier::where('supplier_id', $supplier->id)
            ->with(['product', 'prices'])
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // راست‌چین کردن شیت
        $sheet->setRightToLeft(true);

        $headers = [
            'SKU اختصاصی (اختیاری)',
            'نام کالا',
            'تعداد',
            'وضعیت (1=فعال، 0=غیرفعال)',
            'موجودی',
        ];

        $priceTypes = PriceType::cases();
        foreach ($priceTypes as $type) {
            $headers[] = 'قیمت ' . $type->label() . ' (تومان)';
        }

        // انتقال هدر شناسه کالا به انتهای آرایه هدرها
        $headers[] = 'شناسه کالا (تغییر ندهید)';

        // نوشتن هدرها در سطر اول
        $colIndex = 1;
        foreach ($headers as $header) {
            $colLetter = Coordinate::stringFromColumnIndex($colIndex);
            $sheet->setCellValue($colLetter . '1', $header);
            $sheet->getStyle($colLetter . '1')->getFont()->setBold(true);
            $colIndex++;
        }

        $rowIndex = 2;
        foreach ($productSuppliers as $ps) {
            $prices = [];
            foreach ($ps->prices as $price) {
                $prices[$price->price_type->value] = $price->price;
            }

            $encryptedId = Crypt::encryptString((string)$ps->id);

            // ستون‌های اولیه
            $sheet->setCellValue('A' . $rowIndex, $ps->sku ?? '');
            $sheet->setCellValue('B' . $rowIndex, $ps->product->title ?? 'بدون نام');

            // تعداد (min_order_quantity)
            $sheet->setCellValue('C' . $rowIndex, $ps->min_order_quantity ?? 1);

            // وضعیت به صورت 0 و 1
            $sheet->setCellValue('D' . $rowIndex, $ps->is_active ? 1 : 0);

            // موجودی با فرمت عددی بدون اعشار
            $sheet->setCellValue('E' . $rowIndex, $ps->stock_quantity ?? 0);
            $sheet->getStyle('E' . $rowIndex)->getNumberFormat()->setFormatCode('#,##0');

            // قیمت‌ها با فرمت عددی بدون اعشار
            $currentCol = 6; // شروع از ستون F
            foreach ($priceTypes as $type) {
                $colLetter = Coordinate::stringFromColumnIndex($currentCol);
                $priceValue = $prices[$type->value] ?? '';
                $sheet->setCellValue($colLetter . $rowIndex, $priceValue);
                if ($priceValue !== '') {
                    $sheet->getStyle($colLetter . $rowIndex)->getNumberFormat()->setFormatCode('#,##0');
                }
                $currentCol++;
            }

            // شناسه کالا در ستون آخر
            $lastColLetter = Coordinate::stringFromColumnIndex($currentCol);
            $sheet->setCellValue($lastColLetter . $rowIndex, $encryptedId);

            $rowIndex++;
        }

        // تنظیم عرض خودکار ستون‌ها بر اساس محتوا
        $highestColumn = $sheet->getHighestColumn();
        $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);
        for ($i = 1; $i <= $highestColumnIndex; $i++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
        }

        $fileName = 'price_update_template_' . now()->format('Y-m-d_H-i') . '.xlsx';

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
