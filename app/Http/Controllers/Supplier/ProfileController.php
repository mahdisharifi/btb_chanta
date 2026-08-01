<?php

namespace App\Http\Controllers\Supplier;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class ProfileController extends Controller
{
    public function edit(): View
    {
        $supplier = Supplier::where('user_id', auth()->id())->firstOrFail();

        return view('supplier.profile.edit', compact('supplier'));
    }

    public function update(Request $request): RedirectResponse
    {
        $supplier = Supplier::where('user_id', auth()->id())->firstOrFail();

        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'company_name_en' => ['nullable', 'string', 'max:255'],
            'brand_name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'phone' => ['nullable', 'string', 'max:20'],
            'mobile' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'tax_id' => ['nullable', 'string', 'max:50'],
            'national_id' => ['nullable', 'string', 'max:50'],
        ], [
            'company_name.required' => 'نام شرکت الزامی است.',
        ]);

        $supplier->update($validated);

        return back()->with('success', 'پروفایل شرکت با موفقیت به‌روزرسانی شد.');
    }
}
