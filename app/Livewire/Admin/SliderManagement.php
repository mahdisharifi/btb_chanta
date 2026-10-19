<?php

namespace App\Livewire\Admin;

use App\Models\Slider;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Livewire\WithFileUploads;

#[Layout('components.layouts.admin')]
#[Title('مدیریت اسلایدر اصلی')]
class SliderManagement extends Component
{
    use WithPagination, WithFileUploads;

    // ═══════════════════════════════════════════════════════════
    // Properties
    // ═══════════════════════════════════════════════════════════

    public $sliderId;
    public $showModal = false;
    public $deleteConfirm = false;
    public $deleteId;
    public $existingImagePath;
    public $existingMobileImagePath;
    public bool $removeMobileImage = false;
    public bool $removeDesktopImage = false;

    #[Validate('nullable|image|mimes:jpg,jpeg,png,webp|max:6144|dimensions:max_width=6000,max_height=4000')]
    public $desktopUpload;

    #[Validate('nullable|image|mimes:jpg,jpeg,png,webp|max:6144|dimensions:max_width=6000,max_height=4000')]
    public $mobileUpload;

    // Form Fields
    #[Validate('required|string|max:255')]
    public $title = '';

    #[Validate('nullable|string|max:500')]
    public $subtitle = '';

    #[Validate('nullable|string|max:1000')]
    public $description = '';

    #[Validate('nullable|string|max:500')]
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
            'starts_at', 'ends_at', 'desktopUpload', 'mobileUpload',
            'existingImagePath', 'existingMobileImagePath', 'removeMobileImage',
        ]);

        $this->removeDesktopImage = false;
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
        $this->existingImagePath = $slider->image_path;
        $this->existingMobileImagePath = $slider->mobile_image_path;
        $this->desktopUpload = null;
        $this->mobileUpload = null;
        $this->removeMobileImage = false;
        $this->removeDesktopImage = false;

        $this->showModal = true;
    }

    public function save()
    {
        $this->validate();

        if ($this->starts_at && $this->ends_at) {
            $this->validate(['ends_at' => 'date|after_or_equal:starts_at']);
        }

        if (filled($this->link_url) && ! $this->isValidSliderLink($this->link_url)) {
            $this->addError('link_url', 'لینک باید یک آدرس http/https یا مسیر داخلی معتبر باشد.');
            return;
        }

        $slider = $this->sliderId ? Slider::findOrFail($this->sliderId) : new Slider();
        $oldImagePath = $slider->image_path;
        $oldMobileImagePath = $slider->mobile_image_path;
        $newImagePath = null;
        $newMobileImagePath = null;
        $saved = false;
        $images = app(\App\Services\SliderImageService::class);

        try {
            $data = [
                'title' => $this->sanitizeInput($this->title),
                'subtitle' => $this->sanitizeInput($this->subtitle),
                'description' => $this->sanitizeInput($this->description),
                'link_url' => $this->link_url,
                'link_text' => $this->sanitizeInput($this->link_text),
                'link_target' => $this->link_target,
                'bg_color' => $this->bg_color ?: 'transparent',
                'bg_gradient' => $this->bg_gradient,
                'text_color' => $this->text_color ?: '#ffffff',
                'overlay_color' => $this->overlay_color ?: 'transparent',
                'text_position' => $this->text_position,
                'sort_order' => (int) $this->sort_order,
                'is_active' => (bool) $this->is_active,
                'starts_at' => $this->starts_at
                    ? Carbon::parse($this->starts_at)->startOfDay()
                    : null,
                'ends_at' => $this->ends_at
                    ? Carbon::parse($this->ends_at)->endOfDay()
                    : null,
            ];

            if (! $this->sliderId) {
                $data['image_path'] = null;
                $data['mobile_image_path'] = null;
            }

            if ($this->desktopUpload) {
                $newImagePath = $images->processUpload($this->desktopUpload, 'desktop');
                $data['image_path'] = $newImagePath;
            } elseif ($this->removeDesktopImage) {
                $data['image_path'] = null;
            }

            if ($this->mobileUpload) {
                $newMobileImagePath = $images->processUpload($this->mobileUpload, 'mobile');
                $data['mobile_image_path'] = $newMobileImagePath;
            } elseif ($this->removeMobileImage) {
                $data['mobile_image_path'] = null;
            }

            DB::transaction(function () use ($slider, $data): void {
                $slider->fill($data)->save();
            });
            $saved = true;

            if (($newImagePath || $this->removeDesktopImage) && $oldImagePath && $oldImagePath !== $newImagePath) {
                $images->delete($oldImagePath);
            }
            if (($newMobileImagePath || $this->removeMobileImage)
                && $oldMobileImagePath
                && $oldMobileImagePath !== $newMobileImagePath) {
                $images->delete($oldMobileImagePath);
            }

            $this->reset([
                'showModal', 'sliderId', 'desktopUpload', 'mobileUpload',
                'existingImagePath', 'existingMobileImagePath', 'removeMobileImage', 'removeDesktopImage',
            ]);
            $this->resetPage();
            cache()->forget('home:sliders:active:v2');
            $this->dispatch('slider-saved', message: 'اسلایدر با موفقیت ذخیره شد.');

        } catch (\Throwable $e) {
            if (! $saved) {
                $images->delete($newImagePath);
                $images->delete($newMobileImagePath);
            }
            report($e);
            $this->dispatch('slider-error', message: 'ذخیره اسلایدر انجام نشد. لطفاً ورودی‌ها و فایل‌های تصویر را بررسی کنید و دوباره تلاش کنید.');
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
            $slider = Slider::findOrFail($this->deleteId);
            $imagePath = $slider->image_path;
            $mobileImagePath = $slider->mobile_image_path;
            $slider->delete();
            $images = app(\App\Services\SliderImageService::class);
            $images->delete($imagePath);
            $images->delete($mobileImagePath);
            $this->dispatch('slider-deleted', message: 'اسلایدر با موفقیت حذف شد.');
            $this->reset(['deleteConfirm', 'deleteId']);
            $this->resetPage();
            cache()->forget('home:sliders:active:v2');

        } catch (\Exception $e) {
            report($e);
            $this->dispatch('slider-error', message: 'حذف اسلایدر انجام نشد. لطفاً دوباره تلاش کنید.');
        }
    }

    public function toggleActive($id)
    {
        try {
            $slider = Slider::findOrFail($id);
            $slider->update(['is_active' => !$slider->is_active]);
            $status = $slider->is_active ? 'فعال' : 'غیرفعال';
            $this->dispatch('slider-toggled', message: "اسلایدر {$status} شد.");
            cache()->forget('home:sliders:active:v2');

        } catch (\Exception $e) {
            report($e);
            $this->dispatch('slider-error', message: 'تغییر وضعیت اسلایدر انجام نشد. لطفاً دوباره تلاش کنید.');
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
            cache()->forget('home:sliders:active:v2');

        } catch (\Exception $e) {
            report($e);
            $this->dispatch('slider-error', message: 'تغییر ترتیب اسلایدر انجام نشد. لطفاً دوباره تلاش کنید.');
        }
    }

    public function closeModal()
    {
        $this->reset([
            'showModal', 'sliderId', 'desktopUpload', 'mobileUpload',
            'existingImagePath', 'existingMobileImagePath', 'removeMobileImage',
        ]);
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

    private function isValidSliderLink(string $url): bool
    {
        $url = trim($url);
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        if (in_array($scheme, ['http', 'https'], true)) {
            return filter_var($url, FILTER_VALIDATE_URL) !== false;
        }

        return str_starts_with($url, '/')
            && ! str_starts_with($url, '//')
            && ! str_contains($url, '\\')
            && preg_match('/[\x00-\x20]/', $url) !== 1;
    }
}
