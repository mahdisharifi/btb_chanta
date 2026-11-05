import './bootstrap';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// ✅ Import slider utilities (shared components)
import './components/slider-utils.js';
import './components/formatted-price-input.js';

const pusherKey = import.meta.env.VITE_PUSHER_APP_KEY;
const pusherCluster = import.meta.env.VITE_PUSHER_APP_CLUSTER;
if (pusherKey && (pusherCluster || import.meta.env.VITE_PUSHER_HOST)) {
    window.Pusher = Pusher;

    const scheme = import.meta.env.VITE_PUSHER_SCHEME || 'https';
    const host = import.meta.env.VITE_PUSHER_HOST || undefined;
    const port = Number(import.meta.env.VITE_PUSHER_PORT || (scheme === 'https' ? 443 : 80));
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const echoOptions = {
        broadcaster: 'pusher',
        key: pusherKey,
        cluster: pusherCluster || undefined,
        forceTLS: scheme === 'https',
        disableStats: true,
        enabledTransports: ['ws', 'wss'],
        authEndpoint: '/broadcasting/auth',
        auth: { headers: csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {} },
    };

    if (host) {
        echoOptions.wsHost = host;
        echoOptions.wsPort = port;
        echoOptions.wssPort = port;
    }

    window.Echo = new Echo(echoOptions);
}

const activePriceSubscriptions = new Map();
const visiblePriceProductCounts = new Map();
const observedPriceElements = new Set();
const lastLivePriceRevisionByProduct = new Map();
const snapshotSequenceByProduct = new Map();
const formatLivePrice = (value) => new Intl.NumberFormat('fa-IR', { maximumFractionDigits: 0 }).format(value);

function applySupplierPriceUpdate(productId, update) {
    if (update.product_supplier_id === null || update.product_supplier_id === undefined) return;

    const supplierId = String(update.product_supplier_id);
    const prices = update.prices || [];
    const pricesByKey = new Map(prices.map((price) => [
        `${price.type}:${price.tier_index}:${price.check_period_id ?? 0}`,
        price,
    ]));

    document.querySelectorAll('[data-live-price-supplier-container]').forEach((container) => {
        if (container.dataset.livePriceProduct === productId
            && container.dataset.livePriceSupplierContainer === supplierId) {
            container.hidden = update.supplier_available === false;
        }
    });

    document.querySelectorAll('[data-live-price-cell][data-live-price-product][data-live-price-supplier]').forEach((cell) => {
        if (cell.dataset.livePriceProduct !== productId || cell.dataset.livePriceSupplier !== supplierId) return;

        const price = pricesByKey.get(cell.dataset.livePriceCell);
        const value = price
            ? (cell.dataset.livePriceVariant === 'warranty' ? price.warranty_price : price.price)
            : null;
        cell.textContent = value === null || value === undefined ? '—' : formatLivePrice(value);
    });

    document.querySelectorAll('[data-live-price-product][data-live-price-range="supplier"]').forEach((element) => {
        if (element.dataset.livePriceProduct !== productId
            || element.dataset.livePriceSupplierId !== String(update.supplier_id)) return;

        const value = element.dataset.livePriceRangeKind === 'max'
            ? update.supplier_highest
            : update.supplier_lowest;
        element.textContent = value === null || value === undefined ? '—' : formatLivePrice(value);
    });

    updatePriceRangeWrappers(productId, 'supplier', {
        min: update.supplier_lowest,
        max: update.supplier_highest,
        tiers_count: update.supplier_tiers_count,
    }, update.supplier_id);
    updateEmptyPriceLabels(productId, 'supplier', update.supplier_lowest, update.supplier_id);
}

function updatePriceRangeWrappers(productId, rangeType, range, supplierId = null) {
    document.querySelectorAll('[data-live-price-range-wrapper]').forEach((wrapper) => {
        if (wrapper.dataset.livePriceProduct !== productId || wrapper.dataset.livePriceRange !== rangeType) return;
        if (rangeType === 'supplier' && wrapper.dataset.livePriceSupplierId !== String(supplierId)) return;

        const maximum = wrapper.querySelector('[data-live-price-range-kind="max"]');
        const separator = wrapper.querySelector('[data-live-price-range-separator]');
        const hasRange = range.min !== null
            && range.min !== undefined
            && range.max !== null
            && range.max !== undefined
            && Number(range.min) !== Number(range.max);

        if (maximum) maximum.hidden = !hasRange;
        if (separator) separator.hidden = !hasRange;

        const tierCount = Number(range.tiers_count || 0);
        const tierCountElement = wrapper.querySelector('[data-live-price-static-count]');
        if (tierCountElement) {
            tierCountElement.textContent = `(${formatLivePrice(tierCount)} تیراژ)`;
            tierCountElement.hidden = tierCount <= 1;
        }
    });
}

function updateEmptyPriceLabels(productId, range, value, supplierId = null) {
    document.querySelectorAll('[data-live-price-empty-label]').forEach((label) => {
        if (label.dataset.livePriceProduct !== productId || label.dataset.livePriceRange !== range) return;
        if (range === 'supplier' && label.dataset.livePriceSupplierId !== String(supplierId)) return;
        label.hidden = value !== null && value !== undefined;
    });
}

function announceLivePriceUpdate(productId) {
    let announcer = document.getElementById('live-price-announcer');
    if (!announcer) {
        announcer = document.createElement('div');
        announcer.id = 'live-price-announcer';
        announcer.className = 'sr-only';
        announcer.setAttribute('role', 'status');
        announcer.setAttribute('aria-live', 'polite');
        announcer.setAttribute('aria-atomic', 'true');
        document.body.appendChild(announcer);
    }

    const titleMarker = document.querySelector(
        `[data-live-price-product-title-id="${productId}"]`,
    ) ?? document.querySelector(
        `[data-live-price-product="${productId}"][data-live-price-product-title]`,
    );
    const productTitle = titleMarker?.dataset.livePriceProductTitle?.trim();

    announcer.textContent = '';
    window.requestAnimationFrame(() => {
        announcer.textContent = productTitle
            ? `قیمت «${productTitle}» به‌روزرسانی شد.`
            : 'قیمت محصول به‌روزرسانی شد.';
    });
}

function clearLiveBestPriceIndicators(productId) {
    document.querySelectorAll('[data-live-price-product-matrix-cell][data-live-price-product]').forEach((cell) => {
        if (cell.dataset.livePriceProduct !== productId) return;

        const bestLink = cell.closest('a');
        if (bestLink?.matches('[data-live-price-best-summary]')) {
            bestLink.removeAttribute('href');
            bestLink.removeAttribute('onclick');
            bestLink.setAttribute('aria-disabled', 'true');
            bestLink.classList.remove('bg-green-50', 'hover:bg-green-100', 'text-green-800', 'ring-2', 'ring-green-400', 'cursor-pointer');
            bestLink.classList.add('bg-gray-50', 'text-gray-700');
        }
    });

    document.querySelectorAll('[data-live-price-best-summary-label], [data-live-price-best-summary-tooltip]').forEach((element) => {
        const productCell = element.closest('td')?.querySelector('[data-live-price-product-matrix-cell]');
        if (productCell?.dataset.livePriceProduct === productId) element.hidden = true;
    });

    document.querySelectorAll('[data-live-price-cell-highlight][data-live-price-product]').forEach((element) => {
        if (element.dataset.livePriceProduct !== productId) return;
        element.dataset.livePriceBestCleared = 'true';
        element.classList.remove('bg-green-50', 'text-green-800', 'font-bold', 'ring-2', 'ring-green-500');
        element.classList.add('text-gray-800');
    });

    document.querySelectorAll('[data-live-price-best-label][data-live-price-product]').forEach((element) => {
        if (element.dataset.livePriceProduct === productId) element.hidden = true;
    });

    document.querySelectorAll('[data-live-price-refresh-notice]').forEach((notice) => {
        if (notice.dataset.livePriceRefreshNotice === productId) notice.hidden = false;
    });
}

function applyProductPriceSummary(update) {
    const productId = String(update.product_id);

    document.querySelectorAll('[data-live-price-product][data-live-price-range="product"]').forEach((element) => {
        if (element.dataset.livePriceProduct !== productId) return;

        const value = element.dataset.livePriceRangeKind === 'max'
            ? update.product_highest
            : element.dataset.livePriceRangeKind === 'average'
                ? update.product_average
                : update.product_lowest;
        element.textContent = value === null || value === undefined ? '—' : formatLivePrice(value);
    });

    updatePriceRangeWrappers(productId, 'product', {
        min: update.product_lowest,
        max: update.product_highest,
        tiers_count: update.product_tiers_count,
    });

    document.querySelectorAll('[data-live-price-product][data-live-price-range-difference]').forEach((element) => {
        if (element.dataset.livePriceProduct === productId) {
            element.textContent = update.product_lowest === null || update.product_highest === null
                ? '—'
                : formatLivePrice(Math.max(0, update.product_highest - update.product_lowest));
        }
    });

    updateEmptyPriceLabels(productId, 'product', update.product_lowest);

    if (!Array.isArray(update.product_prices)) return;

    const matrixPrices = update.product_prices;
    const matrixPricesByKey = new Map(matrixPrices.map((price) => [
        `${price.type}:${price.tier_index}:${price.check_period_id ?? 0}`,
        price,
    ]));

    document.querySelectorAll('[data-live-price-product-matrix-cell][data-live-price-product]').forEach((cell) => {
        if (cell.dataset.livePriceProduct !== productId) return;

        const price = matrixPricesByKey.get(cell.dataset.livePriceProductMatrixCell);
        const value = price
            ? (cell.dataset.livePriceVariant === 'warranty' ? price.warranty_price : price.price)
            : null;
        cell.textContent = value === null || value === undefined ? '—' : formatLivePrice(value);
    });
}

function applyProductCardPriceSnapshot(productId, snapshot) {
    if (!snapshot || typeof snapshot !== 'object') return;

    const snapshotSupplierId = snapshot.card_supplier_id === null || snapshot.card_supplier_id === undefined
        ? null
        : String(snapshot.card_supplier_id);
    const range = snapshot.card_range || {};
    const hasPrice = range.lowest !== null && range.lowest !== undefined;
    const hasRange = hasPrice
        && range.highest !== null
        && range.highest !== undefined
        && Number(range.lowest) !== Number(range.highest);

    document.querySelectorAll('[data-live-price-card-range][data-live-price-product]').forEach((wrapper) => {
        if (wrapper.dataset.livePriceProduct !== String(productId)) return;

        const wrapperSupplierId = wrapper.dataset.livePriceCardSupplierId || null;
        if (wrapperSupplierId !== snapshotSupplierId) return;

        const minimum = wrapper.querySelector('[data-live-price-card-min]');
        const maximum = wrapper.querySelector('[data-live-price-card-max]');
        const separator = wrapper.querySelector('[data-live-price-card-separator]');
        const tierCount = wrapper.querySelector('[data-live-price-card-count]');
        if (minimum) minimum.textContent = hasPrice ? formatLivePrice(range.lowest) : '—';
        if (maximum) {
            maximum.textContent = hasPrice ? formatLivePrice(range.highest) : '—';
            maximum.hidden = !hasRange;
        }
        if (separator) separator.hidden = !hasRange;
        if (tierCount) {
            const count = Number(range.tiers_count || 0);
            tierCount.textContent = `(${formatLivePrice(count)} تیراژ)`;
            tierCount.hidden = count <= 1;
        }
        wrapper.hidden = !hasPrice;

        const card = wrapper.closest('.group');
        card?.querySelectorAll('[data-live-price-empty-label][data-live-price-range="card"]').forEach((label) => {
            if (label.dataset.livePriceProduct === String(productId)) label.hidden = hasPrice;
        });
    });
}

function applyBranchPriceSnapshot(productId, branches) {
    if (!Array.isArray(branches)) return;

    const branchPrices = new Map(branches.map((branch) => [String(branch.supplier_id), branch]));
    document.querySelectorAll('[data-live-price-branch-container]').forEach((container) => {
        if (container.dataset.livePriceProduct !== productId) return;

        container.querySelectorAll('[data-live-price-branch-row]').forEach((row) => {
            const branch = branchPrices.get(row.dataset.livePriceBranchRow);
            if (!branch) {
                row.hidden = true;
                return;
            }

            row.hidden = false;
            const price = row.querySelector('[data-live-price-branch-price]');
            const amount = price?.querySelector('[data-live-price-branch-amount]');
            if (amount) {
                amount.textContent = branch.price === null || branch.price === undefined
                    ? (branch.can_view_price ? 'قیمت فعال ندارد' : 'قیمت نیازمند دسترسی')
                    : `از ${formatLivePrice(branch.price)} تومان`;
            }

            const priceType = row.querySelector('[data-live-price-branch-type]');
            if (priceType) {
                const typeLabel = branch.price_type === 'check'
                    ? (branch.check_period_label ? `چکی · ${branch.check_period_label}` : 'چکی')
                    : (branch.price_type === 'cash' ? 'نقدی' : '');
                priceType.textContent = typeLabel ? `(${typeLabel})` : '';
            }

            const percentage = row.querySelector('[data-live-price-branch-percentage]');
            if (percentage) {
                percentage.textContent = branch.is_primary
                    ? 'قیمت پایه'
                    : `(${Number(branch.percentage) > 0 ? '+' : ''}${new Intl.NumberFormat('fa-IR', { maximumFractionDigits: 2 }).format(branch.percentage)}٪)`;
            }
        });
    });
}

function applyLivePriceUpdate(update, isRealtimeEvent = false) {
    const productId = String(update.product_id);
    const priceRevision = Number(update.price_revision);
    const currentRevision = lastLivePriceRevisionByProduct.get(productId);

    if (
        Number.isFinite(priceRevision)
        && currentRevision !== undefined
        && (isRealtimeEvent ? priceRevision <= currentRevision : priceRevision < currentRevision)
    ) return;
    if (isRealtimeEvent && update.requires_page_refresh) {
        window.location.reload();
        return;
    }

    if (Number.isFinite(priceRevision)) lastLivePriceRevisionByProduct.set(productId, priceRevision);
    if (Array.isArray(update.supplier_updates)) {
        update.supplier_updates.forEach((supplierUpdate) => applySupplierPriceUpdate(productId, supplierUpdate));
    } else {
        // Snapshot responses still use one supplier per call.
        applySupplierPriceUpdate(productId, update);
    }

    applyProductPriceSummary(update);
    if (isRealtimeEvent) {
        clearLiveBestPriceIndicators(productId);
        announceLivePriceUpdate(productId);
        refreshProductPriceSnapshot(productId);
    }
}

function refreshProductPriceSnapshot(productId) {
    const source = [...document.querySelectorAll('[data-live-price-product][data-live-price-snapshot-url]')]
        .find((element) => element.dataset.livePriceProduct === productId);
    if (!source) return;

    const requestSequence = (snapshotSequenceByProduct.get(productId) || 0) + 1;
    snapshotSequenceByProduct.set(productId, requestSequence);

    fetch(source.dataset.livePriceSnapshotUrl, {
        method: 'GET',
        credentials: 'same-origin',
        cache: 'no-store',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    })
        .then((response) => response.ok ? response.json() : null)
        .then((snapshot) => {
            if (!snapshot || snapshotSequenceByProduct.get(productId) !== requestSequence) return;

            const snapshotRevision = Number(snapshot.price_revision);
            const currentRevision = lastLivePriceRevisionByProduct.get(productId);
            if (Number.isFinite(snapshotRevision)
                && currentRevision !== undefined
                && snapshotRevision < currentRevision) return;

            applyBranchPriceSnapshot(productId, snapshot.branches);
            applyProductCardPriceSnapshot(productId, snapshot);

            const suppliers = Object.values(snapshot.suppliers || {});
            if (suppliers.length === 0) {
                applyLivePriceUpdate({
                    product_id: snapshot.product_id,
                    price_revision: snapshot.price_revision,
                    product_supplier_id: null,
                    supplier_id: null,
                    prices: [],
                    product_lowest: snapshot.product_lowest,
                    product_highest: snapshot.product_highest,
                    product_average: snapshot.product_average,
                    product_tiers_count: snapshot.product_tiers_count,
                    product_prices: snapshot.product_prices || [],
                    supplier_lowest: null,
                    supplier_highest: null,
                });
            }
            suppliers.forEach((supplier) => {
                applyLivePriceUpdate({
                    product_id: snapshot.product_id,
                    price_revision: snapshot.price_revision,
                    product_supplier_id: supplier.product_supplier_id,
                    supplier_id: supplier.supplier_id,
                    prices: supplier.prices,
                    product_lowest: snapshot.product_lowest,
                    product_highest: snapshot.product_highest,
                    product_average: snapshot.product_average,
                    product_tiers_count: snapshot.product_tiers_count,
                    product_prices: snapshot.product_prices || [],
                    supplier_lowest: supplier.supplier_lowest,
                    supplier_highest: supplier.supplier_highest,
                    supplier_tiers_count: supplier.supplier_tiers_count,
                });
            });

            const snapshotSupplierIds = new Set(suppliers.map((supplier) => String(supplier.product_supplier_id)));
            document.querySelectorAll('[data-live-price-supplier-container]').forEach((container) => {
                if (container.dataset.livePriceProduct === String(snapshot.product_id)) {
                    container.hidden = !snapshotSupplierIds.has(container.dataset.livePriceSupplierContainer);
                }
            });
            document.querySelectorAll('[data-live-price-cell][data-live-price-product][data-live-price-supplier]').forEach((cell) => {
                if (cell.dataset.livePriceProduct === String(snapshot.product_id)
                    && !snapshotSupplierIds.has(cell.dataset.livePriceSupplier)) {
                    cell.textContent = '—';
                }
            });
        })
        .catch(() => {
            // The rendered database prices remain visible if the snapshot endpoint is temporarily unavailable.
        });
}

function syncVisiblePriceSubscriptions() {
    if (!window.Echo) return;

    const visibleProductIds = new Set(visiblePriceProductCounts.keys());

    visibleProductIds.forEach((productId) => {
        const audienceRole = document.querySelector('[data-live-price-channel-role]')?.dataset.livePriceChannelRole;
        if (!audienceRole) return;

        const channelName = `product-prices.${productId}.${audienceRole}`;
        const currentSubscription = activePriceSubscriptions.get(productId);
        if (currentSubscription?.channelName === channelName) return;
        if (currentSubscription) {
            window.Echo.leave(currentSubscription.channelName);
            activePriceSubscriptions.delete(productId);
        }

        window.Echo.private(channelName)
            .listen('.product.prices.updated', (update) => applyLivePriceUpdate(update, true))
            .subscribed(() => refreshProductPriceSnapshot(productId));
        activePriceSubscriptions.set(productId, { channelName });
    });

    activePriceSubscriptions.forEach((subscription, productId) => {
        if (!visibleProductIds.has(productId)) {
            window.Echo.leave(subscription.channelName);
            activePriceSubscriptions.delete(productId);
        }
    });
}

const livePriceObserver = typeof IntersectionObserver === 'undefined' ? null : new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
        const element = entry.target;
        if (!/^\d+$/.test(element.dataset.livePriceProduct || '')) return;

        if (entry.isIntersecting && !observedPriceElements.has(element)) {
            observedPriceElements.add(element);
            const id = element.dataset.livePriceProduct;
            visiblePriceProductCounts.set(id, (visiblePriceProductCounts.get(id) || 0) + 1);
        } else if (!entry.isIntersecting && observedPriceElements.has(element)) {
            observedPriceElements.delete(element);
            const id = element.dataset.livePriceProduct;
            const remaining = (visiblePriceProductCounts.get(id) || 1) - 1;
            if (remaining > 0) visiblePriceProductCounts.set(id, remaining);
            else visiblePriceProductCounts.delete(id);
        }
    });

    syncVisiblePriceSubscriptions();
}, { rootMargin: '120px' });

function watchVisiblePriceElements() {
    const elements = document.querySelectorAll('[data-live-price-product]');

    if (!livePriceObserver) {
        elements.forEach((element) => {
            const id = element.dataset.livePriceProduct;
            if (/^\d+$/.test(id || '')) visiblePriceProductCounts.set(id, 1);
        });
    } else {
        elements.forEach((element) => livePriceObserver.observe(element));
    }

    syncVisiblePriceSubscriptions();
}

function resetVisiblePriceSubscriptions() {
    if (window.Echo) {
        activePriceSubscriptions.forEach((subscription) => window.Echo.leave(subscription.channelName));
    }

    activePriceSubscriptions.clear();
    visiblePriceProductCounts.clear();
    observedPriceElements.clear();
    lastLivePriceRevisionByProduct.clear();
    snapshotSequenceByProduct.clear();
    livePriceObserver?.disconnect();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', watchVisiblePriceElements, { once: true });
} else {
    watchVisiblePriceElements();
}
document.addEventListener('livewire:navigated', () => {
    resetVisiblePriceSubscriptions();
    watchVisiblePriceElements();
});

// تعریف global برای استفاده در Blade

// Auto-init برای اسلایدرها
document.addEventListener('DOMContentLoaded', function() {
    // Hero Slider
    const heroSlider = document.querySelector('#hero-slider .splide');
    if (heroSlider && window.Splide) {
        new window.Splide(heroSlider, {
            type: 'fade',
            direction: 'rtl',
            rewind: true,
            autoplay: true,
            interval: 5000,
            pauseOnHover: true,
            pauseOnFocus: true,
            arrows: true,
            pagination: true,
            lazyLoad: 'nearby',
            speed: 800,
            easing: 'cubic-bezier(0.25, 1, 0.5, 1)',
            classes: {
                arrows: 'splide__arrows hero-arrows',
                arrow: 'splide__arrow hero-arrow',
                prev: 'splide__arrow--prev hero-prev',
                next: 'splide__arrow--next hero-next',
                pagination: 'splide__pagination hero-pagination',
                page: 'splide__pagination__page hero-dot',
            },
        }).mount();
    }
});
// Mermaid به صورت lazy load فقط در صفحات مورد نیاز بارگذاری می‌شود
// برای استفاده: window.loadMermaid().then(mermaid => { ... })
window.loadMermaid = async function() {
    if (!window.mermaid) {
        const mermaid = await import('mermaid');
        mermaid.default.initialize({ startOnLoad: false, theme: 'default' });
        window.mermaid = mermaid.default;
    }
    return window.mermaid;
};
