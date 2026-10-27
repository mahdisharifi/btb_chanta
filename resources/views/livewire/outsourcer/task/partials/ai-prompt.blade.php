<aside class="space-y-3 rounded-xl border border-indigo-200 bg-indigo-50 p-4" aria-label="پرامپت استاندارد تولید توضیحات"
       x-data="{
           prompt: '', messages: [], ready: false, loading: false, copied: false, failure: '',
           async load() {
               if (this.loading) return;
               this.loading = true; this.ready = false; this.prompt = ''; this.failure = ''; this.copied = false;
               try {
                   const result = await $wire.generateAIPrompt();
                   this.ready = result.ready; this.prompt = result.prompt; this.messages = result.messages;
               } catch (error) { this.failure = 'دریافت پرامپت انجام نشد؛ دوباره تلاش کنید.'; }
               finally { this.loading = false; }
           },
           async copy() {
               try { await navigator.clipboard.writeText(this.prompt); this.copied = true; }
               catch (error) { this.failure = 'کپی خودکار در دسترس نیست؛ متن پرامپت را انتخاب و کپی کنید.'; }
           }
       }"
       x-init="$watch('tab', value => { if (value === 'content') load() }); if (tab === 'content') load()">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h3 class="font-semibold text-indigo-950">پرامپت استاندارد هوش مصنوعی</h3>
        <button type="button" @click="load()" :disabled="loading" class="rounded-lg border border-indigo-200 bg-white px-3 py-2 text-xs text-indigo-800 disabled:opacity-50">به‌روزرسانی پرامپت</button>
    </div>
    <p class="text-sm leading-7 text-indigo-900">پس از تکمیل ویژگی‌های اجباری، پرامپت را کپی و در ابزار هوش مصنوعی خود وارد کنید. سپس توضیحات کوتاه و کامل خروجی را بررسی و در کادرهای زیر ثبت کنید.</p>
    <p x-show="loading" role="status" class="text-sm text-indigo-800">در حال بررسی ویژگی‌ها و آماده‌سازی پرامپت…</p>
    <div x-cloak x-show="!loading && !ready && messages.length" class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
        <p class="mb-2 font-medium">برای نمایش پرامپت، ویژگی‌ها را تکمیل یا اصلاح کنید:</p>
        <ul class="list-inside list-disc"><template x-for="message in messages" :key="message"><li x-text="message"></li></template></ul>
        <button type="button" @click="tab = 'attributes'" x-show="{{ data_get($task->scope_json, 'attributes') ? 'true' : 'false' }}" class="mt-3 underline">رفتن به ویژگی‌ها</button>
    </div>
    <div x-cloak x-show="ready && !loading" class="space-y-3">
        <label class="block text-sm font-medium text-indigo-950">متن پرامپت
            <textarea readonly x-model="prompt" rows="13" class="mt-2 w-full rounded-lg border-indigo-200 bg-white p-3 text-sm leading-7" dir="rtl"></textarea>
        </label>
        <button type="button" @click="copy()" class="rounded-lg bg-indigo-700 px-4 py-2 text-sm text-white" x-text="copied ? 'پرامپت کپی شد' : 'کپی پرامپت'"></button>
    </div>
    <p x-cloak x-show="failure" x-text="failure" role="alert" class="text-sm text-red-700"></p>
</aside>
