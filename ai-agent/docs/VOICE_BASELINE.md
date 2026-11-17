# وضعیت پایهٔ موتور مکالمهٔ صوتی

تاریخ اجرای این گزارش: ۲۰۲۶-۱۱-۱۶. این گزارش baseline قبل از تغییرات جدید Piper نیست؛ بخشی از وضعیت قبلی از گزارش‌های قبلی پروژه استخراج شده و اندازه‌گیری‌های تازه فقط برای اجزایی است که صریحاً مشخص شده‌اند.

## مسیر فعلی

```text
میکروفون مرورگر → PCM mono/16 kHz/20 ms → WebSocket و VAD
  → صف ASR محدود و یک worker → Partial نسخه‌دار / Final قطعی
  → مدل متنی OpenAI-compatible از مرورگر → SSE
  → قطعه‌بندی گفتار → یک Piper worker → PCM سطری
  → Web Audio با بافر برنامه‌ریزی‌شدهٔ ۱۲۰ ms
```

Partialها برای پیش‌نمایش‌اند؛ تنها متن Final به گفت‌وگوی عادی فرستاده می‌شود. پردازش احتمالی از Stable Partial فقط با `ENABLE_SPECULATIVE_LLM=true` فعال می‌شود و پیش‌فرض خاموش است.

## سخت‌افزار و مدل‌های محلی

- محیط اندازه‌گیری: ۲ هستهٔ CPU قابل‌مشاهده، حدود ۱۴ GiB RAM، Linux، Python 3.12.
- Piper: `models/tts/piper-fa/fa_IR-mana-medium.onnx`، نمونه‌برداری ۲۲٬۰۵۰ Hz، mono، PCM 16-bit.
- ASR: faster-whisper `small` در این workspace کامل است؛ بسته به نصب وابستگی‌ها، Taraneh-WBase هم ممکن است انتخاب شود.
- تعداد Piper worker: یک. تعداد ASR worker: یک؛ هر دو inference سنگین را خارج از asyncio event loop اجرا می‌کنند.

## داده‌های قبلی که حفظ شده‌اند

`PIPER_TTS_UPGRADE.md` نتایج یک benchmark قدیمی‌تر را ثبت می‌کند: ۲۷ تولید گرم برای ۳ preset و میانهٔ اولین PCM در preset پیش‌فرض ۱٬۷۶۱ ms. کورپس ۱۰۰ جمله‌ای همان گزارش میانهٔ ۱٬۶۴۰ ms و P95 برابر ۴٬۸۳۰ ms برای اولین PCM دارد. این دو آزمایش از مسیر قدیمی و بدون Piper Request Scheduler بوده‌اند و نباید با اعداد worker جدید یکی گرفته شوند.

## Piper Request Scheduler، اندازه‌گیری جدید

۱۲ درخواست پیاپی با صدای محلی واقعی، worker واحد و مدل گرم اجرا شدند. دستگاه ۲ هستهٔ CPU داشت. این اعداد از ثبت job تا اولین PCM هستند، نه زمان شنیده‌شدن در مرورگر:

| Metric | P50 | P95 | Maximum | نمونه |
|---|---:|---:|---:|---:|
| صف Scheduler | ۰٫۱ ms | ۱٫۱ ms | ۲٫۳ ms | ۱۲ |
| اولین PCM Piper | ۷۱۳٫۹ ms | ۹۶۶٫۸ ms | ۱٬۰۹۰٫۴ ms | ۱۲ |
| پایان تولید کامل | ۲٬۵۰۳٫۵ ms | ۳٬۳۹۰٫۲ ms | ۳٬۴۹۴٫۸ ms | ۱۲ |

مدت صوت P50 برابر ۵٫۸ ثانیه، peak RSS فرایند ۳۱۹٫۴ MiB و CPU time مجموع ۳۳٫۸۴۱ ثانیه بود. این run ترتیبی بود و هم‌زمانی ASR/Whisper، LLM، شبکه و مرورگر را پوشش نمی‌دهد. مقادیر هر نمونه در `/tmp/ai-agent-piper-slot-benchmark.json` ثبت شده است.

## خط مبنای واقعی ASR و مکالمهٔ دوبلکس

تلاش‌های محلی برای decode واقعی faster-whisper از سقف چنددقیقه‌ای گذشت و داخل `CTranslate2 model.encode` ماند؛ برای محافظت از سیستم متوقف شد. در اجرای محدود هم‌زمان، Piper واقعی اولین PCM را در ۶۵۲٫۲ ms داد و barge-in نسل قدیمی را بست، اما ASR منتخب Taraneh-WBase هنگام آماده‌سازی CTranslate2 با نبود `tokenizer.json` خطا داد و fallback تا deadline بیرونی ۴۵ ثانیه‌ای تمام نشد. deadline داخلی ASR در ۲۵ ثانیه فعال شد و نتیجهٔ دیررس منتشر نشد. بنابراین تأخیر ASR واقعی هنوز اندازه‌گیری نشده است:

| معیار | P50 | P95 | نمونه | وضعیت |
|---|---:|---:|---:|---|
| Capture تا اولین Partial واقعی Whisper | — | — | ۰ | اندازه‌گیری معتبر نداریم |
| Speech end تا Final ASR واقعی | — | — | ۰ | decode واقعی روی میزبان آزمایش کامل نشد |
| Piper واقعی + ASR واقعی + barge-in | — | — | ۰ کامل | Piper/barge-in pass؛ ASR تا ۴۵ s تمام نشد |
| Piper واقعی + lane آزمایشی ASR + barge-in | — | — | ۰ latency distribution | تست هم‌پوشانی خودکار با ASR Mock پاس است |
| شروع barge-in تا توقف شنیداری مرورگر | — | — | ۰ | نیازمند آزمون مرورگر/میکروفون واقعی |

هیچ latency ترکیبی یا عدد P50/P95 برای این مسیر حدس زده نشده است. آزمون‌های ASR در مجموعهٔ خودکار Mock هستند؛ یک تست مجزا Piper واقعی را با یک lane آزمایشی ASR هم‌زمان می‌کند.

## مرجع اجرای قابل‌تکرار

از پوشهٔ `ai-agent`:

```bash
./.venv/bin/python -m unittest discover -s tests -p 'test_*.py' -v
node --test tests/*.test.cjs
./.venv/bin/python tools/benchmark_piper.py --repeats 3
```

برای عددهای واقعی ASR، همین benchmark باید روی همان نسخهٔ مدل و همان CPU هدف، بعد از آماده‌شدن inference موفق، اجرا و نتایج این جدول تکمیل شود. فایل‌های صوتی یا متن کاربر نباید وارد telemetry شوند.
