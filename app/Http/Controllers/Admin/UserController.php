<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Gate; // 👈 اضافه شدن Gate
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class UserController extends Controller
{
    use AuthorizesRequests;

    // 👈 متد __construct به طور کامل حذف شد

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', User::class); // 👈 بررسی سطح دسترسی

        $query = User::query()->with('roles');

        if ($request->filled('q')) {
            $search = $request->q;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role')) {
            $query->role($request->role);
        }

        if ($request->filled('status')) {
            if ($request->status === 'active') {
                $query->where('is_active', true);
            } elseif ($request->status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        $users = $query->latest()->paginate(20)->withQueryString();

        $roles = Role::all();

        $stats = [
            'total' => User::count(),
            'admins' => User::role('super_admin')->count(),
            'suppliers' => User::role('supplier')->count(),
            'retailers_premium' => User::role('retailer_premium')->count(),
            'retailers_basic' => User::role('retailer_basic')->count(),
            'end_users' => User::role('end_user')->count(),
        ];

        return view('admin.users.index', compact('users', 'roles', 'stats'));
    }

    public function create(): View
    {
        Gate::authorize('create', User::class); // 👈 بررسی سطح دسترسی

        $roles = Role::all();

        return view('admin.users.create', compact('roles'));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', User::class); // 👈 بررسی سطح دسترسی

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', 'exists:roles,name'],
            'is_active' => ['boolean'],
        ], [
            'name.required' => 'نام کاربر الزامی است.',
            'email.required' => 'ایمیل الزامی است.',
            'email.unique' => 'این ایمیل قبلاً ثبت شده است.',
            'password.required' => 'رمز عبور الزامی است.',
            'password.min' => 'رمز عبور باید حداقل 8 کاراکتر باشد.',
            'password.confirmed' => 'تأیید رمز عبور مطابقت ندارد.',
            'role.required' => 'نقش کاربر الزامی است.',
            'role.exists' => 'نقش انتخابی معتبر نیست.',
        ]);

        try {
            DB::beginTransaction();

            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'is_active' => $request->boolean('is_active', true),
            ]);

            $user->assignRole($validated['role']);

            DB::commit();

            return redirect()
                ->route('admin.users.index')
                ->with('success', 'کاربر با موفقیت ایجاد شد.');

        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('User Store Error', [
                'email' => $validated['email'] ?? null,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'خطا در ایجاد کاربر. لطفاً دوباره تلاش کنید.');
        }
    }

    public function show(User $user): View
    {
        Gate::authorize('view', $user); // 👈 بررسی سطح دسترسی

        $user->load('roles');

        return view('admin.users.show', compact('user'));
    }

    public function edit(User $user): View
    {
        Gate::authorize('update', $user); // 👈 بررسی سطح دسترسی

        $user->load('roles');
        $roles = Role::all();

        return view('admin.users.edit', compact('user', 'roles'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('update', $user); // 👈 بررسی سطح دسترسی

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'is_active' => ['boolean'],
        ], [
            'name.required' => 'نام کاربر الزامی است.',
            'email.required' => 'ایمیل الزامی است.',
            'email.email' => 'فرمت ایمیل معتبر نیست.',
            'email.unique' => 'این ایمیل قبلاً ثبت شده است.',
            'password.min' => 'رمز عبور باید حداقل 8 کاراکتر باشد.',
            'password.confirmed' => 'تأیید رمز عبور مطابقت ندارد.',
        ]);

        try {
            DB::beginTransaction();

            $user->update([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'is_active' => $request->boolean('is_active'),
            ]);

            if (!empty($validated['password'])) {
                $user->update([
                    'password' => Hash::make($validated['password']),
                ]);
            }

            DB::commit();

            return redirect()
                ->route('admin.users.index')
                ->with('success', 'کاربر با موفقیت بروزرسانی شد.');

        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('User Update Error', [
                'user_id' => $user->id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'خطا در بروزرسانی کاربر.');
        }
    }

    public function destroy(User $user): RedirectResponse
    {
        Gate::authorize('delete', $user); // 👈 بررسی سطح دسترسی

        if (auth()->id() === $user->id) {
            return back()->with('error', 'شما نمی‌توانید حساب کاربری خود را حذف کنید.');
        }

        try {
            DB::beginTransaction();

            $user->delete();

            DB::commit();

            return redirect()
                ->route('admin.users.index')
                ->with('success', 'کاربر با موفقیت حذف شد.');

        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('User Delete Error', [
                'user_id' => $user->id,
                'message' => $e->getMessage(),
            ]);

            return back()->with('error', 'خطا در حذف کاربر.');
        }
    }

    public function toggleStatus(User $user): RedirectResponse
    {
        Gate::authorize('update', $user); // 👈 بررسی سطح دسترسی (برای تغییر وضعیت)

        if (auth()->id() === $user->id) {
            return back()->with('error', 'شما نمی‌توانید وضعیت حساب خود را تغییر دهید.');
        }

        try {
            DB::beginTransaction();

            $user->update([
                'is_active' => !$user->is_active,
            ]);

            DB::commit();

            $statusText = $user->is_active ? 'فعال' : 'غیرفعال';

            return back()->with('success', "وضعیت کاربر با موفقیت به {$statusText} تغییر یافت.");

        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('User Toggle Status Error', [
                'user_id' => $user->id,
                'message' => $e->getMessage(),
            ]);

            return back()->with('error', 'خطا در تغییر وضعیت کاربر.');
        }
    }

    public function changeRole(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('update', $user); // 👈 بررسی سطح دسترسی (برای تغییر نقش)

        $validated = $request->validate([
            'role' => ['required', 'exists:roles,name'],
        ], [
            'role.required' => 'نقش کاربر الزامی است.',
            'role.exists' => 'نقش انتخابی معتبر نیست.',
        ]);

        if (auth()->id() === $user->id) {
            return back()->with('error', 'شما نمی‌توانید نقش خود را تغییر دهید.');
        }

        try {
            DB::beginTransaction();

            $user->syncRoles([$validated['role']]);

            DB::commit();

            return back()->with('success', 'نقش کاربر با موفقیت تغییر یافت.');

        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('User Change Role Error', [
                'user_id' => $user->id,
                'role' => $validated['role'] ?? null,
                'message' => $e->getMessage(),
            ]);

            return back()->with('error', 'خطا در تغییر نقش کاربر.');
        }
    }

    public function impersonate(User $user): RedirectResponse
    {
        // 👈 در اینجا از متد view استفاده شده، در صورت نیاز می‌توانید یک متد مجزا در Policy برای این کار تعریف کنید
        Gate::authorize('view', $user);

        if (auth()->id() === $user->id) {
            return back()->with('error', 'شما نمی‌توانید خودتان را شبیه‌سازی کنید.');
        }

        try {
            session(['impersonating_from' => auth()->id()]);

            auth()->login($user);

            return redirect()
                ->route('dashboard')
                ->with('success', 'شبیه‌سازی کاربر با موفقیت انجام شد.');

        } catch (\Throwable $e) {
            Log::error('User Impersonation Error', [
                'target_user_id' => $user->id,
                'message' => $e->getMessage(),
            ]);

            return back()->with('error', 'خطا در شبیه‌سازی کاربر.');
        }
    }
}
