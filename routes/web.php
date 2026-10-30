<?php
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaymentCallbackController;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Contracts\Cache\LockTimeoutException;
use App\Http\Controllers\Web\HomeController;
use App\Http\Controllers\Web\ProductController;
use App\Http\Controllers\Web\ProductPriceSnapshotController;
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

Route::middleware(['auth', 'throttle:60,1'])->get(
    '/products/{product:id}/prices/snapshot',
    ProductPriceSnapshotController::class
)->name('products.price-snapshot');

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
    Route::get('/{slug}', \App\Livewire\Web\SupplierProducts::class)
        ->middleware(\App\Http\Middleware\RedirectLegacySupplierSlug::class)
        ->name('show');
});



// Static Pages
Route::view('/about', 'pages.about')->name('about');
Route::view('/contact', 'pages.contact')->name('contact');
Route::view('/terms', 'pages.terms')->name('terms');
Route::view('/privacy', 'pages.privacy')->name('privacy');

// Slider Click Tracking
Route::post('/sliders/{slider}/click', function (Slider $slider) {
    abort_unless(Slider::active()->whereKey($slider->getKey())->exists(), 404);
    $slider->increment('click_count');
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
            $user->hasRole('admin') => redirect()->route('admin.outsourcing.dashboard'),
            $user->hasRole('outsourcer') => redirect()->route('outsourcer.tasks.index'),
            $user->hasRole('reviewer') => redirect()->route('reviewer.outsourcing.tasks.index'),
            $user->hasRole('finance_manager') => redirect()->route('admin.outsourcing.commissions'),
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

// Catalog Progress Tracking (uses the same file cache store as the Livewire action).
Route::middleware(['auth', 'throttle:30,1'])->get('/catalog/pdf-download/{token}', function (string $token) {
    abort_unless(preg_match('/\A[0-9a-f-]{36}\z/iD', $token) === 1, 404);

    $cacheKey = 'catalog_pdf_download_' . $token;
    $cache = \Illuminate\Support\Facades\Cache::store('file');

    try {
        return $cache->lock($cacheKey . ':claim', 30)->block(5, function () use ($cache, $cacheKey, $token) {
            $download = $cache->get($cacheKey);
            abort_unless(
                is_array($download)
                    && (int) ($download['user_id'] ?? 0) === (int) auth()->id(),
                404,
            );

            $filename = $download['filename'] ?? null;
            abort_unless(
                is_string($filename)
                    && preg_match('/\A(?:catalog|list)-[0-9-]+\.pdf\z/iD', $filename) === 1,
                404,
            );

            $relativePath = 'catalog-pdf-downloads/' . $token . '.pdf';
            $disk = \Illuminate\Support\Facades\Storage::disk('local');
            abort_unless($disk->exists($relativePath), 404);

            // Claim the one-time token before releasing the lock so concurrent requests cannot both pass.
            $cache->forget($cacheKey);

            return response()->download(
                $disk->path($relativePath),
                $filename,
                ['Content-Type' => 'application/pdf'],
            )->deleteFileAfterSend(true);
        });
    } catch (LockTimeoutException) {
        return response('درخواست دانلود هم‌زمان در حال پردازش است؛ دوباره تلاش کنید.', 429)
            ->header('Retry-After', '1');
    }
})->name('catalog.pdf.download');

Route::middleware(['auth'])->get('/catalog/progress/{key}', function (string $key) {
    $progress = \Illuminate\Support\Facades\Cache::store('file')->get($key);

    if (!is_array($progress)) {
        return response()->json([
            'percent' => 0,
            'message' => 'در حال شروع ساخت PDF...',
            'status' => 'pending',
        ], 202);
    }

    abort_unless((int) ($progress['user_id'] ?? 0) === (int) auth()->id(), 404);
    unset($progress['user_id']);

    return response()->json($progress);
})->name('catalog.progress');
