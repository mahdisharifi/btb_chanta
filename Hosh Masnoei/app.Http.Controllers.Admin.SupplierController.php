<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class SupplierController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Supplier::class, 'supplier');
    }

    public function index(Request $request): View
    {
        $query = Supplier::query()
            ->with('user')
            ->withCount('products');

        if ($request->filled('q')) {
            $search = $request->q;
            $query->where(function ($q) use ($search) {
                $q->where('company_name', 'like', "%{$search}%")
                    ->orWhere('brand_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            if ($request->status === 'active') {
                $query->where('is_active', true);
            } elseif ($request->status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        if ($request->filled('verified')) {
            if ($request->verified === 'yes') {
                $query->where('is_verified', true);
            } elseif ($request->verified === 'no') {
                $query->where('is_verified', false);
            }
        }

        if ($request->filled('sort')) {
            if ($request->sort === 'name') {
                $query->orderBy('company_name');
            } elseif ($request->sort === 'products') {
                $query->orderBy('products_count', 'desc');
            }
        } else {
            $query->latest();
        }

        $suppliers = $query->paginate(15)->withQueryString();

        $stats = [
            'total' => Supplier::count(),
            'active' => Supplier::where('is_active', true)->count(),
            'verified' => Supplier::where('is_verified', true)->count(),
            'pending' => Supplier::where('is_verified', false)->count(),
        ];

        return view('admin.suppliers.index', compact('suppliers', 'stats'));
    }

    public function create(): View
    {
        return view('admin.suppliers.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id', 'unique:suppliers,user_id'],
            'company_name' => ['required', 'string', 'max:255', 'unique:suppliers,company_name'],
            'company_name_en' => ['nullable', 'string', 'max:255'],
            'brand_name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'phone' => ['nullable', 'string', 'max:20'],
            'mobile' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255', 'unique:suppliers,email'],
            'website' => ['nullable', 'url', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'tax_id' => ['nullable', 'string', 'max:50'],
            'national_id' => ['nullable', 'string', 'max:50'],
                'is_active' => ['boolean'],
            ],[
                'user_id.required' => 'انتخاب کاربر الزامی است.',
                'company_name.required' => 'نام شرکت الزامی است.',
            ]);

        try {

            DB::beginTransaction();

            $validated['is_active'] = $request->boolean('is_active', true);

            Supplier::create($validated);

            DB::commit();

            return redirect()
                ->route('admin.suppliers.index')
                ->with('success', 'تأمین‌کننده با موفقیت ایجاد شد.');

        } catch (\Throwable $e) {

            DB::rollBack();

            Log::error('Supplier Store Error', [
                'message' => $e->getMessage()
            ]);

            return back()
                ->withInput()
                ->with('error', 'خطا در ایجاد تأمین‌کننده.');
        }
    }

    public function show(Supplier $supplier): View
    {
        $supplier->load([
            'user',
            'products' => function ($query) {
                $query->with([
                    'category',
                    'galleries' => function ($q) {
                        $q->where('is_primary', true);
                    }
                ])
                    ->withCount('retailPrices')
                    ->latest()
                    ->take(20);
            }
        ]);

        $supplier->loadCount('products');

        return view('admin.suppliers.show', compact('supplier'));
    }

    public function edit(Supplier $supplier): View
    {
        $supplier->load('user');

        return view('admin.suppliers.edit', compact('supplier'));
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $validated = $request->validate([
            'company_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('suppliers', 'company_name')->ignore($supplier->id)
            ],
            'company_name_en' => ['nullable', 'string', 'max:255'],
            'brand_name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'phone' => ['nullable', 'string', 'max:20'],
            'mobile' => ['nullable', 'string', 'max:20'],
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('suppliers', 'email')->ignore($supplier->id)
            ],
            'website' => ['nullable', 'url', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'tax_id' => ['nullable', 'string', 'max:50'],
            'national_id' => ['nullable', 'string', 'max:50'],
            'is_active' => ['boolean'],
        ]);

        try {

            DB::beginTransaction();

            $validated['is_active'] = $request->boolean('is_active', $supplier->is_active);

            $supplier->update($validated);

            DB::commit();

            return redirect()
                ->route('admin.suppliers.index')
                ->with('success', 'اطلاعات تأمین‌کننده با موفقیت ویرایش شد.');

        } catch (\Throwable $e) {

            DB::rollBack();

            Log::error('Supplier Update Error', [
                'message' => $e->getMessage()
            ]);

            return back()
                ->withInput()
                ->with('error', 'خطا در بروزرسانی تأمین‌کننده.');
        }
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        if ($supplier->products()->exists()) {
            return back()->with(
                'error',
                'این تأمین‌کننده دارای محصول است. ابتدا محصولات را حذف یا منتقل کنید.'
            );
        }

        try {

            DB::beginTransaction();

            $supplier->delete();

            DB::commit();

            return redirect()
                ->route('admin.suppliers.index')
                ->with('success', 'تأمین‌کننده حذف شد.');

        } catch (\Throwable $e) {

            DB::rollBack();

            Log::error('Supplier Delete Error', [
                'message' => $e->getMessage()
            ]);

            return back()->with('error', 'خطا در حذف تأمین‌کننده.');
        }
    }

    public function verify(Supplier $supplier): RedirectResponse
    {
        $this->authorize('verify', $supplier);

        try {

            DB::beginTransaction();

            $supplier->update([
                'is_verified' => true,
                'verified_at' => now(),
            ]);

            DB::commit();

            return back()->with(
                'success',
                "تأمین‌کننده «{$supplier->company_name}» تأیید شد."
            );

        } catch (\Throwable $e) {

            DB::rollBack();

            Log::error('Supplier Verify Error', [
                'message' => $e->getMessage()
            ]);

            return back()->with('error', 'خطا در تأیید تأمین‌کننده.');
        }
    }

    public function toggleStatus(Supplier $supplier): RedirectResponse
    {
        $this->authorize('update', $supplier);

        try {

            DB::beginTransaction();

            $supplier->update([
                'is_active' => ! $supplier->is_active
            ]);

            DB::commit();

            $status = $supplier->is_active ? 'فعال' : 'غیرفعال';

            return back()->with(
                'success',
                "وضعیت تأمین‌کننده «{$supplier->company_name}» {$status} شد."
            );

        } catch (\Throwable $e) {

            DB::rollBack();

            Log::error('Supplier Status Toggle Error', [
                'message' => $e->getMessage()
            ]);

            return back()->with('error', 'خطا در تغییر وضعیت.');
        }
    }
}

