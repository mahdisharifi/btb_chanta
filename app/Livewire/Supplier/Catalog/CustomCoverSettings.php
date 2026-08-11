<?php

namespace App\Livewire\Supplier\Catalog;

use Livewire\Component;
use Livewire\Attributes\Computed;
use App\Models\CatalogTemplate;
use Illuminate\Support\Facades\Auth;

class CustomCoverSettings extends Component
{
    public $catalogTemplate;
    
    // تنظیمات وابستگی
    public $sourceCatalogId = null;
    public $isDefaultTemplate = false;
    
    // تنظیمات کلی کاور
    public $coverSettings = [
        'page_break' => true,
        'background_color' => '#667eea',
        'padding' => 20,
        'default_font_size' => 16,
        'default_font_color' => '#ffffff',
        'default_font_weight' => 'normal',
        'use_custom_font_per_cell' => false,
    ];
    
    // ردیف‌های جدول
    public $rows = [];
    
    // ردیف در حال ویرایش
    public $editingRowIndex = null;
    public $showModal = false;
    
    // فرم ردیف جدید/ویرایش
    public $rowForm = [
        'height' => 100,
        'height_unit' => 'px', // px, mm, auto
        'columns' => 1,
        'vertical_align' => 'middle', // top, middle, bottom
        'background_color' => '',
        'border' => '',
        'cells' => [],
    ];
    
    // فرم سلول
    public $cellForm = [
        'content_type' => 'text', // text, logo, company_name, brand_name, catalog_title, contact, date, spacer
        'content_value' => '',
        'font_size' => 16,
        'font_color' => '#ffffff',
        'font_weight' => 'normal', // normal, bold
        'text_align' => 'center', // left, center, right
        'background_color' => '',
        'padding' => 10,
        'border_radius' => 0,
        'colspan' => 1,
        'width' => '', // درصد یا px
        'logo_width' => 100, // عرض لوگو
        'logo_height' => 100, // ارتفاع لوگو
        'use_default_font' => true, // استفاده از فونت پیش‌فرض
    ];
    
    protected $rules = [
        'coverSettings.page_break' => 'boolean',
        'coverSettings.background_color' => 'required|regex:/^#[0-9A-Fa-f]{6}$/',
        'coverSettings.padding' => 'integer|min:0|max:50',
        'rowForm.height' => 'required|integer|min:20|max:500',
        'rowForm.columns' => 'required|integer|min:1|max:4',
        'rowForm.vertical_align' => 'required|in:top,middle,bottom',
        'rowForm.background_color' => 'nullable|regex:/^#[0-9A-Fa-f]{6}$/',
    ];
    
    public function mount($catalog)
    {
        $this->catalogTemplate = CatalogTemplate::findOrFail($catalog);
        
        // بررسی مالکیت
        $user = Auth::user();
        $isAdmin = $user->hasRole('super_admin');
        
        if (!$isAdmin && $this->catalogTemplate->supplier_id !== $user->supplier->id) {
            abort(403, 'شما مجاز به ویرایش این کاتالوگ نیستید.');
        }
        
        $this->loadSettings();
    }
    
    public function loadSettings()
    {
        $settings = $this->catalogTemplate->settings ?? [];
        $coverData = $settings['custom_cover_table'] ?? [];
        
        // تنظیمات وابستگی
        $this->sourceCatalogId = $coverData['source_catalog_id'] ?? null;
        $this->isDefaultTemplate = $coverData['is_default_template'] ?? false;
        
        // تنظیمات کلی
        $this->coverSettings = array_merge($this->coverSettings, $coverData['settings'] ?? []);
        
        // ردیف‌ها
        $this->rows = $coverData['rows'] ?? [];
        
        // اگر هیچ ردیفی نیست، یک ردیف پیش‌فرض بساز
        if (empty($this->rows)) {
            $this->createDefaultRows();
        }
    }
    
    protected function createDefaultRows()
    {
        // ردیف 1: لوگو و نام شرکت
        $this->rows = [
            [
                'height' => 150,
                'height_unit' => 'px',
                'columns' => 1,
                'vertical_align' => 'middle',
                'background_color' => '',
                'border' => '',
                'cells' => [
                    [
                        'content_type' => 'logo',
                        'content_value' => '',
                        'font_size' => 100,
                        'font_color' => '#ffffff',
                        'font_weight' => 'normal',
                        'text_align' => 'center',
                        'background_color' => '',
                        'padding' => 15,
                        'border_radius' => 15,
                        'colspan' => 1,
                        'width' => '',
                    ]
                ]
            ],
            [
                'height' => 80,
                'height_unit' => 'px',
                'columns' => 1,
                'vertical_align' => 'middle',
                'background_color' => '',
                'border' => '',
                'cells' => [
                    [
                        'content_type' => 'company_name',
                        'content_value' => '',
                        'font_size' => 42,
                        'font_color' => '#ffffff',
                        'font_weight' => 'bold',
                        'text_align' => 'center',
                        'background_color' => '',
                        'padding' => 10,
                        'border_radius' => 0,
                        'colspan' => 1,
                        'width' => '',
                    ]
                ]
            ],
            [
                'height' => 200,
                'height_unit' => 'px',
                'columns' => 1,
                'vertical_align' => 'middle',
                'background_color' => '',
                'border' => '',
                'cells' => [
                    [
                        'content_type' => 'catalog_title',
                        'content_value' => '',
                        'font_size' => 24,
                        'font_color' => '#667eea',
                        'font_weight' => 'bold',
                        'text_align' => 'center',
                        'background_color' => '#ffffff',
                        'padding' => 20,
                        'border_radius' => 30,
                        'colspan' => 1,
                        'width' => '',
                    ]
                ]
            ],
            [
                'height' => 150,
                'height_unit' => 'px',
                'columns' => 1,
                'vertical_align' => 'middle',
                'background_color' => '',
                'border' => '',
                'cells' => [
                    [
                        'content_type' => 'contact',
                        'content_value' => '',
                        'font_size' => 14,
                        'font_color' => '#111827',
                        'font_weight' => 'normal',
                        'text_align' => 'right',
                        'background_color' => '#ffffff',
                        'padding' => 15,
                        'border_radius' => 15,
                        'colspan' => 1,
                        'width' => '',
                    ]
                ]
            ],
        ];
    }
    
    public function addRow()
    {
        $this->editingRowIndex = null;
        $this->rowForm = [
            'height' => 100,
            'height_unit' => 'px',
            'columns' => 1,
            'vertical_align' => 'middle',
            'background_color' => '',
            'border' => '',
            'cells' => [
                $this->getEmptyCell()
            ],
        ];
        $this->showModal = true;
    }
    
    public function editRow($index)
    {
        $this->editingRowIndex = $index;
        $this->rowForm = $this->rows[$index];
        $this->showModal = true;
    }
    
    public function deleteRow($index)
    {
        unset($this->rows[$index]);
        $this->rows = array_values($this->rows);
    }
    
    public function moveRowUp($index)
    {
        if ($index > 0) {
            $temp = $this->rows[$index - 1];
            $this->rows[$index - 1] = $this->rows[$index];
            $this->rows[$index] = $temp;
        }
    }
    
    public function moveRowDown($index)
    {
        if ($index < count($this->rows) - 1) {
            $temp = $this->rows[$index + 1];
            $this->rows[$index + 1] = $this->rows[$index];
            $this->rows[$index] = $temp;
        }
    }
    
    public function moveCellLeft($cellIndex)
    {
        if ($cellIndex > 0) {
            $temp = $this->rowForm['cells'][$cellIndex - 1];
            $this->rowForm['cells'][$cellIndex - 1] = $this->rowForm['cells'][$cellIndex];
            $this->rowForm['cells'][$cellIndex] = $temp;
        }
    }
    
    public function moveCellRight($cellIndex)
    {
        if ($cellIndex < count($this->rowForm['cells']) - 1) {
            $temp = $this->rowForm['cells'][$cellIndex + 1];
            $this->rowForm['cells'][$cellIndex + 1] = $this->rowForm['cells'][$cellIndex];
            $this->rowForm['cells'][$cellIndex] = $temp;
        }
    }
    
    public function addCell()
    {
        if (count($this->rowForm['cells']) < 4) {
            $this->rowForm['columns']++;
            $this->rowForm['cells'][] = $this->getEmptyCell();
        }
    }
    
    public function removeCell($cellIndex)
    {
        if (count($this->rowForm['cells']) > 1) {
            unset($this->rowForm['cells'][$cellIndex]);
            $this->rowForm['cells'] = array_values($this->rowForm['cells']);
            $this->rowForm['columns']--;
        }
    }
    
    protected function getEmptyCell()
    {
        return [
            'content_type' => 'text',
            'content_value' => '',
            'font_size' => $this->coverSettings['default_font_size'],
            'font_color' => $this->coverSettings['default_font_color'],
            'font_weight' => $this->coverSettings['default_font_weight'],
            'text_align' => 'center',
            'background_color' => '',
            'padding' => 10,
            'border_radius' => 0,
            'colspan' => 1,
            'width' => '',
            'logo_width' => 100,
            'logo_height' => 100,
            'use_default_font' => true,
        ];
    }
    
    public function saveRow()
    {
        $this->validate([
            'rowForm.height' => 'required|integer|min:20|max:500',
            'rowForm.columns' => 'required|integer|min:1|max:4',
        ]);
        
        if ($this->editingRowIndex !== null) {
            $this->rows[$this->editingRowIndex] = $this->rowForm;
        } else {
            $this->rows[] = $this->rowForm;
        }
        
        $this->showModal = false;
        $this->editingRowIndex = null;
    }
    
    public function cancelEdit()
    {
        $this->showModal = false;
        $this->editingRowIndex = null;
    }
    
    public function save()
    {
        $this->validate();
        
        $coverData = [
            'source_catalog_id' => $this->sourceCatalogId,
            'is_default_template' => $this->isDefaultTemplate,
            'settings' => $this->coverSettings,
            'rows' => $this->rows,
        ];
        
        $settings = $this->catalogTemplate->settings ?? [];
        $settings['custom_cover_table'] = $coverData;
        
        $this->catalogTemplate->update(['settings' => $settings]);
        
        // Cascade update اگر پیش‌فرض است
        if ($this->isDefaultTemplate) {
            $this->updateDependentCatalogs($coverData);
        }
        
        session()->flash('success', 'تنظیمات کاور با موفقیت ذخیره شد.');
        
        return redirect()->route('supplier.catalog.index');
    }
    
    protected function updateDependentCatalogs($coverData)
    {
        $user = Auth::user();
        $supplierId = $user->supplier->id ?? null;
        
        $dependentCatalogs = CatalogTemplate::where('supplier_id', $supplierId)
            ->where('id', '!=', $this->catalogTemplate->id)
            ->get()
            ->filter(function ($catalog) {
                $settings = $catalog->settings ?? [];
                return ($settings['custom_cover_table']['source_catalog_id'] ?? null) == $this->catalogTemplate->id;
            });
        
        foreach ($dependentCatalogs as $catalog) {
            $settings = $catalog->settings ?? [];
            $settings['custom_cover_table'] = $coverData;
            $settings['custom_cover_table']['source_catalog_id'] = $this->catalogTemplate->id;
            $settings['custom_cover_table']['is_default_template'] = false;
            $catalog->update(['settings' => $settings]);
        }
    }
    
    public function detachFromDefault()
    {
        $this->sourceCatalogId = null;
        
        $settings = $this->catalogTemplate->settings ?? [];
        if (isset($settings['custom_cover_table'])) {
            $settings['custom_cover_table']['source_catalog_id'] = null;
            $this->catalogTemplate->update(['settings' => $settings]);
        }
        
        session()->flash('success', 'این کاتالوگ اکنون مستقل است.');
    }
    
    #[Computed]
    public function sourceCatalogName()
    {
        if (!$this->sourceCatalogId) {
            return null;
        }
        
        $sourceCatalog = CatalogTemplate::find($this->sourceCatalogId);
        return $sourceCatalog ? $sourceCatalog->name : "کاتالوگ #{$this->sourceCatalogId}";
    }
    
    public function render()
    {
        $user = Auth::user();
        $isAdmin = $user->hasRole('super_admin');
        $layout = $isAdmin ? 'components.layouts.admin' : 'components.layouts.supplier';
        
        return view('livewire.supplier.catalog.custom-cover-settings')
            ->layout($layout);
    }
}
