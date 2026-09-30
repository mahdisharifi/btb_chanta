<?php

namespace App\Livewire\Admin;

use App\Models\Slider;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Illuminate\Support\Facades\DB;

#[Layout('components.layouts.admin')]
#[Title('مدیریت اسلایدر اصلی')]
class SliderManagement extends Component
{
    use WithPagination;

    // ═══════════════════════════════════════════════════════════
    // Properties
    // ═══════════════════════════════════════════════════════════

    public $sliderId;
    public $showModal = false;
    public $deleteConfirm = false;
    public $deleteId;

    // Form Fields
    #[Validate('required|string|max:255')]
    public $title = '';

    #[Validate('nullable|string|max:500')]
    public $subtitle = '';

    #[Validate('nullable|string|max:1000')]
    public $description = '';

    #[Validate('nullable|url|max:500')]
    public $link_url = '';

    #[Validate('nullable|string|max:100')]
    public $link_text = '';

    #[Validate('nullable|in:_self,_blank')]
    public $link_target = '_self';

    #[Validate('nullable|string|max:50')]
    public $bg_color = 'transparent';

    #[Validate('nullable|string|max:500')]
    public $bg_gradient = '';

    #[Validate('nullable|string|max:50')]
    public $text_color = '#ffffff';

    #[Validate('nullable|string|max:50')]
    public $overlay_color = 'transparent';

    #[Validate('required|in:left,center,right')]
    public $text_position = 'center';

    #[Validate('required|integer|min:0|max:999')]
    public $sort_order = 0;

    #[Validate('required|boolean')]
    public $is_active = true;

    #[Validate('nullable|date')]
    public $starts_at = null;

    #[Validate('nullable|date')]
    public $ends_at = null;

    // ═══════════════════════════════════════════════════════════
    // Lifecycle
    // ═══════════════════════════════════════════════════════════

    public function render()
    {
        $sliders = Slider::query()
            ->orderBy('sort_order', 'asc')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('livewire.admin.slider-management', [
            'sliders' => $sliders,
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // Actions
    // ═══════════════════════════════════════════════════════════

    public function create()
    {
        $this->reset([
            'sliderId', 'title', 'subtitle', 'description',
            'link_url', 'link_text', 'link_target',
            'bg_color', 'bg_gradient', 'text_color', 'overlay_color',
            'text_position', 'sort_order', 'is_active',
            'starts_at', 'ends_at',
        ]);

        $this->bg_color = 'transparent';
        $this->overlay_color = 'transparent';
        $this->text_color = '#ffffff';
        $this->text_position = 'center';
        $this->link_target = '_self';
        $this->is_active = true;
        $this->sort_order = Slider::max('sort_order') + 1;

        $this->showModal = true;
    }

    public function edit($id)
    {
        $slider = Slider::findOrFail($id);

        $this->sliderId = $slider->id;
        $this->title = $slider->title;
        $this->subtitle = $slider->subtitle;
        $this->description = $slider->description;
        $this->link_url = $slider->link_url;
        $this->link_text = $slider->link_text;
        $this->link_target = $slider->link_target;
        $this->bg_color = $slider->bg_color ?? 'transparent';
        $this->bg_gradient = $slider->bg_gradient;
        $this->text_color = $slider->text_color ?? '#ffffff';
        $this->overlay_color = $slider->overlay_color ?? 'transparent';
        $this->text_position = $slider->text_position;
        $this->sort_order = $slider->sort_order;
        $this->is_active = $slider->is_active;
        $this->starts_at = $slider->starts_at?->format('Y-m-d');
        $this->ends_at = $slider->ends_at?->format('Y-m-d');

        $this->showModal = true;
    }

    public function save()
    {
        $this->validate();

        try {
            DB::transaction(function () {
                $data = [
                    'title' => $this->sanitizeInput($this->title),
                    'subtitle' => $this->sanitizeInput($this->subtitle),
                    'description' => $this->sanitizeInput($this->description),
                    'image_path' => '',
                    'mobile_image_path' => '',
                    'link_url' => $this->link_url,
                    'link_text' => $this->sanitizeInput($this->link_text),
                    'link_target' => $this->link_target,
                    'bg_color' => $this->bg_color ?: 'transparent',
                    'bg_gradient' => $this->bg_gradient,
                    'text_color' => $this->text_color ?: '#ffffff',
                    'overlay_color' => $this->overlay_color ?: 'transparent',
                    'text_position' => $this->text_position,
                    'sort_order' => (int)$this->sort_order,
                    'is_active' => (bool)$this->is_active,
                    'starts_at' => $this->starts_at ?: null,
                    'ends_at' => $this->ends_at ?: null,
                ];

                if ($this->sliderId) {
                    Slider::where('id', $this->sliderId)->update($data);
                    $message = 'اسلایدر با موفقیت بروزرسانی شد.';
                } else {
                    Slider::create($data);
                    $message = 'اسلایدر جدید با موفقیت ایجاد شد.';
                }

                $this->dispatch('slider-saved', message: $message);
            });

            $this->reset(['showModal', 'sliderId']);
            $this->resetPage();
            cache()->forget('home:sliders:active');

        } catch (\Exception $e) {
            $this->dispatch('slider-error', message: 'خطا در ذخیره اسلایدر: ' . $e->getMessage());
        }
    }

    public function confirmDelete($id)
    {
        $this->deleteId = $id;
        $this->deleteConfirm = true;
    }

    public function delete()
    {
        try {
            Slider::findOrFail($this->deleteId)->delete();
            $this->dispatch('slider-deleted', message: 'اسلایدر با موفقیت حذف شد.');
            $this->reset(['deleteConfirm', 'deleteId']);
            $this->resetPage();
            cache()->forget('home:sliders:active');

        } catch (\Exception $e) {
            $this->dispatch('slider-error', message: 'خطا در حذف اسلایدر: ' . $e->getMessage());
        }
    }

    public function toggleActive($id)
    {
        try {
            $slider = Slider::findOrFail($id);
            $slider->update(['is_active' => !$slider->is_active]);
            $status = $slider->is_active ? 'فعال' : 'غیرفعال';
            $this->dispatch('slider-toggled', message: "اسلایدر {$status} شد.");
            cache()->forget('home:sliders:active');

        } catch (\Exception $e) {
            $this->dispatch('slider-error', message: 'خطا در تغییر وضعیت: ' . $e->getMessage());
        }
    }

    public function updateOrder($id, $direction)
    {
        try {
            DB::transaction(function () use ($id, $direction) {
                $slider = Slider::findOrFail($id);
                $currentOrder = $slider->sort_order;
                $newOrder = $direction === 'up' ? $currentOrder - 1 : $currentOrder + 1;

                // جابجایی با اسلایدر دیگر
                $otherSlider = Slider::where('sort_order', $newOrder)->first();
                if ($otherSlider) {
                    $otherSlider->update(['sort_order' => $currentOrder]);
                }

                $slider->update(['sort_order' => $newOrder]);
            });

            $this->dispatch('slider-reordered', message: 'ترتیب اسلایدرها تغییر کرد.');
            cache()->forget('home:sliders:active');

        } catch (\Exception $e) {
            $this->dispatch('slider-error', message: 'خطا در تغییر ترتیب: ' . $e->getMessage());
        }
    }

    public function closeModal()
    {
        $this->reset(['showModal', 'sliderId']);
    }

    public function cancelDelete()
    {
        $this->reset(['deleteConfirm', 'deleteId']);
    }

    // ═══════════════════════════════════════════════════════════
    // Helpers
    // ═══════════════════════════════════════════════════════════

    private function sanitizeInput(?string $input): ?string
    {
        if ($input === null) return null;
        return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
    }
}
