# ایجنت‌های متخصص پروژه B2B Marketplace

این سند شامل فهرست کامل ایجنت‌های متخصص برای طراحی، توسعه، تست و استقرار پروژه است.

---

## ۱. ایجنت معماری و طراحی سیستم (System Architect)

### مسئولیت‌ها
- طراحی معماری کلی سیستم
- تعیین الگوهای طراحی و بهترین شیوه‌ها
- بررسی مقیاس‌پذیری و انعطاف‌پذیری
- مستندسازی تصمیمات معماری

### موارد بررسی
- [ ] انطباق با اصول SOLID و Clean Architecture
- [ ] جداسازی مسئولیت‌ها (Separation of Concerns)
- [ ] طراحی Domain-Driven Design برای ماژول‌های پیچیده
- [ ] الگوی Repository و Service Layer
- [ ] مدیریت وابستگی‌ها و Dependency Injection
- [ ] طراحی API Contract First
- [ ] استراتژی versioning برای APIها
- [ ] مدیریت configuration و environment
- [ ] طراحی Event-Driven برای فرآیندهای ناهمگام
- [ ] استراتژی Error Handling و Logging

---

## ۲. ایجنت پایگاه داده و MySQL (Database Expert)

### مسئولیت‌ها
- طراحی و بهینه‌سازی schema
- مدیریت migrations و seeders
- تنظیم ایندکس‌ها و query optimization

### موارد بررسی
- [ ] **نرمال‌سازی:** سطح مناسب نرمال‌سازی (3NF)
- [ ] **ایندکس‌ها:**
  - Primary Keys و Foreign Keys
  - Unique Indexes برای ستون‌های منحصربه‌فرد
  - Composite Indexes برای کوئری‌های ترکیبی
  - Full-text Indexes برای جستجو
- [ ] **Query Optimization:**
  - تحلیل EXPLAIN برای کوئری‌های کند
  - جلوگیری از N+1 با Eager Loading
  - استفاده از subquery و CTE
  - Pagination مناسب برای داده‌های زیاد
- [ ] **Data Integrity:**
  - Constraints (NOT NULL, CHECK, DEFAULT)
  - Triggers برای logic پیچیده
  - Soft Deletes برای داده‌های حساس
- [ ] **Backup و Recovery:**
  - استراتژی پشتیبان‌گیری خودکار
  - Point-in-time recovery
  - Replication برای high availability
- [ ] **Migration Safety:**
  - Non-destructive migrations
  - Rollback strategies
  - Zero-downtime deployments

---

## ۳. ایجنت امنیت چندلایه (Security Expert)

### مسئولیت‌ها
- تحلیل و رفع آسیب‌پذیری‌ها
- پیاده‌سازی لایه‌های امنیتی
- ممیزی امنیتی کد

### موارد بررسی

#### ۳.۱ امنیت احراز هویت (Authentication Security)
- [ ] Password hashing (bcrypt/argon2)
- [ ] Rate limiting برای login attempts
- [ ] Session management و fixation prevention
- [ ] Multi-factor authentication (2FA)
- [ ] OAuth/OIDC security best practices
- [ ] Password reset flow security
- [ ] Remember token security

#### ۳.۲ مجوزدهی و کنترل دسترسی (Authorization)
- [ ] Role-Based Access Control (RBAC)
- [ ] Permission checks در controllers و policies
- [ ] Route middleware برای نقش‌ها
- [ ] Resource ownership validation
- [ ] API token scopes و abilities

#### ۳.۳ امنیت ورودی‌ها (Input Validation)
- [ ] Validation rules کامل برای همه فیلدها
- [ ] Sanitization ورودی‌های HTML
- [ ] Type casting و strict typing
- [ ] File upload validation (MIME, size, content)
- [ ] SQL Injection prevention (parameterized queries)
- [ ] XSS prevention (output escaping)
- [ ] CSRF protection برای فرم‌ها
- [ ] Mass assignment protection ($fillable/$guarded)

#### ۳.۴ امنیت API
- [ ] API authentication (Sanctum/Passport)
- [ ] Rate limiting per user/IP
- [ ] Request throttling
- [ ] Response data filtering
- [ ] Error message sanitization
- [ ] CORS configuration
- [ ] API versioning

#### ۳.۵ امنیت فایل و آپلود
- [ ] Allowed file types whitelist
- [ ] Maximum file size enforcement
- [ ] Virus scanning برای آپلودها
- [ ] Secure file naming
- [ ] Storage outside public directory
- [ ] Image processing security

#### ۳.۶ امنیت Session و Cookie
- [ ] HttpOnly و Secure flags
- [ ] SameSite attribute
- [ ] Session timeout configuration
- [ ] Session encryption
- [ ] Regenerate session on login

#### ۳.۷ Logging و Monitoring
- [ ] Audit trail برای عملیات حساس
- [ ] Failed login attempt logging
- [ ] Suspicious activity detection
- [ ] Security event alerting

---

## ۴. ایجنت فرانت‌اند و Livewire (Frontend Expert)

### مسئولیت‌ها
- طراحی و پیاده‌سازی UI components
- مدیریت state در Livewire
- تعاملات کاربری و UX

### موارد بررسی
- [ ] **Livewire Best Practices:**
  - Proper component lifecycle usage
  - Optimistic UI updates
  - Dirty state detection
  - Loading states و feedback
  - Error boundary handling
- [ ] **State Management:**
  - Public vs protected properties
  - Session vs component state
  - Query string preservation
  - Form state persistence
- [ ] **Alpine.js Integration:**
  - Proper x-data scoping
  - x-init و x-effect usage
  - Event handling
  - Transition effects
- [ ] **Form Handling:**
  - Validation feedback
  - Real-time validation
  - File upload progress
  - Multi-step forms
- [ ] **Real-time Features:**
  - Livewire polling optimization
  - Echo/webSocket integration
  - Event broadcasting

---

## ۵. ایجنت طراحی UI/UX و تجربه کاربری (UX Expert)

### مسئولیت‌ها
- طراحی تجربه کاربری بهینه
- رعایت اصول طراحی
- تست کاربردپذیری

### موارد بررسی
- [ ] **Information Architecture:**
  - Navigation structure
  - Breadcrumbs و wayfinding
  - Content hierarchy
  - Search functionality
- [ ] **User Flows:**
  - Onboarding flow
  - Checkout/process flows
  - Error recovery paths
  - Task completion rate optimization
- [ ] **Accessibility (WCAG 2.1 AA):**
  - Color contrast (4.5:1 minimum)
  - Keyboard navigation
  - Screen reader compatibility
  - Focus management
  - ARIA labels
  - Alt text برای images
- [ ] **Persian/RTL Support:**
  - Right-to-left layout
  - Persian typography
  - Number formatting (Persian digits)
  - Date formatting (Jalali)
  - Persian text rendering
- [ ] **User Feedback:**
  - Loading states
  - Success/error messages
  - Confirmation dialogs
  - Progress indicators
- [ ] **Forms UX:**
  - Field labels و help text
  - Error message positioning
  - Auto-focus و tab order
  - Autocomplete attributes

---

## ۶. ایجنت رسپانسیو و موبایل (Mobile & Responsive Expert)

### مسئولیت‌ها
- طراحی رسپانسیو
- بهینه‌سازی برای موبایل
- PWA features

### موارد بررسی
- [ ] **Responsive Breakpoints:**
  - Mobile first approach
  - Tablet optimization
  - Desktop layouts
  - Large screen handling
- [ ] **Touch Interaction:**
  - Touch target size (minimum 44px)
  - Swipe gestures
  - Pull to refresh
  - Touch feedback
- [ ] **Mobile Performance:**
  - Image lazy loading
  - Critical CSS inline
  - Resource hints (preload, prefetch)
  - Service Worker caching
- [ ] **PWA Features:**
  - Manifest.json
  - Service Worker
  - Offline functionality
  - Push notifications
  - Add to home screen
- [ ] **Mobile-specific UX:**
  - Bottom navigation
  - Sticky headers
  - Infinite scroll vs pagination
  - Image optimization (WebP, AVIF)
- [ ] **Cross-browser Testing:**
  - iOS Safari quirks
  - Android Chrome
  - Samsung Internet
  - Desktop browsers

---

## ۷. ایجنت سئو و موتورهای جستجو (SEO Expert)

### مسئولیت‌ها
- بهینه‌سازی برای موتورهای جستجو
- پیاده‌سازی structured data
- بهبود Core Web Vitals

### موارد بررسی
- [ ] **Technical SEO:**
  - XML Sitemap
  - Robots.txt
  - Canonical URLs
  - URL structure (clean, semantic)
  - HTTPS enforcement
  - Redirect chains
  - 404 handling
  - Pagination SEO (rel="next/prev")
- [ ] **On-Page SEO:**
  - Title tags (unique, descriptive)
  - Meta descriptions
  - Heading hierarchy (H1-H6)
  - Image alt attributes
  - Internal linking
  - Breadcrumb markup
- [ ] **Structured Data (Schema.org):**
  - Organization schema
  - Product schema
  - BreadcrumbList schema
  - LocalBusiness schema
  - FAQ schema
  - Article schema
- [ ] **Core Web Vitals:**
  - LCP (Largest Contentful Paint) < 2.5s
  - FID (First Input Delay) < 100ms
  - CLS (Cumulative Layout Shift) < 0.1
  - INP (Interaction to Next Paint) < 200ms
- [ ] **Google Search Console:**
  - Index coverage
  - URL inspection
  - Sitemap submission
  - Mobile usability
  - Security issues
- [ ] **Performance SEO:**
  - Server response time
  - Render-blocking resources
  - Image optimization
  - Font loading strategy

---

## ۸. ایجنت AI و نمایش در هوش مصنوعی (AI Search Expert)

### مسئولیت‌ها
- بهینه‌سازی برای AI Overview
- پیاده‌سازی AI-readable content
- Structured data برای AI

### موارد بررسی
- [ ] **AI-Optimized Content:**
  - Clear, factual writing
  - Question-answer format
  - Concise explanations
  - Data tables و lists
  - Definition boxes
- [ ] **Schema Markup for AI:**
  - FAQPage schema
  - HowTo schema
  - QAPage schema
  - Speakable schema
  - ClaimReview schema
- [ ] **Content Structure:**
  - Clear headings
  - Bullet points
  - Numbered steps
  - Comparison tables
  - Summary sections
- [ ] **Entity Optimization:**
  - Named entity recognition
  - Knowledge graph optimization
  - Brand entity consistency
- [ ] **AI Search Features:**
  - Featured snippet optimization
  - People Also Ask targeting
  - Knowledge panel eligibility

---

## ۹. ایجنت کارایی و کش‌گذاری (Performance Expert)

### مسئولیت‌ها
- بهینه‌سازی سرعت
- پیاده‌سازی caching
- تحلیل bottleneck

### موارد بررسی
- [ ] **Server-side Caching:**
  - Redis configuration
  - Cache keys strategy
  - Cache tagging
  - Cache invalidation
  - Model caching
  - Query result caching
  - View caching
  - Config caching (php artisan config:cache)
  - Route caching (php artisan route:cache)
  - Event caching
- [ ] **Browser Caching:**
  - Cache-Control headers
  - ETag validation
  - Last-Modified headers
  - Expires headers
  - Service Worker cache
- [ ] **CDN Configuration:**
  - Static assets delivery
  - Image optimization
  - CSS/JS minification
  - Gzip/Brotli compression
- [ ] **Database Optimization:**
  - Query caching
  - Connection pooling
  - Read replicas
  - Query logging برای slow queries
- [ ] **Asset Optimization:**
  - Vite build optimization
  - Code splitting
  - Tree shaking
  - Image lazy loading
  - Font subsetting
- [ ] **Performance Monitoring:**
  - Laravel Telescope
  - Application performance monitoring (APM)
  - Real User Monitoring (RUM)
  - Server metrics (CPU, Memory, I/O)

---

## ۱۰. ایجنت هاست اشتراکی و استقرار (Deployment Expert)

### مسئولیت‌ها
- آماده‌سازی برای deployment
- تنظیم محیط production
- مدیریت shared hosting

### موارد بررسی
- [ ] **Hosting Compatibility:**
  - PHP version (8.2+)
  - Required extensions (bcmath, ctype, fileinfo, json, mbstring, openssl, pdo, tokenizer, xml)
  - Composer availability
  - Storage permissions
  - Symlink support
- [ ] **Environment Configuration:**
  - APP_ENV=production
  - APP_DEBUG=false
  - APP_KEY generation
  - Database credentials
  - Mail configuration
  - Queue driver
  - Session driver
- [ ] **Security Hardening:**
  - Public folder as document root
  - .htaccess configuration
  - Disable directory listing
  - Hide sensitive files
  - Force HTTPS
- [ ] **Deployment Process:**
  - Zero-downtime deployment
  - Maintenance mode
  - Database migration order
  - Asset compilation
  - Storage:link
  - Config cache
  - Route cache
  - View cache
- [ ] **Monitoring & Logging:**
  - Error logging
  - Access logs
  - Performance logs
  - Security logs
- [ ] **Backup Strategy:**
  - Database backup
  - File backup
  - Code backup (git)
  - Environment backup

---

## ۱۱. ایجنت تست و QA (Testing Expert)

### مسئولیت‌ها
- نوشتن و نگهداری تست‌ها
- Code coverage
- رگرسیون تست

### موارد بررسی
- [ ] **Unit Tests:**
  - Model tests
  - Service tests
  - Helper tests
  - Policy tests
- [ ] **Feature Tests:**
  - HTTP tests برای routes
  - API tests
  - Authentication tests
  - Authorization tests
  - Form validation tests
  - File upload tests
- [ ] **Browser Tests (Dusk):**
  - User flows
  - JavaScript interactions
  - Form submissions
  - Navigation tests
- [ ] **Test Coverage:**
  - Minimum 80% coverage
  - Critical path coverage 100%
  - Edge case testing
  - Error scenario testing
- [ ] **Test Data:**
  - Factories
  - Seeders
  - Mock data
  - Test environments

---

## ۱۲. ایجنت API و یکپارچگی (API Expert)

### مسئولیت‌ها
- طراحی و مستندسازی API
- مدیریت versioning
- Client compatibility

### موارد بررسی
- [ ] **API Design:**
  - RESTful conventions
  - Resource naming
  - HTTP methods usage
  - Status codes
  - Response format consistency
- [ ] **Documentation:**
  - OpenAPI/Swagger
  - Request/Response examples
  - Authentication docs
  - Rate limiting docs
  - Error code reference
- [ ] **Versioning:**
  - URL versioning (/api/v1/)
  - Backward compatibility
  - Deprecation policy
  - Breaking change documentation
- [ ] **Client Libraries:**
  - SDK documentation
  - SDK examples
  - Client error handling

---

## ۱۳. ایجنت پرداخت و مالی (Payment Expert)

### مسئولیت‌ها
- یکپارچگی درگاه پرداخت
- امنیت تراکنش‌ها
- تسویه حساب

### موارد بررسی
- [ ] **Payment Gateway:**
  - Gateway integration
  - Transaction logging
  - Callback handling
  - Verify و settle
- [ ] **Security:**
  - Payment data encryption
  - Transaction verification
  - Fraud detection
  - Duplicate payment prevention
- [ ] **Order Management:**
  - Order status tracking
  - Inventory management
  - Invoice generation
  - Refund processing
- [ ] **Compliance:**
  - PCI DSS awareness
  - Data retention policy
  - Audit trail

---

## ۱۴. ایجنت بومی‌سازی و فارسی (Localization Expert)

### مسئولیت‌ها
- ترجمه و فارسی‌سازی
- RTL support
- Persian formatting

### موارد بررسی
- [ ] **Translation:**
  - lang/fa.json completeness
  - Dynamic text translation
  - Pluralization rules
  - Date formatting (Jalali)
  - Number formatting
  - Currency formatting
- [ ] **RTL Support:**
  - RTL CSS framework
  - Direction-aware components
  - Text alignment
  - Icon mirroring
- [ ] **Persian Typography:**
  - Font selection
  - Line height
  - Letter spacing
  - Persian number rendering

---

## ۱۵. ایجنت لاگینگ و مانیتورینگ (Logging Expert)

### مسئولیت‌ها
- تنظیم logging
- Error tracking
- Performance monitoring

### موارد بررسی
- [ ] **Logging Configuration:**
  - Log channels (stack, daily, slack)
  - Log levels (emergency, alert, critical, error, warning, notice, info, debug)
  - Log rotation
  - Log retention
- [ ] **Error Tracking:**
  - Sentry/Bugsnag integration
  - Stack trace logging
  - Context logging
  - User context
- [ ] **Performance Monitoring:**
  - Slow query logging
  - Memory usage logging
  - Response time logging
  - Queue monitoring

---

## ۱۶. ایجنت صف و Job (Queue Expert)

### مسئولیت‌ها
- تنظیم queues
- Job management
- Worker configuration

### موارد بررسی
- [ ] **Queue Configuration:**
  - Queue driver (Redis/Database)
  - Queue priorities
  - Retry configuration
  - Timeout settings
- [ ] **Job Management:**
  - Job dispatching
  - Delayed jobs
  - Job chaining
  - Job batching
  - Failed job handling
- [ ] **Worker Configuration:**
  - Worker processes
  - Worker monitoring
  - Auto-restart
  - Memory limits

---

## ۱۷. ایجنت فایل و ذخیره‌سازی (Storage Expert)

### مسئولیت‌ها
- مدیریت فایل‌ها
- Storage configuration
- File optimization

### موارد بررسی
- [ ] **Storage Configuration:**
  - Local vs cloud storage
  - Storage disks
  - Access control
- [ ] **File Management:**
  - Upload handling
  - File validation
  - Image processing
  - File naming conventions
- [ ] **Optimization:**
  - Image compression
  - Image resizing
  - Format conversion (WebP)
  - Lazy loading

---

## نقش‌های کاربری پروژه

بر اساس بررسی کد، نقش‌های زیر در پروژه تعریف شده‌اند:

| نقش | توضیح |
|-----|-------|
| SUPER_ADMIN | مدیر ارشد سیستم |
| ADMIN | مدیر عادی |
| SUPPLIER | تأمین‌کننده محصول |
| REGULAR_SUPPLIER | تأمین‌کننده عادی |
| OUTSOURCER | پیمانکار محتوا |
| REVIEWER | بازبین محتوا |
| FINANCE_MANAGER | مدیر مالی |
| RETAILER_PREMIUM | کاسب ویژه |
| RETAILER_BASIC | کاسب عادی |
| END_USER | کاربر عادی |

---

## فرآیند بازبینی

برای هر تغییر، ایجنت‌های مرتبط باید به ترتیب زیر فراخوانی شوند:

1. **ایجنت معماری** - تأیید طراحی کلی
2. **ایجنت امنیت** - بررسی آسیب‌پذیری‌ها (برای تغییرات حساس)
3. **ایجنت پایگاه داده** - بررسی migrations و کوئری‌ها
4. **ایجنت API** - بررسی API endpoints
5. **ایجنت فرانت‌اند** - بررسی Livewire components
6. **ایجنت UX** - بررسی تجربه کاربری
7. **ایجنت رسپانسیو** - بررسی موبایل
8. **ایجنت سئو** - بررسی SEO
9. **ایجنت کارایی** - بررسی کش و سرعت
10. **ایجنت تست** - بررسی coverage
11. **ایجنت استقرار** - بررسی deployment readiness

---

**تاریخ ایجاد:** 1407/07/19  
**آخرین بروزرسانی:** 1407/07/19  
**وضعیت:** فعال
