# آمادگی استقرار سرویس صوتی

## وضعیت استقرار

این تغییرات محیط سرور `noctra.ir` را عوض نمی‌کنند. سرویس یک فرایند FastAPI است و event bus، Sessionها، ASR queue و Piper slots در حافظهٔ همان فرایند قرار دارند.

برای نسخهٔ فعلی، یک Uvicorn worker اجرا کن. اجرای چند worker باعث می‌شود هرکدام یک Piper و ASR worker داشته باشند و مجموع آن‌ها از ظرفیت CPU فراتر رود؛ همچنین reconnect/session state مشترک نیست. اگر چند worker یا چند میزبان لازم شدند، ابتدا sticky routing یا broker و سهمیه‌بندی workerها را اضافه کن.

## HTTPS، WebSocket و stream

- میکروفون مرورگر عمومی به secure context نیاز دارد؛ `https://noctra.ir` از قبل SSL دارد.
- Nginx باید `Upgrade` و `Connection` را برای WebSocket عبور دهد، buffering را برای PCM/SSE غیرفعال کند و timeout طولانی کافی داشته باشد.
- upstream اپ فقط روی loopback (`127.0.0.1:8787`) در دسترس باشد.
- محدودیت body، rate limiting و سقف تعداد WebSocketها باید متناسب با ظرفیت server افزوده و با load test سنجیده شوند.

## اعتبار API

در نسخهٔ فعلی مرورگر مستقیم به API انتخاب‌شدهٔ کاربر وصل می‌شود و bearer token در حافظهٔ صفحه است؛ token در `localStorage` یا لاگ پایتون ذخیره نمی‌شود. برای حساب مشترک سرور یا کاربران نهایی، کلید provider را در backend نگه‌دار و endpointهای احراز هویت/سهمیه را اضافه کن. این انتقال در این مأموریت انجام نشده است.

## سرویس و shutdown

Lifespan برنامه یک Piper slot و یک ASR worker را بالا می‌آورد و در shutdown، صف‌ها را می‌بندد و jobهای pending را پاک می‌کند. چون inferenceهای native قابل‌کشتن نیستند، shutdown ممکن است تا پایان deadline chunk Piper طول بکشد؛ ASR nativeِ گیرکرده بعد از deadline به‌صورت امن قابل‌لغو نیست و می‌تواند worker را اشغال نگه دارد.

## بررسی قبل از انتشار

1. مدل‌ها و وزن‌های محلی در مسیر مورد انتظار هستند و به rsync عادی کد اضافه نمی‌شوند.
2. فقط یک Uvicorn worker فعال است.
3. `GET /api/status` تعداد worker و ظرفیت صف مورد انتظار را نشان می‌دهد.
4. WebSocket میکروفون، PCM streaming، timeout، قطع اتصال و barge-in روی دامنهٔ HTTPS آزمایش می‌شود.
5. ASR واقعی + Piper واقعی + barge-in روی همان ۴-core/۶-GB سرور benchmark می‌شود؛ تست محلی ۲-core را به‌عنوان benchmark سرور جا نزن.
6. مصرف RAM، swap، CPU، صف و error rate در تست هم‌زمانی ثبت شود.

## بازگشت

فایل‌های تغییرکردهٔ این مأموریت در پوشهٔ مستقل `ai-agent/` هستند. برای rollback، نسخهٔ پشتیبان همان فایل‌های `ai-agent` را برگردان و سرویس را با یک worker restart کن؛ فایل‌های Laravel و وزن‌های مدل تغییری نکرده‌اند.
