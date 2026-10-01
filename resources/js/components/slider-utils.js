/**
 * ═══════════════════════════════════════════════════════════════
 * توابع مشترک اسلایدرها (Shared Slider Utilities)
 * ═══════════════════════════════════════════════════════════════
 * 
 * این فایل شامل توابع مشترک بین hero-slider و product-slider است
 * برای بهینه‌سازی عملکرد و کاهش تکرار کد
 */

/**
 * Hero Slider Component - اسلایدر اصلی سایت
 * 
 * @param {number} totalSlides - تعداد کل اسلایدها
 * @returns {Object} Alpine.js component object
 */
window.mainSlider = function(totalSlides) {
    return {
        current: 0,
        total: totalSlides,
        paused: false,
        progress: 0,
        progressInterval: null,
        autoPlayDuration: 5000, // 5 ثانیه

        init() {
            if (this.total > 1) {
                this.startAutoPlay();
            }
        },

        startAutoPlay() {
            this.progress = 0;
            const step = 100 / (this.autoPlayDuration / 16.67); // ~60fps
            let lastTime = performance.now();
            
            const animate = (currentTime) => {
                if (this.paused) {
                    this.progressInterval = requestAnimationFrame(animate);
                    return;
                }
                
                const delta = currentTime - lastTime;
                if (delta >= 16.67) { // ~60fps
                    this.progress += step * (delta / 16.67);
                    lastTime = currentTime;
                    
                    if (this.progress >= 100) {
                        this.next();
                        return;
                    }
                }
                
                this.progressInterval = requestAnimationFrame(animate);
            };
            
            this.progressInterval = requestAnimationFrame(animate);
        },

        stopAutoPlay() {
            if (this.progressInterval) {
                cancelAnimationFrame(this.progressInterval);
                this.progressInterval = null;
            }
        },

        resetProgress() {
            this.stopAutoPlay();
            this.progress = 0;
            this.startAutoPlay();
        },

        next() {
            this.current = (this.current + 1) % this.total;
            this.resetProgress();
        },

        prev() {
            this.current = (this.current - 1 + this.total) % this.total;
            this.resetProgress();
        },

        goTo(index) {
            if (index !== this.current) {
                this.current = index;
                this.resetProgress();
            }
        },

        pause() {
            this.paused = true;
        },

        resume() {
            this.paused = false;
        }
    };
};

/**
 * Product Slider Component - اسلایدر محصولات
 * 
 * @returns {Object} Alpine.js component object
 */
window.productSlider = function() {
    return {
        dragged: false,
        startX: 0,
        scrollLeft: 0,
        isDown: false,
        dragThreshold: 8,
        autoScrollTimeout: null,
        isHovered: false,
        isIntersecting: false,
        observer: null,
        
        init() {
            // اضافه کردن event listener برای مدیریت کلیک روی لینک‌ها
            this.$refs.slider.addEventListener('click', (e) => {
                if (this.dragged) {
                    e.preventDefault();
                    e.stopPropagation();
                    e.stopImmediatePropagation();
                    this.dragged = false;
                }
            }, true);
            
            // Intersection Observer برای شروع فقط وقتی اسلایدر دیده شد
            this.observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        this.isIntersecting = true;
                        // شروع auto-scroll با تأخیر 2 ثانیه پس از دیده شدن
                        setTimeout(() => {
                            if (this.isIntersecting && !this.isHovered) {
                                this.startAutoScroll();
                            }
                        }, 2000);
                    } else {
                        this.isIntersecting = false;
                        this.stopAutoScroll();
                    }
                });
            }, { threshold: 0.3 });
            
            this.observer.observe(this.$refs.slider);
            
            // توقف auto-scroll هنگام hover
            this.$refs.slider.addEventListener('mouseenter', () => {
                this.isHovered = true;
                this.pauseAutoScroll();
            });
            
            this.$refs.slider.addEventListener('mouseleave', () => {
                this.isHovered = false;
                if (this.isIntersecting && !this.isDown) {
                    this.startAutoScroll();
                }
            });
            
            // Touch support برای موبایل
            this.$refs.slider.addEventListener('touchstart', () => {
                this.pauseAutoScroll();
            });
            
            this.$refs.slider.addEventListener('touchend', () => {
                // ادامه auto-scroll پس از 2 ثانیه
                setTimeout(() => {
                    if (!this.isHovered && this.isIntersecting) {
                        this.startAutoScroll();
                    }
                }, 2000);
            });
        },
        
        getItemWidth() {
            const slider = this.$refs.slider;
            const containerWidth = slider.clientWidth;
            const gap = 12; // gap-3 = 0.75rem = 12px
            
            // محاسبه عرض هر آیتم بر اساس breakpoint
            if (window.innerWidth < 640) {
                // موبایل: 2.5 کارت
                return (containerWidth * 0.4) + (gap * 0.5);
            } else if (window.innerWidth < 768) {
                // تبلت: 3 کارت
                return (containerWidth * 0.333) + gap;
            } else {
                // دسکتاپ: عرض ثابت w-52 = 208px
                return 220; // 208px + gap
            }
        },
        
        scrollByItem(direction) {
            const slider = this.$refs.slider;
            const itemWidth = this.getItemWidth();
            
            slider.scrollBy({ 
                left: itemWidth * direction,
                behavior: 'smooth' 
            });
            
            // توقف موقت auto-scroll پس از کلیک کاربر
            this.pauseAutoScroll();
            setTimeout(() => {
                if (!this.isHovered && this.isIntersecting) {
                    this.startAutoScroll();
                }
            }, 3000);
        },
        
        startAutoScroll() {
            this.stopAutoScroll();
            
            const slider = this.$refs.slider;
            const itemWidth = this.getItemWidth();
            
            // استفاده از setTimeout به جای setInterval برای عملکرد بهتر
            const doScroll = () => {
                if (!this.isHovered && !this.isDown && this.isIntersecting) {
                    const maxScroll = slider.scrollWidth - slider.clientWidth;
                    
                    if (Math.abs(slider.scrollLeft) >= maxScroll - 10) {
                        slider.scrollTo({ left: 0, behavior: 'smooth' });
                    } else {
                        slider.scrollBy({ left: -itemWidth, behavior: 'smooth' });
                    }
                }
                
                this.autoScrollTimeout = setTimeout(doScroll, 5000);
            };
            
            this.autoScrollTimeout = setTimeout(doScroll, 5000);
        },
        
        stopAutoScroll() {
            if (this.autoScrollTimeout) {
                clearTimeout(this.autoScrollTimeout);
                this.autoScrollTimeout = null;
            }
        },
        
        pauseAutoScroll() {
            this.stopAutoScroll();
        },
        
        startDrag(event) {
            this.isDown = true;
            this.dragged = false;
            this.startX = event.pageX - this.$refs.slider.offsetLeft;
            this.scrollLeft = this.$refs.slider.scrollLeft;
            this.pauseAutoScroll();
        },
        
        stopDrag() {
            this.isDown = false;
            if (!this.isHovered && this.isIntersecting) {
                this.startAutoScroll();
            }
        },
        
        onDrag(event) {
            if (!this.isDown) return;

            event.preventDefault();

            const currentX = event.pageX - this.$refs.slider.offsetLeft;
            const distance = currentX - this.startX;

            if (Math.abs(distance) > this.dragThreshold) {
                this.dragged = true;
            }

            this.$refs.slider.scrollLeft = this.scrollLeft - distance;
        }
    };
};

/**
 * Animate Count - شمارنده انیمیت‌دار
 * 
 * @param {number} target - عدد هدف
 * @returns {Object} Alpine.js component object
 */
window.animateCount = function(target) {
    return {
        display: '0',
        started: false,
        init() {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting && !this.started) {
                        this.started = true;
                        this.countUp(target);
                        observer.disconnect();
                    }
                });
            }, { threshold: 0.3 });
            observer.observe(this.$el);
        },
        countUp(target) {
            const duration = 1500;
            const startTime = performance.now();

            const step = (currentTime) => {
                const elapsed = currentTime - startTime;
                const progress = Math.min(elapsed / duration, 1);
                const eased = progress === 1 ? 1 : 1 - Math.pow(2, -10 * progress);
                const value = Math.floor(eased * target);

                this.display = value.toLocaleString('fa-IR');

                if (progress < 1) {
                    requestAnimationFrame(step);
                } else {
                    this.display = target.toLocaleString('fa-IR');
                }
            };
            requestAnimationFrame(step);
        }
    };
};
