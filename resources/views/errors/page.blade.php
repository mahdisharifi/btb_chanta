@php
    $status = (int) ($status ?? (isset($exception) ? $exception->getStatusCode() : 500));
    $maintenance = $status === 503 && (($maintenancePreview ?? false) || app()->isDownForMaintenance());
    $messages = [
        400 => ['درخواست قابل پردازش نیست', 'لطفاً آدرس و اطلاعات واردشده را بررسی کنید و دوباره از صفحه اصلی ادامه دهید.'],
        401 => ['ابتدا وارد حساب خود شوید', 'برای مشاهده این بخش لازم است وارد حساب کاربری خود شوید.'],
        403 => ['دسترسی به این بخش امکان‌پذیر نیست', 'حساب شما مجوز مشاهده این صفحه یا انجام این عملیات را ندارد.'],
        404 => ['این صفحه پیدا نشد', 'ممکن است آدرس تغییر کرده باشد یا صفحه دیگر در دسترس نباشد. از صفحه اصلی مسیرتان را ادامه دهید.'],
        405 => ['این روش ارسال پشتیبانی نمی‌شود', 'لطفاً صفحه موردنظر را از منوهای سایت باز کنید و دوباره تلاش کنید.'],
        408 => ['زمان پاسخ‌گویی به پایان رسید', 'ارتباط شما به‌موقع کامل نشد. اتصال اینترنت را بررسی کنید و دوباره تلاش کنید.'],
        409 => ['اطلاعات این صفحه تغییر کرده است', 'برای جلوگیری از ثبت اطلاعات قدیمی، صفحه را دوباره باز کنید و آخرین وضعیت را بررسی کنید.'],
        410 => ['این صفحه دیگر در دسترس نیست', 'محتوای موردنظر حذف شده یا دسترسی به آن پایان یافته است.'],
        413 => ['حجم اطلاعات بیش از حد مجاز است', 'حجم فایل یا اطلاعات ارسالی را کاهش دهید و دوباره تلاش کنید.'],
        419 => ['زمان اعتبار صفحه تمام شده است', 'برای حفظ امنیت حساب، اعتبار فرم پایان یافته است. صفحه را دوباره باز کنید؛ ممکن است لازم باشد اطلاعات فرم را دوباره وارد کنید.'],
        422 => ['اطلاعات نیاز به بررسی دارد', 'برخی اطلاعات ارسالی معتبر نیست. فرم را دوباره باز کنید و مقادیر واردشده را بررسی کنید.'],
        429 => ['کمی صبر کنید', 'تعداد درخواست‌ها در مدت کوتاه زیاد بوده است. کمی بعد دوباره تلاش کنید.'],
        500 => ['در انجام درخواست مشکلی پیش آمد', 'درخواست شما کامل نشد. لطفاً کمی بعد دوباره تلاش کنید.'],
        502 => ['ارتباط با سرویس برقرار نشد', 'یکی از سرویس‌ها موقتاً پاسخ نمی‌دهد. لطفاً کمی بعد دوباره تلاش کنید.'],
        503 => ['سرویس موقتاً در دسترس نیست', 'لطفاً کمی بعد دوباره مراجعه کنید. از شکیبایی شما سپاسگزاریم.'],
        504 => ['پاسخ سرویس کمی طول کشید', 'در مهلت مقرر پاسخی دریافت نشد. لطفاً کمی بعد وضعیت درخواست را بررسی کنید.'],
    ];
    [$title, $description] = $maintenance
        ? ['در حال بهبود تجربه شما هستیم', 'سایت برای انجام تعمیرات و به‌روزرسانی، موقتاً از دسترس خارج شده است. پس از پایان کار، خدمات دوباره در دسترس شما خواهد بود.']
        : ($messages[$status] ?? ($status >= 500
            ? ['سرویس با مشکلی روبه‌رو شده است', 'لطفاً کمی بعد دوباره تلاش کنید.']
            : ['امکان انجام این درخواست نیست', 'لطفاً آدرس و اطلاعات درخواست را بررسی کنید و از صفحه اصلی ادامه دهید.']));
    $safePath = '/'.ltrim(str_replace(['\\', "\r", "\n"], '', request()->path()), '/');
@endphp
<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="color-scheme" content="light">
    <title>{{ $title }} | {{ $maintenance ? 'به‌روزرسانی سایت' : 'خطای '.$status }}</title>
    <style>
        @font-face{font-family:Vazir;src:url('/fonts/vazir.woff2') format('woff2');font-display:swap}
        *{box-sizing:border-box}body{margin:0;background:#f5f7fb;color:#182337;font-family:Vazir,Tahoma,sans-serif;min-height:100vh;display:flex;flex-direction:column}
        header{padding:28px max(24px,6vw);display:flex;align-items:center;justify-content:space-between;gap:16px}header a{color:#243c62;text-decoration:none;font-weight:bold}.badge{font-size:12px;color:#54647c;display:flex;align-items:center;gap:8px}.dot{height:8px;width:8px;border-radius:50%;background:{{ $maintenance ? '#d99816' : '#8293ac' }}}
        main{flex:1;display:grid;place-items:center;padding:24px}.card{width:min(100%,950px);display:grid;grid-template-columns:1.3fr 1fr;gap:48px;align-items:center;background:#fff;border:1px solid #e4eaf3;border-radius:28px;padding:64px;box-shadow:0 18px 70px #23365308}
        .eyebrow{font-size:12px;font-weight:bold;color:#526784;margin:0 0 14px}h1{font-size:clamp(23px,3.5vw,32px);line-height:1.7;margin:0 0 16px;letter-spacing:-.6px}.description{font-size:14px;color:#607088;line-height:2.2;margin:0}.actions{display:flex;gap:12px;flex-wrap:wrap;margin-top:28px}a.button{display:inline-flex;align-items:center;justify-content:center;padding:12px 20px;border-radius:11px;border:1px solid #dce4ef;text-decoration:none;color:#334765;font-size:13px;font-weight:bold}.button.primary{background:#254edb;color:white;border-color:#254edb}a:focus-visible{outline:3px solid #83a0ff;outline-offset:4px}.hint{font-size:12px;line-height:2;color:#718198;margin:22px 0 0}
        .visual{position:relative;aspect-ratio:1;display:grid;place-items:center;border-radius:50%;background:radial-gradient(circle,#e9efff 0%,#f6f8fe 57%,#fff 70%)}.visual:before{content:'';position:absolute;inset:12%;border:1px dashed #cbd8f1;border-radius:50%}.symbol{width:100px;height:100px;color:#4564bc}.code{position:absolute;bottom:8%;background:#fff;border:1px solid #e1e8f5;border-radius:14px;padding:8px 24px;font-size:26px;letter-spacing:3px;color:#52688c;font-weight:bold}.note{margin-top:24px;padding:12px 16px;border-radius:10px;background:#fffaee;color:#886827;font-size:12px;line-height:2}footer{text-align:center;padding:24px;font-size:12px;color:#7a879c}
        @media(max-width:650px){header{padding:20px}.card{grid-template-columns:1fr;padding:30px 24px;gap:24px;text-align:center;border-radius:20px}.visual{grid-row:1;width:190px;margin:auto}.symbol{width:75px;height:75px}.code{font-size:20px;padding:5px 20px}.actions{justify-content:center}main{padding:12px}.badge{font-size:11px}}
    </style>
</head>
<body>
<header><a href="/" aria-label="رفتن به صفحه اصلی">صفحه اصلی</a><span class="badge"><span class="dot" aria-hidden="true"></span>{{ $maintenance ? 'به‌روزرسانی و نگهداری' : 'راهنمای وضعیت درخواست' }}</span></header>
<main>
    <section class="card" aria-labelledby="error-title">
        <div>
            <p class="eyebrow">{{ $maintenance ? 'کمی استراحت، برای بازگشتی بهتر' : 'برای ادامه همراه شما هستیم' }}</p>
            <h1 id="error-title">{{ $title }}</h1>
            <p class="description">{{ $description }}</p>
            <div class="actions">
                @if($maintenance)
                    <a class="button primary" href="{{ $safePath }}">بررسی دوباره دسترسی</a>
                @elseif($status === 401)
                    <a class="button primary" href="/login">ورود به حساب</a>
                    <a class="button" href="/">صفحه اصلی</a>
                @else
                    <a class="button primary" href="/">بازگشت به صفحه اصلی</a>
                    @if(in_array($status, [408, 409, 419, 429, 500, 502, 503, 504]))
                        <a class="button" href="{{ $safePath }}">باز کردن دوباره صفحه</a>
                    @endif
                @endif
            </div>
            @if($maintenance)
                <div class="note">نیازی به اقدام خاصی نیست؛ لطفاً کمی بعد دوباره مراجعه کنید.</div>
            @elseif($status >= 500)
                <p class="hint">اگر در حال ثبت سفارش یا پرداخت بوده‌اید، پیش از تکرار عملیات، وضعیت آن را در حساب خود بررسی کنید.</p>
            @endif
        </div>
        <div class="visual" aria-hidden="true">
            @if($maintenance)
                <svg class="symbol" viewBox="0 0 100 100" fill="none"><rect x="14" y="22" width="72" height="51" rx="9" stroke="currentColor" stroke-width="4"/><path d="M35 85h30M50 74v11M33 45l-8 8 8 8m34-16 8 8-8 8M56 41l-12 24" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/><circle cx="80" cy="23" r="10" fill="#f4bd4f" stroke="white" stroke-width="4"/></svg>
            @else
                <svg class="symbol" viewBox="0 0 100 100" fill="none"><rect x="20" y="12" width="60" height="76" rx="10" stroke="currentColor" stroke-width="4"/><path d="M34 31h32M34 42h21" stroke="currentColor" stroke-width="4" stroke-linecap="round"/><circle cx="50" cy="64" r="11" stroke="currentColor" stroke-width="4"/><path d="M58 72l9 9" stroke="currentColor" stroke-width="4" stroke-linecap="round"/></svg>
            @endif
            <span class="code" dir="ltr">{{ $maintenance ? 'در حال تعمیر' : $status }}</span>
        </div>
    </section>
</main>
<footer>از همراهی و شکیبایی شما سپاسگزاریم.</footer>
</body>
</html>
