const ROOT_SELECTOR = '[data-product-create-guide], [data-tier-discounts-guide], [data-check-periods-guide], [data-price-rounding-ranges-guide]';
const STEP_SELECTOR = '[data-product-tour-step]';
const MOBILE_BREAKPOINT = 768;

function isVisible(element) {
    if (!element?.isConnected || element.closest('[hidden]')) return false;

    for (let current = element; current; current = current.parentElement) {
        const style = window.getComputedStyle(current);
        if (style.display === 'none' || style.visibility === 'hidden') return false;
    }

    const rect = element.getBoundingClientRect();
    return rect.width > 0 && rect.height > 0;
}

function collectSteps(root) {
    const welcomeTarget = root.querySelector('[data-product-tour-welcome]');
    const steps = [{
        key: 'welcome',
        order: 0,
        title: root.dataset.productTourWelcomeTitle || 'راهنمای پیشنهاد کالای جدید',
        description: root.dataset.productTourWelcomeDescription || 'این راهنما فرم را به‌ترتیب توضیح می‌دهد. برای هر بخش می‌توانید همان‌جا اطلاعات را وارد کنید؛ راهنما چیزی را ثبت یا ارسال نمی‌کند.',
        hint: root.dataset.productTourWelcomeHint || 'با «مرحله بعد» جلو بروید؛ برای پایان یا خروج هر زمان «بستن» را بزنید.',
        target: welcomeTarget,
        owner: root,
    }];

    root.querySelectorAll(STEP_SELECTOR).forEach((element) => {
        if (!isVisible(element)) return;
        const targetSelector = element.dataset.productTourTarget;
        const target = targetSelector ? element.querySelector(targetSelector) : element;
        let label = element.dataset.productTourLabel
            ? element.querySelector(element.dataset.productTourLabel)
            : null;

        if (!label && target?.id) {
            const targetId = window.CSS?.escape ? window.CSS.escape(target.id) : target.id.replace(/(["\\])/g, '\\$1');
            const labelSelector = `label[for="${targetId}"]`;
            label = element.querySelector(labelSelector) || root.querySelector(labelSelector);
        }
        if (!label) label = element.querySelector('label, h2, h3, h4');

        steps.push({
            key: element.dataset.productTourStep,
            order: Number(element.dataset.productTourOrder || 1000),
            title: element.dataset.productTourTitle || 'راهنمای این بخش',
            description: element.dataset.productTourDescription || '',
            hint: element.dataset.productTourHint || '',
            target: target && isVisible(target) ? target : element,
            label: label && isVisible(label) ? label : null,
            owner: element,
            mediaState: element.dataset.productTourStep === 'gallery-images'
                ? `${root.querySelector('[data-product-tour-image-count]')?.dataset.productTourImageCount}:${root.querySelector('[data-product-tour-drag-version]')?.dataset.productTourDragVersion}`
                : null,
        });
        if (element.dataset.productTourStep === 'gallery-images' && root.dataset.productTourMediaRequired === 'true') {
            const uploadStep = steps[steps.length - 1];
            uploadStep.title = 'افزودن اولین تصویر محصول';
            uploadStep.description = 'روی «افزودن تصاویر» بزنید و یک عکس انتخاب کنید. پس از پایان بارگذاری، عکس اول به‌عنوان تصویر اصلی و پیش‌فرض سئو انتخاب می‌شود. افزودن تصویر برای ادامه الزامی است.';
            steps.push({ ...uploadStep, key: 'gallery-more-images', order: 3,
                title: 'افزودن دو تصویر دیگر',
                description: 'برای تمرین جابه‌جایی در این راهنما، دو عکس دیگر اضافه کنید. برای ثبت محصول فقط یک تصویر الزامی است؛ می‌توانید چند تصویر را هم‌زمان انتخاب کنید.' });
            steps.push({ ...uploadStep, key: 'gallery-reorder', order: 4,
                target: element.querySelector('[data-product-gallery-sortable]') || uploadStep.target,
                label: null,
                title: 'تغییر ترتیب با کشیدن عکس‌ها',
                description: 'یکی از عکس‌ها را با ماوس بگیرید، روی جایگاه عکس دیگری بکشید و رها کنید. عکس جایگاه اول، تصویر اصلی می‌شود. برای ادامه، حداقل یک جابه‌جایی واقعی بین سه تصویر انجام دهید.',
                hint: 'پس از رهاکردن عکس، منتظر بمانید تا ترتیب جدید نمایش داده شود.' });
        }
    });

    return steps.sort((first, second) => first.order - second.order);
}

function intersectionArea(first, second) {
    const width = Math.max(0, Math.min(first.right, second.right) - Math.max(first.left, second.left));
    const height = Math.max(0, Math.min(first.bottom, second.bottom) - Math.max(first.top, second.top));
    return width * height;
}

class ProductCreationGuide {
    constructor(root) {
        this.root = root;
        this.steps = [];
        this.index = 0;
        this.active = false;
        this.highlighted = null;
        this.highlightedLabel = null;
        this.refreshQueued = false;
        this.positionQueued = false;
        this.isMobile = null;
        this.deactivationReturnKey = null;
        this.pendingRangeStartIndex = null;
        this.pendingPriceUpdateModal = false;

        this.layer = document.createElement('div');
        this.layer.className = 'product-tour-layer';
        this.layer.hidden = true;
        this.layer.innerHTML = `
            <div class="product-tour-backdrop-piece" data-piece="top" aria-hidden="true"></div>
            <div class="product-tour-backdrop-piece" data-piece="left" aria-hidden="true"></div>
            <div class="product-tour-backdrop-piece" data-piece="right" aria-hidden="true"></div>
            <div class="product-tour-backdrop-piece" data-piece="bottom" aria-hidden="true"></div>`;
        this.card = document.createElement('aside');
        this.card.className = 'product-tour-card';
        this.card.hidden = true;
        this.card.setAttribute('role', 'dialog');
        this.card.setAttribute('aria-modal', 'false');
        this.card.setAttribute('aria-labelledby', 'product-tour-title');
        this.card.setAttribute('aria-describedby', 'product-tour-description');
        this.card.setAttribute('tabindex', '-1');
        this.card.innerHTML = `
            <div class="product-tour-card-top">
                <span class="product-tour-eyebrow">راهنمای مرحله‌ای</span>
                <div class="product-tour-card-top-actions">
                    <button type="button" class="product-tour-collapse" data-product-tour-action="toggle" aria-expanded="true">کوچک‌کردن</button>
                    <button type="button" class="product-tour-close" data-product-tour-action="close" aria-label="بستن راهنما">×</button>
                </div>
            </div>
            <h2 id="product-tour-title" class="product-tour-title" aria-live="polite"></h2>
            <div class="product-tour-card-details">
                <div class="product-tour-progress-track" aria-hidden="true"><span data-product-tour-progress></span></div>
                <div class="product-tour-step-count" data-product-tour-count></div>
                <p id="product-tour-description" class="product-tour-description"></p>
                <p class="product-tour-hint" data-product-tour-hint hidden></p>
                <p class="product-tour-warning" data-product-tour-warning role="status" aria-live="polite" hidden></p>
            </div>
            <div class="product-tour-actions">
                <button type="button" class="product-tour-button product-tour-button-muted" data-product-tour-action="close">بستن</button>
                <div class="product-tour-actions-next">
                    <button type="button" class="product-tour-button product-tour-button-muted" data-product-tour-action="previous">قبلی</button>
                    <button type="button" class="product-tour-button product-tour-button-primary" data-product-tour-action="next">مرحله بعد</button>
                </div>
            </div>`;

        document.body.append(this.layer, this.card);
        this.title = this.card.querySelector('#product-tour-title');
        this.description = this.card.querySelector('#product-tour-description');
        this.hint = this.card.querySelector('[data-product-tour-hint]');
        this.warning = this.card.querySelector('[data-product-tour-warning]');
        this.count = this.card.querySelector('[data-product-tour-count]');
        this.progress = this.card.querySelector('[data-product-tour-progress]');
        this.previousButton = this.card.querySelector('[data-product-tour-action="previous"]');
        this.nextButton = this.card.querySelector('[data-product-tour-action="next"]');
        this.collapseButton = this.card.querySelector('[data-product-tour-action="toggle"]');
        this.pieces = [...this.layer.querySelectorAll('[data-piece]')];

        this.onRootClick = this.onRootClick.bind(this);
        this.onCardClick = this.onCardClick.bind(this);
        this.onKeyDown = this.onKeyDown.bind(this);
        this.schedulePosition = this.schedulePosition.bind(this);
        this.scheduleRefresh = this.scheduleRefresh.bind(this);

        this.root.addEventListener('click', this.onRootClick);
        this.card.addEventListener('click', this.onCardClick, true);
        document.addEventListener('keydown', this.onKeyDown);
        window.addEventListener('resize', this.schedulePosition);
        window.addEventListener('scroll', this.schedulePosition, true);

        this.observer = new MutationObserver(this.scheduleRefresh);
        this.observer.observe(this.root, {
            childList: true,
            characterData: true,
            subtree: true,
            attributes: true,
            attributeFilter: [
                'data-product-tour-category-selected',
                'data-product-tour-tiers-applied',
                'data-product-tour-description',
                'data-product-tour-hint',
                'data-product-tour-title',
                'data-product-tour-image-count',
                'data-product-tour-drag-version',
                'aria-expanded',
                'hidden',
                'style',
            ],
        });
    }

    onRootClick(event) {
        const target = event.target instanceof Element ? event.target : event.target?.parentElement;
        if (target?.closest('[data-product-tour-start]')) {
            this.open();
            return;
        }

        if (this.active && target?.closest('[data-price-rounding-add-range]')) {
            this.pendingRangeStartIndex = this.root.querySelectorAll('[data-price-rounding-range-card]').length;
            this.scheduleRefresh();
            return;
        }

        if (this.active && target?.closest('[data-price-rounding-update-open]')) {
            this.pendingPriceUpdateModal = true;
            this.scheduleRefresh();
            return;
        }

        if (this.active && target?.closest('[data-check-guide-category-toggle]')) {
            this.scheduleRefresh();
        }
    }

    onCardClick(event) {
        const target = event.target instanceof Element ? event.target : event.target?.parentElement;
        const button = target?.closest('[data-product-tour-action]');
        if (!button) return;

        event.preventDefault();
        event.stopPropagation();
        const action = button.dataset.productTourAction;
        if (action === 'close') return this.close();
        if (action === 'previous') return this.move(-1);
        if (action === 'next') return this.move(1);
        if (action === 'toggle') return this.toggleCard();
    }

    onKeyDown(event) {
        if (this.active && event.key === 'Escape') {
            event.preventDefault();
            this.close();
        }
    }

    open() {
        this.steps = collectSteps(this.root);
        this.index = 0;
        this.active = true;
        this.layer.hidden = false;
        this.card.hidden = false;
        this.renderCurrent(true);
    }

    close() {
        if (!this.active) return;
        this.active = false;
        this.clearHighlight();
        this.layer.hidden = true;
        this.card.hidden = true;
        this.warning.hidden = true;
        this.root.querySelector('[data-product-tour-start]')?.focus({ preventScroll: true });
    }

    move(direction) {
        this.steps = collectSteps(this.root);
        const current = this.steps[this.index];
        const mediaWarning = direction > 0 ? this.mediaRequirementMessage(current) : null;
        if (mediaWarning) {
            this.warning.textContent = mediaWarning;
            this.warning.hidden = false;
            return;
        }

        if (direction > 0 && current?.key === 'category' && !this.categoryIsSelected()) {
            this.warning.textContent = 'برای ادامه، همین حالا یک دسته‌بندی نهایی از فرم انتخاب کنید.';
            this.warning.hidden = false;
            this.root.querySelector('#category-select')?.focus({ preventScroll: true });
            return;
        }

        if (direction > 0 && current?.key === 'category-section'
            && this.root.querySelector('[data-check-guide-category-toggle]')?.getAttribute('aria-expanded') !== 'true') {
            this.warning.textContent = 'برای دیدن مراحل درصدهای دسته‌بندی، ابتدا همین بخش را باز کنید.';
            this.warning.hidden = false;
            this.root.querySelector('[data-check-guide-category-toggle]')?.focus({ preventScroll: true });
            return;
        }

        if (direction > 0 && current?.key === 'category-select') {
            const category = this.root.querySelector('[data-check-guide-category-select]');
            if (!category?.value) {
                this.warning.textContent = 'برای ادامه، یک دسته‌بندی نهایی را از همین فهرست انتخاب کنید.';
                this.warning.hidden = false;
                category?.focus({ preventScroll: true });
                return;
            }
        }

        if (direction > 0 && current?.key === 'deactivation-confirm-action') {
            this.warning.textContent = 'برای ادامه، در پنجرهٔ تأیید یکی از دو انتخاب را انجام دهید: انصراف یا غیرفعال‌کردن. راهنما هیچ‌کدام را به‌جای شما انتخاب نمی‌کند.';
            this.warning.hidden = false;
            this.root.querySelector('[data-check-guide-deactivation-cancel]')?.focus({ preventScroll: true });
            return;
        }

        if (direction > 0 && current?.key === 'tier-currency-base') {
            const currency = this.root.querySelector('#supplier-pricing-currency');
            if (currency?.value === 'TOM') {
                this.warning.textContent = 'برای دیدن نرخ دستی و دریافت خودکار، یک ارز پایهٔ ارزی انتخاب کنید. انتخاب ارز تا وقتی «ذخیرهٔ تنظیمات ارز» را نزنید ثبت نمی‌شود.';
                this.warning.hidden = false;
                currency.focus({ preventScroll: true });
                return;
            }
        }

        if (direction > 0 && current?.key === 'currency-auto-toggle') {
            const automaticRate = this.root.querySelector('[id^="currency-auto-"]');
            if (!automaticRate?.checked) {
                this.warning.textContent = 'برای دیدن دوره‌های به‌روزرسانی، تیک «دریافت خودکار نرخ» را روشن کنید. نرخ دستی در مرحلهٔ قبل اختیاری بود.';
                this.warning.hidden = false;
                automaticRate?.focus({ preventScroll: true });
                return;
            }
        }

        if (direction > 0 && current?.key === 'tier-category-select') {
            const category = this.root.querySelector('[data-tier-guide-category-select]');
            if (!category?.value) {
                this.warning.textContent = 'برای دیدن تنظیم درصدهای اختصاصی، ابتدا یک دسته‌بندی را انتخاب کنید.';
                this.warning.hidden = false;
                category?.focus({ preventScroll: true });
                return;
            }
        }

        if (direction > 0 && current?.key === 'auto-tiers' && !this.tiersAreApplied()) {
            this.warning.textContent = this.getAutoTierButton()
                ? 'برای ادامه، روی دکمهٔ «اعمال این تیراژها» در فرم کلیک کنید.'
                : 'ابتدا تعداد داخل هر واحد بسته‌بندی را وارد کنید تا پیشنهاد تیراژ نمایش داده شود.';
            this.warning.hidden = false;
            (this.getAutoTierButton() || this.root.querySelector('#quantity_per_packaging'))?.focus({ preventScroll: true });
            return;
        }

        const roundingMinMatch = direction > 0
            ? current?.key?.match(/^price-rounding-range-(\d+)-min$/)
            : null;
        if (roundingMinMatch && Number(roundingMinMatch[1]) > 0) {
            const rangeIndex = Number(roundingMinMatch[1]);
            const previousMax = this.root
                .querySelector(`[data-price-rounding-range-card="${rangeIndex - 1}"] [data-price-rounding-max-input]`);
            if (!previousMax?.value) {
                const previousMaxStep = this.steps.findIndex((step) => step.key === `price-rounding-range-${rangeIndex - 1}-max`);
                if (previousMaxStep >= 0) {
                    this.index = previousMaxStep;
                    this.renderCurrent(true);
                }
                this.warning.textContent = 'برای اینکه بازه‌ها روی هم نیفتند، ابتدا سقف بازهٔ قبلی را وارد کنید؛ حداقل این بازه بعد از آن خودکار اصلاح می‌شود.';
                this.warning.hidden = false;
                previousMax?.focus({ preventScroll: true });
                previousMax?.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' });
                return;
            }
        }

        if (direction > 0 && current?.key === 'price-rounding-save') {
            const coverageStep = this.steps.findIndex((step) => step.key === 'price-rounding-coverage-warning');
            if (coverageStep >= 0) {
                this.index = coverageStep;
                this.renderCurrent(true);
                this.warning.textContent = 'تا وقتی هشدار پوشش نمایش داده می‌شود، تنظیمات قابل ذخیره نیستند. ابتدا بازه‌ها را اصلاح کنید.';
                this.warning.hidden = false;
                return;
            }
        }

        if (direction > 0 && current?.key === 'price-rounding-update-confirm'
            && this.root.querySelector('[data-price-rounding-update-modal]')) {
            this.warning.textContent = 'برای ادامه، در پنجرهٔ تأیید یکی از دو انتخاب را انجام دهید: «انصراف» یا «بله، بروزرسانی کن». راهنما هیچ‌کدام را به‌جای شما انتخاب نمی‌کند.';
            this.warning.hidden = false;
            this.root.querySelector('[data-price-rounding-update-cancel]')
                ?.focus({ preventScroll: true });
            if (!this.root.querySelector('[data-price-rounding-update-cancel]')) {
                this.root.querySelector('[data-price-rounding-update-confirm]')
                ?.focus({ preventScroll: true });
            }
            return;
        }

        if (direction > 0 && ['global-period-toggle', 'supplier-period-toggle'].includes(current?.key)) {
            const confirmationIndex = this.steps.findIndex((step) => step.key === 'deactivation-confirm-title');
            if (confirmationIndex >= 0) {
                this.deactivationReturnKey = current.key;
                this.index = confirmationIndex;
                this.renderCurrent(true);
                return;
            }
        }

        this.warning.hidden = true;
        const guidedCurrencyNext = direction > 0 ? ({
            'tier-currency-base': 'currency-rate-input',
            'currency-rate-input': 'currency-auto-toggle',
            'currency-auto-toggle': 'currency-refresh-interval',
            'currency-refresh-interval': 'currency-rate-status',
            'currency-rate-status': 'save-currency-settings',
        }[current?.key]) : null;
        const nextIndex = guidedCurrencyNext
            ? this.steps.findIndex((step) => step.key === guidedCurrencyNext)
            : this.index + direction;

        if (guidedCurrencyNext && nextIndex < 0) {
            this.warning.textContent = 'این بخش هنوز آماده نشده است. بعد از تغییر ارز یا روشن‌کردن دریافت خودکار، کمی صبر کنید و دوباره «مرحله بعد» را بزنید.';
            this.warning.hidden = false;
            this.scheduleRefresh();
            return;
        }
        if (nextIndex < 0) return;
        if (nextIndex >= this.steps.length) return this.close();

        this.index = nextIndex;
        this.renderCurrent(true);
    }

    toggleCard() {
        const collapsed = this.card.classList.toggle('product-tour-card-collapsed');
        this.collapseButton.textContent = collapsed ? 'توضیح' : 'کوچک‌کردن';
        this.collapseButton.setAttribute('aria-expanded', String(!collapsed));
        this.schedulePosition();
    }

    categoryIsSelected() {
        const category = this.root.querySelector('#field-category_id');
        if (category?.dataset.productTourCategorySelected === 'true') return true;

        const label = this.root.querySelector('#category-current')?.textContent?.trim();
        return Boolean(label && label !== 'یک دسته‌بندی انتخاب کنید...');
    }

    mediaRequirementMessage(step) {
        if (this.root.dataset.productTourMediaRequired !== 'true') return null;
        const media = this.root.querySelector('[data-product-tour-image-count]');
        const count = Number(media?.dataset.productTourImageCount || 0);
        if (step?.key === 'gallery-images' && count < 1) return 'ابتدا یک تصویر اضافه کنید و تا پایان بارگذاری منتظر بمانید.';
        if (['gallery-more-images', 'gallery-reorder'].includes(step?.key) && count < 3) return 'برای ادامه حداقل سه تصویر اضافه کنید؛ یک تصویر اصلی و دو تصویر گالری.';
        if (step?.key === 'gallery-reorder' && Number(media?.dataset.productTourDragVersion || 0) < 1) return 'یکی از تصاویر را روی جایگاه دیگری بکشید و رها کنید تا ترتیب واقعاً تغییر کند.';
        return null;
    }

    tiersAreApplied() {
        return this.root.querySelector('#field-packaging')?.dataset.productTourTiersApplied === 'true';
    }

    getAutoTierButton() {
        const button = [...this.root.querySelectorAll('button')]
            .find((candidate) => candidate.getAttribute('wire:click') === 'applyAutoTiers');
        return isVisible(button) ? button : null;
    }

    renderCurrent(scrollToTarget = false) {
        if (!this.active) return;
        this.steps = collectSteps(this.root);
        this.index = Math.max(0, Math.min(this.index, this.steps.length - 1));
        const step = this.steps[this.index];
        if (!step) return this.close();

        this.clearHighlight();
        this.title.textContent = step.title;
        this.description.textContent = step.description;
        this.hint.textContent = step.hint || '';
        this.hint.hidden = !step.hint;
        this.warning.hidden = true;
        this.count.textContent = `گام ${this.index + 1} از ${this.steps.length}`;
        this.progress.style.width = `${((this.index + 1) / this.steps.length) * 100}%`;
        this.previousButton.disabled = this.index === 0;
        const categorySectionNeedsOpen = step.key === 'category-section'
            && this.root.querySelector('[data-check-guide-category-toggle]')?.getAttribute('aria-expanded') !== 'true';
        this.nextButton.textContent = categorySectionNeedsOpen
            ? 'بازکردن بخش برای ادامه'
            : (this.index === this.steps.length - 1 ? 'پایان راهنما' : 'مرحله بعد');

        const autoTiersPending = step.key === 'auto-tiers' && !this.tiersAreApplied();
        if (autoTiersPending) {
            this.hint.textContent = this.getAutoTierButton()
                ? 'برای ادامه، روی دکمهٔ «اعمال این تیراژها» در فرم کلیک کنید. بعد از اعمال، راهنما تیراژهای ساخته‌شده را توضیح می‌دهد.'
                : 'تعداد داخل هر واحد بسته‌بندی را وارد کنید؛ سپس دکمهٔ اعمال پیشنهاد ظاهر می‌شود.';
            this.hint.hidden = false;
        }
        const mediaWarning = this.mediaRequirementMessage(step);
        if (mediaWarning) {
            this.hint.textContent = mediaWarning;
            this.hint.hidden = false;
        }
        this.nextButton.disabled = autoTiersPending || Boolean(mediaWarning);

        this.syncResponsiveCard(step, true);

        this.highlighted = step.target?.isConnected ? step.target : null;
        this.highlighted?.classList.add('product-tour-highlight');
        this.highlightedLabel = step.label?.isConnected && step.label !== this.highlighted ? step.label : null;

        if (scrollToTarget && this.highlighted) {
            this.highlighted.scrollIntoView({ behavior: 'smooth', block: step.key === 'gallery-reorder' ? 'start' : 'center', inline: 'nearest' });
        }

        requestAnimationFrame(() => this.positionCard());
    }

    syncResponsiveCard(step, resetCompactState = false) {
        const isMobile = window.innerWidth <= MOBILE_BREAKPOINT;
        const modeChanged = this.isMobile !== null && this.isMobile !== isMobile;
        this.card.classList.toggle('product-tour-card-mobile', isMobile);
        this.collapseButton.hidden = !isMobile;

        if (resetCompactState || modeChanged) {
            const compactByDefault = isMobile && !['welcome', 'auto-tiers', 'tiers-overview'].includes(step?.key);
            this.card.classList.toggle('product-tour-card-collapsed', compactByDefault);
            this.collapseButton.textContent = compactByDefault ? 'توضیح' : 'کوچک‌کردن';
            this.collapseButton.setAttribute('aria-expanded', String(!compactByDefault));
        }

        this.isMobile = isMobile;
    }

    clearHighlight() {
        this.highlighted?.classList.remove('product-tour-highlight');
        this.highlighted = null;
        this.highlightedLabel = null;
        this.pieces.forEach((piece) => { piece.style.cssText = ''; });
    }

    getSpotlightRect() {
        if (!this.highlighted?.isConnected) return null;

        const rect = this.highlighted.getBoundingClientRect();
        const bounds = { left: rect.left, top: rect.top, right: rect.right, bottom: rect.bottom };
        const step = this.steps[this.index];

        if (step?.label?.isConnected && step.label !== this.highlighted) {
            const labelRect = step.label.getBoundingClientRect();
            bounds.left = Math.min(bounds.left, labelRect.left);
            bounds.top = Math.min(bounds.top, labelRect.top);
            bounds.right = Math.max(bounds.right, labelRect.right);
            bounds.bottom = Math.max(bounds.bottom, labelRect.bottom);
        }

        if (step?.key === 'category') {
            const dropdown = step.owner?.querySelector('[x-show="open"]');
            if (dropdown && isVisible(dropdown)) {
                const dropdownRect = dropdown.getBoundingClientRect();
                return {
                    left: Math.min(bounds.left, dropdownRect.left),
                    top: Math.min(bounds.top, dropdownRect.top),
                    right: Math.max(bounds.right, dropdownRect.right),
                    bottom: Math.max(bounds.bottom, dropdownRect.bottom),
                };
            }
        }

        return bounds;
    }

    positionCard() {
        if (!this.active) return;
        const viewportWidth = window.innerWidth;
        const viewportHeight = window.innerHeight;
        const bounds = this.getSpotlightRect();
        this.syncResponsiveCard(this.steps[this.index]);
        const isGalleryDrag = this.steps[this.index]?.key === 'gallery-reorder';
        this.card.classList.toggle('product-tour-card-drag-safe', isGalleryDrag);
        this.card.style.maxHeight = '';

        if (bounds) {
            const padding = 10;
            const top = Math.max(0, bounds.top - padding);
            const left = Math.max(0, bounds.left - padding);
            const right = Math.min(viewportWidth, bounds.right + padding);
            const bottom = Math.min(viewportHeight, bounds.bottom + padding);
            const pieces = {
                top: { left: 0, top: 0, width: viewportWidth, height: top },
                left: { left: 0, top, width: left, height: Math.max(0, bottom - top) },
                right: { left: right, top, width: Math.max(0, viewportWidth - right), height: Math.max(0, bottom - top) },
                bottom: { left: 0, top: bottom, width: viewportWidth, height: Math.max(0, viewportHeight - bottom) },
            };

            this.pieces.forEach((piece) => {
                const rect = pieces[piece.dataset.piece];
                piece.style.left = `${rect.left}px`;
                piece.style.top = `${rect.top}px`;
                piece.style.width = `${rect.width}px`;
                piece.style.height = `${rect.height}px`;
            });
        } else {
            this.pieces.forEach((piece) => {
                piece.style.left = '0';
                piece.style.top = '0';
                piece.style.width = `${viewportWidth}px`;
                piece.style.height = `${viewportHeight}px`;
            });
        }

        if (window.innerWidth <= MOBILE_BREAKPOINT) {
            this.card.style.left = '';
            this.card.style.top = '';
            this.card.style.visibility = 'visible';
            return;
        }

        this.card.style.visibility = 'hidden';
        this.card.style.left = '0px';
        this.card.style.top = '0px';
        const cardRect = this.card.getBoundingClientRect();
        const cardWidth = cardRect.width;
        const cardHeight = cardRect.height;
        const margin = 12;
        const rootRect = this.root.getBoundingClientRect();
        const minLeft = Math.max(margin, rootRect.left + margin);
        const maxLeft = Math.max(minLeft, Math.min(viewportWidth - cardWidth - margin, rootRect.right - cardWidth - margin));
        const minTop = margin;
        const maxTop = Math.max(minTop, viewportHeight - cardHeight - margin);
        const clamp = (value, minimum, maximum) => Math.max(minimum, Math.min(value, maximum));
        const source = bounds || { left: minLeft, top: viewportHeight / 2, right: minLeft, bottom: viewportHeight / 2 };
        const rawCandidates = [
            { left: source.right + 18, top: source.top },
            { left: source.left - cardWidth - 18, top: source.top },
            { left: source.left, top: source.bottom + 18 },
            { left: source.left, top: source.top - cardHeight - 18 },
            { left: minLeft, top: minTop },
            { left: maxLeft, top: minTop },
            { left: minLeft, top: maxTop },
            { left: maxLeft, top: maxTop },
        ];
        const candidates = rawCandidates.map((candidate) => ({
            left: clamp(candidate.left, minLeft, maxLeft),
            top: clamp(candidate.top, minTop, maxTop),
        })).filter((candidate, index, all) => all.findIndex((other) => other.left === candidate.left && other.top === candidate.top) === index);
        const controls = [...this.root.querySelectorAll('input, select, textarea, button, [role="button"], [contenteditable="true"]')]
            .filter((element) => isVisible(element))
            .map((element) => element.getBoundingClientRect())
            .filter((rect) => rect.bottom > 0 && rect.top < viewportHeight && rect.right > 0 && rect.left < viewportWidth);
        const score = (candidate) => {
            const cardBounds = { left: candidate.left, top: candidate.top, right: candidate.left + cardWidth, bottom: candidate.top + cardHeight };
            const targetOverlap = bounds ? intersectionArea(cardBounds, bounds) : 0;
            const controlOverlap = controls.reduce((total, rect) => total + intersectionArea(cardBounds, rect), 0);
            const horizontalGap = bounds ? Math.max(bounds.left - cardBounds.right, cardBounds.left - bounds.right, 0) : 0;
            const verticalGap = bounds ? Math.max(bounds.top - cardBounds.bottom, cardBounds.top - bounds.bottom, 0) : 0;
            const distance = Math.hypot(horizontalGap, verticalGap);
            return targetOverlap * 12 + controlOverlap * 0.01 + distance * 0.6;
        };

        candidates.sort((first, second) => score(first) - score(second));
        let position = candidates[0] || { left: minLeft, top: minTop };
        if (isGalleryDrag && bounds) {
            const overlap = intersectionArea({ ...position, right: position.left + cardWidth, bottom: position.top + cardHeight }, bounds);
            if (overlap > 0) {
                const above = Math.max(0, bounds.top - margin * 2);
                const below = Math.max(0, viewportHeight - bounds.bottom - margin * 2);
                const placeBelow = below >= above;
                const availableHeight = placeBelow ? below : above;
                this.card.style.maxHeight = `${availableHeight}px`;
                position = {
                    left: clamp(bounds.left, minLeft, maxLeft),
                    top: placeBelow ? bounds.bottom + margin : margin,
                };
            }
        }
        this.card.style.left = `${position.left}px`;
        this.card.style.top = `${position.top}px`;
        this.card.style.visibility = 'visible';
    }

    schedulePosition() {
        if (!this.active || this.positionQueued) return;
        this.positionQueued = true;
        requestAnimationFrame(() => {
            this.positionQueued = false;
            this.positionCard();
        });
    }

    scheduleRefresh() {
        if (!this.active || this.refreshQueued) return;
        this.refreshQueued = true;
        requestAnimationFrame(() => {
            this.refreshQueued = false;
            if (!this.active) return;

            const previousStep = this.steps[this.index];
            const previousKey = previousStep?.key;
            const previousIndex = this.index;
            const nextSteps = collectSteps(this.root);
            const currentRangeCount = this.root.querySelectorAll('[data-price-rounding-range-card]').length;

            if (this.pendingRangeStartIndex !== null && currentRangeCount > this.pendingRangeStartIndex) {
                const newRangeKey = `price-rounding-range-${this.pendingRangeStartIndex}-min`;
                const newRangeIndex = nextSteps.findIndex((step) => step.key === newRangeKey);
                this.pendingRangeStartIndex = null;
                if (newRangeIndex >= 0) {
                    this.steps = nextSteps;
                    this.index = newRangeIndex;
                    return this.renderCurrent(true);
                }
            }

            if (this.pendingPriceUpdateModal) {
                const modalIndex = nextSteps.findIndex((step) => step.key === 'price-rounding-update-modal');
                if (modalIndex >= 0) {
                    this.pendingPriceUpdateModal = false;
                    this.steps = nextSteps;
                    this.index = modalIndex;
                    return this.renderCurrent(true);
                }
                if (!this.root.querySelector('[data-price-rounding-update-modal]')) {
                    this.pendingPriceUpdateModal = false;
                }
            }

            const changed = nextSteps.length !== this.steps.length || nextSteps.some((step, index) =>
                step.key !== this.steps[index]?.key
                || step.target !== this.steps[index]?.target
                || step.title !== this.steps[index]?.title
                || step.description !== this.steps[index]?.description
                || step.hint !== this.steps[index]?.hint
                || step.mediaState !== this.steps[index]?.mediaState
            );
            if (!changed) return this.schedulePosition();

            this.steps = nextSteps;
            const wasDeactivationConfirmation = previousKey?.startsWith('deactivation-confirm-')
                && !nextSteps.some((step) => step.key.startsWith('deactivation-confirm-'));
            const wasPriceRoundingConfirmation = previousKey?.startsWith('price-rounding-update-')
                && !nextSteps.some((step) => step.key.startsWith('price-rounding-update-'));
            if (wasDeactivationConfirmation && this.deactivationReturnKey) {
                const returnIndex = nextSteps.findIndex((step) => step.key === this.deactivationReturnKey);
                this.index = returnIndex >= 0 ? returnIndex + 1 : Math.min(previousIndex, this.steps.length - 1);
                this.deactivationReturnKey = null;
            } else if (wasPriceRoundingConfirmation) {
                const applyIndex = nextSteps.findIndex((step) => step.key === 'price-rounding-apply-existing');
                this.index = applyIndex >= 0 ? applyIndex + 1 : Math.min(previousIndex, this.steps.length - 1);
            } else if (previousKey === 'auto-tiers' && this.tiersAreApplied()) {
                const tiersIndex = this.steps.findIndex((step) => step.key === 'tiers-overview');
                this.index = tiersIndex >= 0 ? tiersIndex : Math.min(previousIndex, this.steps.length - 1);
            } else {
                const matchingIndex = this.steps.findIndex((step) => step.key === previousKey);
                this.index = matchingIndex >= 0 ? matchingIndex : Math.min(previousIndex, this.steps.length - 1);
            }
            this.renderCurrent(false);
        });
    }

    destroy() {
        this.close();
        this.observer.disconnect();
        this.root.removeEventListener('click', this.onRootClick);
        this.card.removeEventListener('click', this.onCardClick, true);
        document.removeEventListener('keydown', this.onKeyDown);
        window.removeEventListener('resize', this.schedulePosition);
        window.removeEventListener('scroll', this.schedulePosition, true);
        this.layer.remove();
        this.card.remove();
    }
}

let currentGuide = null;

function bootProductCreateGuide() {
    const root = document.querySelector(ROOT_SELECTOR);
    if (currentGuide?.root === root) return;

    currentGuide?.destroy();
    currentGuide = root ? new ProductCreationGuide(root) : null;
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootProductCreateGuide, { once: true });
} else {
    bootProductCreateGuide();
}

document.addEventListener('livewire:navigated', bootProductCreateGuide);
