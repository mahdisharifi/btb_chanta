<div>
    <x-admin.subheader :title="$flowchart && $flowchart->exists ? 'ویرایش فلوچارت' : 'ایجاد فلوچارت جدید'">
        <x-secondary-button wire:navigate href="{{ route('admin.flowcharts.index') }}">
            بازگشت به لیست
        </x-secondary-button>
    </x-admin.subheader>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- فرم ورود اطلاعات -->
        <x-admin.card>
            <form wire:submit="save" class="space-y-6">
                <div>
                    <x-input-label for="title" value="عنوان فلوچارت" />
                    <x-text-input id="title" wire:model="title" type="text" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('title')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="description" value="توضیحات (اختیاری)" />
                    <x-textarea id="description" wire:model="description" class="mt-1 block w-full" rows="3"></x-textarea>
                    <x-input-error :messages="$errors->get('description')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="code" value="کد فلوچارت (Mermaid)" />
                    <!-- debounce برای جلوگیری از رندر پی‌در‌پی و افت پرفورمنس هنگام تایپ -->
                    <x-textarea id="code" wire:model.live.debounce.500ms="code" dir="ltr" class="mt-1 block w-full font-mono text-sm bg-gray-50" rows="12"></x-textarea>
                    <x-input-error :messages="$errors->get('code')" class="mt-2" />
                    <p class="mt-1 text-xs text-gray-500">از سینتکس استاندارد Mermaid.js استفاده کنید.</p>
                </div>

                <div class="flex items-center gap-4">
                    <x-primary-button type="submit">
                        {{ $flowchart && $flowchart->exists ? 'ذخیره تغییرات' : 'ایجاد فلوچارت' }}
                    </x-primary-button>
                </div>
            </form>
        </x-admin.card>

        <!-- بخش پیش‌نمایش -->
        <div class="col-span-1 border rounded-lg p-4 bg-gray-50 flex flex-col"
             x-data="{
        code: @entangle('code'),
        async renderChart(value) {
            if (!value || value.trim() === '') {
                this.$refs.container.innerHTML = '<p class=\'text-gray-400 text-center mt-10\'>کد فلوچارت را وارد کنید...</p>';
                return;
            }
            try {
                const mermaid = await window.loadMermaid();
                const id = 'mermaid-svg-' + Math.round(Math.random() * 100000);
                const { svg } = await mermaid.render(id, value);
                this.$refs.container.innerHTML = svg;
            } catch (error) {
                console.warn('خطای ساختار Mermaid:', error);
            }
        },
       downloadImage() {
    const svgNode = this.$refs.container.querySelector('svg');
    if (!svgNode) {
        alert('گرافی برای دانلود وجود ندارد!');
        return;
    }

    // ۱. کپی کردن SVG تا تغییرات روی گراف اصلی نمایش داده شده اعمال نشود
    const clonedSvg = svgNode.cloneNode(true);

    // ۲. استخراج ابعاد واقعی و کامل از ویژگی viewBox که Mermaid تولید می‌کند
    const viewBox = clonedSvg.getAttribute('viewBox');
    let realWidth = 800, realHeight = 600; // مقادیر پیش‌فرض

    if (viewBox) {
        const parts = viewBox.split(' ');
        if (parts.length === 4) {
            realWidth = parseFloat(parts[2]);
            realHeight = parseFloat(parts[3]);
        }
    } else {
        const rect = svgNode.getBoundingClientRect();
        realWidth = rect.width;
        realHeight = rect.height;
    }

    // ۳. مقداردهی قطعی طول و عرض به پیکسل برای جلوگیری از برش خوردن
    clonedSvg.setAttribute('width', realWidth);
    clonedSvg.setAttribute('height', realHeight);
    clonedSvg.style.maxWidth = 'none'; // خنثی کردن استایل‌های محدودکننده Mermaid

    // ۴. تبدیل به رشته و ساخت بوم (Canvas)
    const svgData = new XMLSerializer().serializeToString(clonedSvg);
    const canvas = document.createElement('canvas');

    // ۵. افزایش کیفیت (Scale) برای جلوگیری از تاری
    const scale = 2;
    canvas.width = realWidth * scale;
    canvas.height = realHeight * scale;

    const ctx = canvas.getContext('2d');

    // ۶. پس‌زمینه سفید
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, canvas.width, canvas.height);

    ctx.scale(scale, scale);

    // ۷. تولید خروجی
    const img = new Image();
    img.src = 'data:image/svg+xml;base64,' + btoa(unescape(encodeURIComponent(svgData)));

    img.onload = () => {
        ctx.drawImage(img, 0, 0);
        const imgURI = canvas.toDataURL('image/png', 1.0);

        const a = document.createElement('a');
        a.download = 'flowchart.png';
        a.href = imgURI;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    };
}
    }"
             x-init="
        window.loadMermaid().then(() => {
            setTimeout(() => { if(code) renderChart(code); }, 100);
        });
        $watch('code', value => renderChart(value));
    ">

            <!-- هدر بخش پیش‌نمایش همراه با دکمه دانلود -->
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900">پیش‌نمایش زنده</h3>

                <x-secondary-button type="button" @click="downloadImage()" class="text-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    ذخیره تصویر
                </x-secondary-button>
            </div>

            <!-- کانتینر گراف با wire:ignore -->
            <div class="flex-1 overflow-auto bg-white border rounded-lg p-4 flex justify-center items-center" wire:ignore>
                <div x-ref="container" class="w-full min-h-[300px]" dir="ltr"></div>
            </div>
        </div>

    </div>
</div>
