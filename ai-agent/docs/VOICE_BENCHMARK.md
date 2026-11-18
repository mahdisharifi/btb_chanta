# بنچمارک و معیارهای مکالمهٔ صوتی

## قاعدهٔ اندازه‌گیری

سرور از `time.perf_counter()` برای eventهای داخلی استفاده می‌کند. مرورگر از `performance.now()` استفاده می‌کند. فقط durationهای محاسبه‌شده در یک محیط با هم مقایسه می‌شوند؛ timestampهای خام دو محیط با هم کم نمی‌شوند. Logها شناسه‌ها و durationها را دارند، اما صوت، transcript، token یا API key ندارند.

`GET /api/status` مقدارهای P50/P95/maximum/sample count را از پنجرهٔ حداکثر ۵۱۲ مشاهده برای هر metric نشان می‌دهد، همراه با counterها، عمق صف‌ها، RSS فعلی/قله و CPU time/درصد process از آخرین خواندن status. این آمار persistence ندارد؛ CPU درصدیِ status اول `null` است و ۱۰۰٪ برابر یک هسته تعریف شده است.

## latencyهایی که instrumentation دارد

| Metric | Clock/source | وضعیت |
|---|---|---|
| Capture → first partial | سرور، از شروع VAD تا partial | instrument شده؛ نمونهٔ real ASR نداریم |
| ASR request duration / queue wait | سرور | instrument شده؛ inference واقعی روی این میزبان کامل نشد |
| Speech end → final ASR | سرور | instrument شده؛ نمونهٔ معتبر real ASR نداریم |
| Piper queue wait / first PCM / generation total | سرور و worker واقعی | با تست/benchmark Piper ثبت می‌شود |
| LLM first token / completion | مرورگر، monotonic | event durationها در metrics جمع می‌شوند؛ واقعیِ Arvan در این validation اجرا نشد |
| first playable PCM | مرورگر، first chunk scheduling | instrument شده؛ با «اولین صدای شنیده‌شده» یکی نیست |
| barge-in → playback stop | مرورگر، یک clock | instrument شده؛ باید در browser/manual run نمونه جمع شود |
| End-to-end response latency | چند بخش و احتمالاً شبکه | هنوز P50/P95 معتبر ندارد |

## نتیجهٔ واقعی موجود

- آزمون‌های خودکار یک Piper واقعی محلی را از مسیر single-worker اجرا کرده‌اند.
- آزمون جداگانه ASR lane آزمایشی را هم‌زمان با Piper واقعی نگه می‌دارد و generation صوتی را پس از اولین PCM قطع می‌کند؛ PCM نسل قدیمی نادیده گرفته می‌شود.
- این آزمون از ASR/Whisper واقعی استفاده نمی‌کند و نباید به‌عنوان موفقیت full-duplex واقعی معرفی شود.
- اجرای محدود واقعی روی ASR منتخب Taraneh-WBase با Piper واقعی، اولین PCM را در ۶۵۲٫۲ ms داد و barge-in را pass کرد، اما ASR با نبود `tokenizer.json` در مسیر CTranslate2 مواجه شد؛ deadline داخلی ۲۵ s رسید و فرایند آزمایشی در ۴۵ s متوقف شد. پس ASR P50/P95 و latency ترکیبی هنوز قابل گزارش نیست.
- گزارش قبلی `PIPER_TTS_UPGRADE.md` شامل medianهای benchmark قدیمی و کورپس است. اعداد آن قبل از Scheduler بوده‌اند.
- بنچمارک Piper Request Scheduler با ۱۲ درخواست warm روی ۲ هسته اجرا شد: صف P50/P95 برابر ۰٫۱/۱٫۱ ms، اولین PCM برابر ۷۱۳٫۹/۹۶۶٫۸ ms، پایان تولید کامل برابر ۲٬۵۰۳٫۵/۳٬۳۹۰٫۲ ms و peak RSS برابر ۳۱۹٫۴ MiB بود. این‌ها خروجی Piper هستند؛ شامل ASR، LLM، شبکه یا مرورگر نیستند. دادهٔ هر نمونه در `/tmp/ai-agent-piper-slot-benchmark.json` است.

## دستورها

```bash
./.venv/bin/python -m unittest discover -s tests -p 'test_*.py' -v
node --test tests/*.test.cjs
./.venv/bin/python tools/benchmark_piper.py --repeats 3
```

برای Full-Duplex واقعی، از utteranceهای کنترل‌شدهٔ ضبط‌شده یا صدای TTS آزمایشی استفاده کن؛ ASR واقعی، Piper واقعی و barge-in مرورگر را هم‌زمان اجرا کن. حداقل ۲۰ turn برای گزارش صدک‌ها پیشنهاد می‌شود. تا آن زمان مقدارها در جدول «اندازه‌گیری نشده» می‌مانند.
