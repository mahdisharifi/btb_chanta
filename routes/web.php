<?php
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaymentCallbackController;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Web\HomeController;
use App\Http\Controllers\Web\ProductController;
use App\Http\Controllers\Web\CategoryController;
use App\Http\Controllers\Web\SearchController;
use App\Http\Controllers\Web\SupplierPublicController;
use App\Models\Slider;
use Illuminate\Http\Request;
use Livewire\Volt\Volt;
use App\Http\Controllers\SitemapController;


Route::get('/sitemap.xml',            [SitemapController::class, 'index'])->name('sitemap.index');
Route::get('/sitemap-products.xml',   [SitemapController::class, 'products'])->name('sitemap.products');
Route::get('/sitemap-categories.xml', [SitemapController::class, 'categories'])->name('sitemap.categories');
Route::get('/sitemap-static.xml',     [SitemapController::class, 'static'])->name('sitemap.static');

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// ============================================================================
// 🧪 Development & Testing Routes
// ============================================================================

if (app()->environment('local')) {
    Route::get('/force-login/{id}', function ($id) {
        $user = \App\Models\User::find($id);

        if (!$user) {
            return 'کاربری با این آی‌دی یافت نشد.';
        }

        Auth::login($user);

        return '<div style="text-align:center; padding:50px;">
                    <h2>ورود موفقیت‌آمیز بود</h2>
                    <a href="/dashboard">رفتن به داشبورد</a>
                </div>';
    })->name('dev.force-login1');

    Route::middleware(['can:super_admin'])->group(function() {
        Route::get('/test-blade', function () {
            return view('test-component');
        })->name('test.blade');

// مرحله 1: نمایش فرم ورود کد امنیتی
        Route::get('/dev/force-login', function () {
            if (session('dev_authenticated')) {
                return redirect()->route('dev.force-login.select');
            }

            return view('dev.force-login-auth');
        })->name('dev.force-login');

// مرحله 2: بررسی کد امنیتی
        Route::post('/dev/force-login/verify', function (Illuminate\Http\Request $request) {
            $secretCode = config('app.dev_force_login_code', 'CHANTA2026'); // کد پیش‌فرض

            $request->validate([
                'code' => 'required|string'
            ], [
                'code.required' => 'وارد کردن کد الزامی است.'
            ]);

            if ($request->code !== $secretCode) {
                return back()->withErrors(['code' => 'کد امنیتی اشتباه است.'])->withInput();
            }

            session(['dev_authenticated' => true]);
            return redirect()->route('dev.force-login.select');
        })->name('dev.force-login.verify');

// مرحله 3: انتخاب کاربر
        Route::get('/dev/force-login/select', function () {
            if (!session('dev_authenticated')) {
                return redirect()->route('dev.force-login');
            }

            $users = \App\Models\User::with('roles')->orderBy('id', 'desc')->get();
            return view('dev.force-login-select', compact('users'));
        })->name('dev.force-login.select');
// مرحله 4: اجرای لاگین
        Route::post('/dev/force-login/execute/{id}', function ($id) {
            if (!session('dev_authenticated')) {
                return redirect()->route('dev.force-login');
            }

            $user = \App\Models\User::with('roles')->find($id);

            if (!$user) {
                return back()->with('error', 'کاربری با این آی‌دی یافت نشد.');
            }

            Auth::login($user);
            session()->forget('dev_authenticated'); // پاک‌سازی احراز هویت dev

            $roles = $user->roles->pluck('display_name')->implode('، ') ?: 'بدون نقش';
            $permissions = $user->getAllPermissions()->pluck('display_name')->take(10)->implode('، ');
            if ($user->getAllPermissions()->count() > 10) {
                $permissions .= ' و ' . ($user->getAllPermissions()->count() - 10) . ' مورد دیگر';
            }
            return view('dev.force-login-success', compact('user', 'roles', 'permissions'));
        })->name('dev.force-login.execute');
    });
}
// ============================================================================
// 🔐 Authentication Routes
// ============================================================================

require __DIR__.'/auth.php';

// ============================================================================
// 🌐 Public Routes
// ============================================================================

// Home
Route::get('/', [HomeController::class, 'index'])->name('home');
        Route::get('/search', [SearchController::class, 'index'])->name('search');

// Products
Route::prefix('products')->name('products.')->group(function () {
    Route::get('/', [ProductController::class, 'index'])->name('index');
    Route::get('/filters/category-counts', [ProductController::class, 'getCategoryCounts'])->name('category-counts');
    Route::get('/filters/suppliers', [ProductController::class, 'getFilteredSuppliers'])->name('filtered-suppliers');
    Route::get('/{product:slug}', [ProductController::class, 'show'])->name('show');
});

// Categories
Route::prefix('categories')->name('categories.')->group(function () {
    Route::get('/', [CategoryController::class, 'index'])->name('index');
    Route::get('/{category:slug}', [CategoryController::class, 'show'])->name('show');
});

// Suppliers (Public)
Route::prefix('suppliers')->name('suppliers.')->group(function () {
    Route::get('/', [SupplierPublicController::class, 'index'])->name('index');
    Route::get('/profile/{supplier}', [SupplierPublicController::class, 'showById'])->name('show.legacy');
    Route::get('/{slug}', \App\Livewire\Web\SupplierProducts::class)->name('show');
});



// Static Pages
Route::view('/about', 'pages.about')->name('about');
Route::view('/contact', 'pages.contact')->name('contact');
Route::view('/terms', 'pages.terms')->name('terms');
Route::view('/privacy', 'pages.privacy')->name('privacy');

// Slider Click Tracking
Route::post('/sliders/{slider}/click', function (Slider $slider) {
    $slider->increment('clicks');
    return response()->json(['success' => true]);
})->middleware('throttle:30,1')->name('sliders.click');

// ============================================================================
// 🔒 Authenticated Routes
// ============================================================================

Route::middleware(['auth', 'verified'])->group(function () {
    // Dashboard Redirect (Role-based)
    Route::get('/dashboard', function () {
        $user = auth()->user();

        return match(true) {
            $user->hasRole('super_admin') => redirect()->route('admin.dashboard'),
            $user->hasRole('supplier') || $user->hasRole('regular_supplier') => redirect()->route('supplier.dashboard'),
            $user->hasRole('retailer_premium') || $user->hasRole('retailer_basic')
            => redirect()->route('retailer.dashboard'),
            $user->hasRole('end_user') => redirect()->route('user.role-selection'),
            default => redirect()->route('home')
        };

    })->name('dashboard');
});

// ⚠️ مسیرهای /admin و /admin/seo به routes/admin.php منتقل شده‌اند.
// ⚠️ مسیرهای /supplier در routes/supplier.php هستند.
// ⚠️ مسیرهای /retailer در routes/retailer.php هستند.
/*
|--------------------------------------------------------------------------
| Auth Routes
|--------------------------------------------------------------------------
*/

//// Login
//Route::get('/login', function () {
//    return view('auth.login');
//})->name('login');
//
//Route::post('/login', function (Request $request) {
//    $credentials = $request->validate([
//        'email' => ['required', 'email'],
//        'password' => ['required'],
//    ]);
//
//    if (Auth::attempt($credentials, $request->filled('remember'))) {
//        $request->session()->regenerate();
//
//        $user = Auth::user();
//        if ($user->hasRole('super_admin')) {
//            return redirect()->route('admin.dashboard');
//        }
//        if ($user->hasRole('supplier')) {
//            return redirect()->route('supplier.dashboard');
//        }
//        if ($user->hasRole(['retailer_premium', 'retailer_basic'])) {
//            return redirect()->route('retailer.dashboard');
//        }
//        return redirect()->route('home');
//    }
//
//    return back()->withErrors([
//        'email' => 'اطلاعات وارد شده نادرست است.',
//    ])->onlyInput('email');
//})->name('login.attempt');
//
//// Logout
//Route::post('/logout', function (Request $request) {
//    Auth::guard('web')->logout();
//
//    $request->session()->invalidate();
//    $request->session()->regenerateToken();
//
//    return redirect('/');
//})->name('logout');
//
//// Register (if needed)
//Route::get('/register', function () {
//    return view('auth.register');
//})->name('register');
//
//Route::post('/register', function (Request $request) {
//    $data = $request->validate([
//        'name' => ['required', 'string', 'max:255'],
//        'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
//        'password' => ['required', 'string', 'min:8', 'confirmed'],
//    ]);
//
//    $user = \App\Models\User::create([
//        'name' => $data['name'],
//        'email' => $data['email'],
//        'password' => bcrypt($data['password']),
//    ]);
//
//    Auth::login($user);
//
//    return redirect()->route('home');
//})->name('register.store');

// Admin routes
//require __DIR__.'/admin.php';

Volt::route('/test-volt', 'test-page')->name('test.volt');
Route::any('/payment/verify', [PaymentController::class, 'verify'])->name('payment.verify');
Route::get('/payment/callback', [PaymentCallbackController::class, 'verify'])->name('payment.callback');

// Catalog Progress Tracking (استفاده از Cache به جای Session)
Route::middleware(['auth'])->get('/catalog/progress/{key}', function ($key) {
    $progress = \Illuminate\Support\Facades\Cache::get($key, ['percent' => 0, 'message' => 'آماده‌سازی...']);
    return response()->json($progress);
})->name('catalog.progress');
