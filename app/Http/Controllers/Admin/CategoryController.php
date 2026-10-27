<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Category::class, 'category');
    }

    public function index(Request $request): View
    {
        $query = Category::query()
            ->withCount('products')
            ->with('parent');

        if ($request->filled('q')) {
            $query->where('name', 'like', '%' . $request->q . '%');
        }

        if ($request->filled('parent')) {
            if ($request->parent === 'root') {
                $query->whereNull('parent_id');
            } else {
                $query->where('parent_id', $request->parent);
            }
        }

        $query->orderBy('sort_order');

        $categories = $query->paginate(25)->withQueryString();

        $categoryTree = Category::whereNull('parent_id')
            ->where('is_active', true)
            ->with(['children' => function ($query) {
                $query->orderBy('sort_order');
            }])
            ->orderBy('sort_order')
            ->get();

        return view('admin.categories.index', compact('categories', 'categoryTree'));
    }

    public function create(): View
    {
        $parentCategories = Category::whereNull('parent_id')
            ->with(['children' => function ($query) {
                $query->orderBy('sort_order');
            }])
            ->orderBy('sort_order')
            ->get();

        return view('admin.categories.create', compact('parentCategories'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required','string','max:255','unique:categories,name'],
            'parent_id' => ['nullable','exists:categories,id'],
            'description' => ['nullable','string','max:1000'],
            'icon' => ['nullable','string', Rule::in(\App\Support\CategoryIcon::allowedValues())],
            'sort_order' => ['integer','min:0'],
            'is_active' => ['boolean'],
            'seo_title' => ['nullable','string','max:60'],
            'seo_description' => ['nullable','string','max:160'],
            'seo_keywords' => ['nullable','string','max:255'],
        ],[
            'name.required' => 'نام دسته‌بندی الزامی است.',
            'name.max' => 'نام دسته‌بندی نباید بیشتر از 255 کاراکتر باشد.',
            'name.unique' => 'این نام دسته‌بندی قبلاً استفاده شده است.',
            'parent_id.exists' => 'دسته‌بندی والد معتبر نیست.',
        ]);

        try {

            DB::beginTransaction();

            if ($validated['parent_id'] ?? null) {

                $parent = Category::find($validated['parent_id']);

                $depth = 0;
                $current = $parent;

                while ($current && $depth < 4) {
                    $depth++;
                    $current = $current->parent;
                }

                if ($depth >= 3) {
                    DB::rollBack();

                    return back()
                        ->withInput()
                        ->withErrors([
                            'parent_id' => 'حداکثر عمق دسته‌بندی 3 سطح است.'
                        ]);
                }
            }

            $validated['is_active'] = $request->boolean('is_active', true);

            Category::create($validated);

            DB::commit();

            return redirect()
                ->route('admin.categories.index')
                ->with('success','دسته‌بندی با موفقیت ایجاد شد.');

        } catch (\Throwable $e) {

            DB::rollBack();

            Log::error('Category Store Error',[
                'message'=>$e->getMessage()
            ]);

            return back()
                ->withInput()
                ->with('error','خطا در ایجاد دسته‌بندی.');
        }
    }

    public function show(Category $category): View
    {
        $category->load([
            'parent',
            'children' => function ($query) {
                $query->withCount('products')
                    ->orderBy('sort_order');
            },
            'products' => function ($query) {
                $query->with(['supplier','primaryImage'])
                    ->latest()
                    ->limit(20);
            }
        ]);

        $productsCount = $category->products()->count();

        return view('admin.categories.show', compact('category','productsCount'));
    }

    public function edit(Category $category): View
    {
        $parentCategories = Category::whereNull('parent_id')
            ->where('id','!=',$category->id)
            ->with(['children'=>function($query) use ($category){
                $query->where('id','!=',$category->id)
                    ->orderBy('sort_order');
            }])
            ->orderBy('sort_order')
            ->get();

        return view('admin.categories.edit', compact('category','parentCategories'));
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $validated = $request->validate([
            'name' => [
                'required','string','max:255',
                Rule::unique('categories','name')->ignore($category->id)
            ],
            'parent_id' => ['nullable','exists:categories,id'],
            'description' => ['nullable','string','max:1000'],
            'icon' => ['nullable','string', Rule::in(\App\Support\CategoryIcon::allowedValues())],
            'sort_order' => ['integer','min:0'],
            'is_active' => ['boolean'],
            'seo_title' => ['nullable','string','max:60'],
            'seo_description' => ['nullable','string','max:160'],
            'seo_keywords' => ['nullable','string','max:255'],
        ]);

        if (($validated['parent_id'] ?? null) == $category->id) {
            return back()
                ->withInput()
                ->withErrors([
                    'parent_id'=>'یک دسته‌بندی نمی‌تواند والد خودش باشد.'
                ]);
        }

        try {

            DB::beginTransaction();

            $validated['is_active'] = $request->boolean('is_active', true);

            $category->update($validated);

            DB::commit();

            return redirect()
                ->route('admin.categories.index')
                ->with('success','دسته‌بندی با موفقیت بروزرسانی شد.');

        } catch (\Throwable $e) {

            DB::rollBack();

            Log::error('Category Update Error',[
                'message'=>$e->getMessage()
            ]);

            return back()
                ->withInput()
                ->with('error','خطا در بروزرسانی دسته‌بندی.');
        }
    }

    public function destroy(Category $category): RedirectResponse
    {
        if ($category->children()->exists()) {
            return back()->with('error','ابتدا زیر دسته‌ها را حذف کنید.');
        }

        if ($category->products()->exists()) {
            return back()->with('error','این دسته‌بندی دارای محصول است.');
        }

        try {

            DB::beginTransaction();

            $category->delete();

            DB::commit();

            return redirect()
                ->route('admin.categories.index')
                ->with('success','دسته‌بندی حذف شد.');

        } catch (\Throwable $e) {

            DB::rollBack();

            Log::error('Category Delete Error',[
                'message'=>$e->getMessage()
            ]);

            return back()->with('error','خطا در حذف دسته‌بندی.');
        }
    }

    public function reorder(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'order'=>'required|array',
            'order.*.id'=>'required|exists:categories,id',
            'order.*.sort_order'=>'required|integer|min:0'
        ]);

        try {

            DB::beginTransaction();

            foreach ($validated['order'] as $item) {

                Category::findOrFail($item['id'])->update(['sort_order' => $item['sort_order']]);
            }

            DB::commit();

            return back()->with('success','ترتیب دسته‌بندی‌ها بروزرسانی شد.');

        } catch (\Throwable $e) {

            DB::rollBack();

            Log::error('Category Reorder Error',[
                'message'=>$e->getMessage()
            ]);

            return back()->with('error','خطا در بروزرسانی ترتیب.');
        }
    }
}
