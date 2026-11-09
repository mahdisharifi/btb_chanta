const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');

const context = vm.createContext({
    document: { readyState: 'loading', addEventListener() {} },
    window: { getComputedStyle: () => ({ display: 'block', visibility: 'visible' }) },
});
vm.runInContext(fs.readFileSync('resources/js/components/product-create-guide.js', 'utf8') + '\nthis.Guide = ProductCreationGuide; this.stepsFor = collectSteps;', context);

const state = { productTourImageCount: '0', productTourDragVersion: '0' };
const node = (key, order) => ({
    dataset: { productTourStep: key, productTourOrder: String(order) },
    isConnected: true, parentElement: null, closest: () => null,
    getBoundingClientRect: () => ({ width: 200, height: 100 }), querySelector: () => null,
});
const root = {
    dataset: { productTourMediaRequired: 'true' },
    querySelector: (selector) => selector.includes('data-product-tour-image-count') || selector.includes('data-product-tour-drag-version') ? { dataset: state } : null,
    querySelectorAll: () => [node('media-overview', 1), node('gallery-images', 2), node('product-video', 5), node('title', 10)],
};
const guide = Object.create(context.Guide.prototype);
guide.root = root;
const warning = (key) => guide.mediaRequirementMessage({ key });
assert.ok(warning('gallery-images'));
state.productTourImageCount = '1';
assert.equal(warning('gallery-images'), null);
assert.ok(warning('gallery-more-images'));
state.productTourImageCount = '3';
assert.equal(warning('gallery-more-images'), null);
assert.ok(warning('gallery-reorder'));
state.productTourDragVersion = '1';
assert.equal(warning('gallery-reorder'), null);
state.productTourImageCount = '2';
assert.ok(warning('gallery-reorder'));
const steps = Array.from(context.stepsFor(root));
assert.deepEqual(steps.map((step) => step.key), ['welcome', 'media-overview', 'gallery-images', 'gallery-more-images', 'gallery-reorder', 'product-video', 'title']);
const before = steps.find((step) => step.key === 'gallery-images').mediaState;
state.productTourImageCount = '3';
assert.notEqual(context.stepsFor(root).find((step) => step.key === 'gallery-images').mediaState, before);
root.dataset.productTourMediaRequired = 'false';
assert.equal(warning('gallery-images'), null);
assert.ok(!context.stepsFor(root).some((step) => step.key === 'gallery-reorder'));
console.log('PASS: required uploads, drag completion, deletion revalidation, guide order and non-product isolation');

// A short desktop viewport must shrink the guide into free space rather than cover images.
context.window.innerWidth = 1100;
context.window.innerHeight = 720;
const positioningGuide = Object.create(context.Guide.prototype);
const bounds = { left: 40, right: 1040, top: 220, bottom: 500 };
positioningGuide.active = true;
positioningGuide.steps = [{ key: 'gallery-reorder' }];
positioningGuide.index = 0;
positioningGuide.getSpotlightRect = () => bounds;
positioningGuide.syncResponsiveCard = () => {};
positioningGuide.pieces = [];
positioningGuide.root = { getBoundingClientRect: () => ({ left: 24, right: 1060 }), querySelectorAll: () => [] };
positioningGuide.card = {
    style: {}, classList: { toggle() {} },
    getBoundingClientRect: () => ({ width: 360, height: 340 }),
};
positioningGuide.positionCard();
const top = parseFloat(positioningGuide.card.style.top);
const height = parseFloat(positioningGuide.card.style.maxHeight) || 340;
assert.ok(top + height <= bounds.top || top >= bounds.bottom, 'Guide must not overlap draggable images');
assert.ok(top >= 0 && top + height <= 720, 'Guide must remain within the viewport');
positioningGuide.steps = [{ key: 'title' }];
positioningGuide.positionCard();
assert.equal(positioningGuide.card.style.maxHeight, '', 'Other steps must restore normal guide height');
console.log('PASS: desktop drag guidance stays outside images and restores normal layout');
