import './bootstrap';

// ✅ Import slider utilities (shared components)
import './components/slider-utils.js';

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
