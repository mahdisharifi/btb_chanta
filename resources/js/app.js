import './bootstrap';
import '@splidejs/splide/css';
import Splide from '@splidejs/splide';

// تعریف global برای استفاده در Blade
window.Splide = Splide;

// Auto-init برای اسلایدرها
document.addEventListener('DOMContentLoaded', function() {
    // Hero Slider
    const heroSlider = document.querySelector('#hero-slider .splide');
    if (heroSlider) {
        new Splide(heroSlider, {
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
import mermaid from 'mermaid';
mermaid.initialize({ startOnLoad: true, theme: 'default' });
window.mermaid = mermaid; // برای استفاده در لایووایر
