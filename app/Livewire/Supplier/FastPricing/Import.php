<?php

namespace App\Livewire\Supplier\FastPricing;

use App\Enums\PriceType;
use App\Models\PriceUpdateBatch;
use App\Models\PriceUpdateItem;
use App\Models\ProductSupplier;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
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
        'file' => 'required|mimes:xlsx|max:5120', // حداکثر 5 مگابایت
    ];

    protected $messages = [
        'file.required' => 'لطفاً یک فایل انتخاب کنید.',
        'file.mimes' => 'فقط فرمت اکسل (xlsx) مجاز است.',
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
            $reader = new XlsxReader();
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($this->file->getRealPath());
            $sheet = $spreadsheet->getActiveSheet();

            $rows = $sheet->toArray();

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
            foreach ($headers as $index => $header) {
                if ($header === null) continue;
                $header = trim($header);

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
                } else {
                    foreach (PriceType::cases() as $priceType) {
                        if (str_contains($header, $priceType->label()) || str_contains($header, $priceType->value)) {
                            $priceColumns[$priceType->value] = $index;
                            break;
                        }
                    }
                }
            }

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

                if (empty($newPrices) && $newStock === null && $newIsActive === null) {
                    $rowErrors[] = "هیچ قیمت، موجودی یا وضعیتی برای بروزرسانی وارد نشده است.";
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
                    'old_is_active'         => $productSupplier->is_active,  // <-- اصلاح شد: تغییر $ps به $productSupplier
                    'new_is_active'         => $newIsActive,    // ثبت وضعیت جدید از فایل
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

            // وضعیت به صورت 0 و 1
            $sheet->setCellValue('C' . $rowIndex, $ps->is_active ? 1 : 0);

            // موجودی با فرمت عددی بدون اعشار
            $sheet->setCellValue('D' . $rowIndex, $ps->stock_quantity ?? 0);
            $sheet->getStyle('D' . $rowIndex)->getNumberFormat()->setFormatCode('#,##0');

            // قیمت‌ها با فرمت عددی بدون اعشار
            $currentCol = 5; // شروع از ستون E
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
