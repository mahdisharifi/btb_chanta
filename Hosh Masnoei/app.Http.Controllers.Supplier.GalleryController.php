<?php

namespace App\Http\Controllers\Supplier;

use App\Http\Controllers\Controller;
use App\Models\ProductGallery;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;

class GalleryController extends Controller
{
    private function getSupplier(): Supplier
    {
        return Supplier::where('user_id', auth()->id())->firstOrFail();
    }

    public function index(): View
    {
        $supplier = $this->getSupplier();

        $galleries = ProductGallery::query()
            ->whereHas('product', fn($q) => $q->where('supplier_id', $supplier->id))
            ->with('product:id,title,slug')
            ->latest()
            ->paginate(24);

        return view('supplier.gallery.index', compact('galleries'));
    }

    public function upload(Request $request): RedirectResponse
    {
        $supplier = $this->getSupplier();

        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'files' => ['required', 'array', 'max:10'],
            'files.*' => ['required', 'file', 'mimes:jpeg,png,jpg,gif,webp,mp4,mov', 'max:20480'],
        ], [
            'files.required' => 'انتخاب فایل الزامی است.',
            'files.*.mimes' => 'فرمت فایل مجاز نیست.',
            'files.*.max' => 'حداکثر حجم فایل ۲۰ مگابایت است.',
        ]);

        // Verify product belongs to supplier
        $product = \App\Models\Product::findOrFail($validated['product_id']);
        abort_unless($product->supplier_id === $supplier->id, 403);

        $lastOrder = $product->galleries()->max('sort_order') ?? 0;

        foreach ($request->file('files') as $file) {
            $path = $file->store('galleries/' . $product->id, 'public');

            $fileType = in_array($file->getClientOriginalExtension(), ['mp4', 'mov']) ? 'video' : 'image';

            $product->galleries()->create([
                'file_path' => $path,
                'file_type' => $fileType,
                'title' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                'alt_text' => $product->title,
                'sort_order' => ++$lastOrder,
                'is_primary' => $product->galleries()->count() === 0, // First image is primary
            ]);
        }

        return back()->with('success', 'فایل‌ها با موفقیت آپلود شدند.');
    }

    public function destroy(ProductGallery $media): RedirectResponse
    {
        $supplier = $this->getSupplier();
        abort_unless($media->product->supplier_id === $supplier->id, 403);

        // Delete file from storage
        Storage::disk('public')->delete($media->file_path);

        $wasPrimary = $media->is_primary;
        $productId = $media->product_id;

        $media->delete();

        // If deleted was primary, set next image as primary
        if ($wasPrimary) {
            $nextImage = ProductGallery::where('product_id', $productId)
                ->orderBy('sort_order')
                ->first();

            if ($nextImage) {
                $nextImage->update(['is_primary' => true]);
            }
        }

        return back()->with('success', 'فایل با موفقیت حذف شد.');
    }

    public function reorder(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'order' => ['required', 'array'],
            'order.*.id' => ['required', 'exists:product_galleries,id'],
            'order.*.sort_order' => ['required', 'integer', 'min:0'],
        ]);

        $supplier = $this->getSupplier();

        foreach ($validated['order'] as $item) {
            $gallery = ProductGallery::find($item['id']);
            if ($gallery && $gallery->product->supplier_id === $supplier->id) {
                $gallery->update(['sort_order' => $item['sort_order']]);
            }
        }

        return back()->with('success', 'ترتیب تصاویر با موفقیت تغییر کرد.');
    }
}
