# 📚 راهنمای استفاده از ایجنت‌های بررسی کد

## 🎯 روش‌های فراخوانی تمام ایجنت‌ها

### روش 1: استفاده از Code Review Coordinator (توصیه می‌شود)

این ساده‌ترین روش است. فقط از ایجنت هماهنگ‌کننده استفاده کنید:

```
@code-review-coordinator بررسی کامل ProductController را انجام بده
```

یا:

```
@code-review-coordinator پروژه را برای deploy به production آماده کن
```

**مزایا:**
- ✅ خودکار همه ایجنت‌های مرتبط را فراخوانی می‌کند
- ✅ گزارش یکپارچه و ساختاریافته
- ✅ اولویت‌بندی خودکار مشکلات
- ✅ Action plan عملی

---

### روش 2: فراخوانی دستی تک‌به‌تک

اگر می‌خواهید control بیشتری داشته باشید:

```
# 1. امنیت
@security-api-expert بررسی امنیتی ProductController

# 2. پایگاه داده
@database-storage-expert بررسی queries و relationships در Product Model

# 3. کیفیت کد
@testing-quality-expert بررسی code quality و test coverage

# 4. فرانت‌اند
@frontend-ui-expert بررسی UI/UX کامپوننت محصولات

# 5. عملکرد
@performance-scalability-expert بررسی performance و caching

# 6. سئو
@seo-ranking-expert بررسی SEO صفحه محصول

# 7. استقرار
@deployment-hosting-expert بررسی تنظیمات production
```

**مزایا:**
- ✅ کنترل کامل بر ترتیب و جزئیات
- ✅ می‌توانید فقط ایجنت‌های مورد نیاز را فراخوانی کنید

**معایب:**
- ❌ زمان‌بر است
- ❌ نیاز به جمع‌آوری دستی نتایج

---

### روش 3: استفاده از invoke_sub_agent در کد

برای استفاده برنامه‌نویسی (در یک تسک یا اسکریپت):

```javascript
// مثال: بررسی کامل یک فیچر
const agents = [
    'security-api-expert',
    'database-storage-expert',
    'testing-quality-expert',
    'frontend-ui-expert',
    'performance-scalability-expert',
    'seo-ranking-expert',
    'deployment-hosting-expert'
];

const target = 'Product Feature';
const results = {};

for (const agentName of agents) {
    console.log(`🔍 Invoking ${agentName}...`);
    
    const result = await invoke_sub_agent({
        name: agentName,
        prompt: `بررسی ${target} را انجام بده`,
        contextFiles: [
            'app/Models/Product.php',
            'app/Http/Controllers/ProductController.php',
            'app/Livewire/Products/*.php'
        ]
    });
    
    results[agentName] = result;
}

// ایجاد گزارش یکپارچه
generateUnifiedReport(results);
```

---

## 📋 سناریوهای مختلف استفاده

### سناریو 1: بررسی یک Controller

**هدف**: بررسی ProductController قبل از merge

**دستور**:
```
@code-review-coordinator بررسی ProductController را انجام بده
```

**ایجنت‌های فراخوانی شده**:
- ✅ security-api-expert (امنیت و validation)
- ✅ testing-quality-expert (کیفیت کد و تست)
- ✅ performance-scalability-expert (query optimization)

**خروجی مورد انتظار**:
- لیست مشکلات امنیتی
- پیشنهادات refactoring
- بررسی N+1 queries
- بررسی test coverage

---

### سناریو 2: بررسی یک Livewire Component

**هدف**: بررسی ProductList.php (Livewire)

**دستور**:
```
@code-review-coordinator بررسی کامل Livewire Component ProductList
```

**ایجنت‌های فراخوانی شده**:
- ✅ frontend-ui-expert (UI/UX و accessibility)
- ✅ security-api-expert (authorization)
- ✅ performance-scalability-expert (real-time updates)
- ✅ testing-quality-expert (component tests)

---

### سناریو 3: بررسی یک Model

**هدف**: بررسی Product Model

**دستور**:
```
@code-review-coordinator بررسی Product Model را انجام بده
```

**ایجنت‌های فراخوانی شده**:
- ✅ database-storage-expert (relationships، indexes)
- ✅ security-api-expert (mass assignment)
- ✅ testing-quality-expert (business logic tests)

---

### سناریو 4: آماده‌سازی برای Production

**هدف**: پروژه را برای deploy آماده کنیم

**دستور**:
```
@code-review-coordinator پروژه را برای production deployment آماده کن
```

**ایجنت‌های فراخوانی شده**:
- ✅ همه ایجنت‌ها (7 ایجنت)

**تمرکز ویژه بر**:
- 🔴 مشکلات Critical امنیتی
- 🔴 تنظیمات .env و .htaccess
- 🔴 SSL و HTTPS
- 🔴 File permissions
- 🟡 Performance optimization
- 🟡 Cache configuration
- 🟡 SEO readiness

---

### سناریو 5: بررسی کل پروژه (Quarterly Review)

**هدف**: بررسی جامع سه‌ماهه

**دستور**:
```
@code-review-coordinator بررسی جامع کل پروژه را انجام بده و گزارش مفصل ارائه کن
```

**ایجنت‌های فراخوانی شده**:
- ✅ همه ایجنت‌ها

**خروجی**:
- گزارش جامع 50+ صفحه‌ای
- آمار دقیق (test coverage، performance metrics، security score)
- مقایسه با بررسی قبلی
- Action plan 3 ماهه

---

## 🎨 سفارشی‌سازی درخواست‌ها

### درخواست با Focus خاص

```
@code-review-coordinator بررسی ProductController با تمرکز بر امنیت و performance
```

در این حالت، coordinator بیشتر روی security-api-expert و performance-scalability-expert تمرکز می‌کند.

### درخواست برای فیچر کامل

```
@code-review-coordinator بررسی کامل فیچر Authentication (Models, Controllers, Middleware, Views, Tests)
```

### درخواست با محدوده زمانی

```
@code-review-coordinator بررسی تغییرات 2 هفته اخیر را انجام بده
```

---

## 📊 فرمت گزارش نهایی

گزارش coordinator شامل این بخش‌هاست:

```markdown
# 📊 گزارش جامع بررسی کد

## ℹ️ اطلاعات بررسی
- تاریخ، هدف، تعداد ایجنت‌ها

## 📈 خلاصه اجرایی
- آمار مشکلات (Critical/High/Medium/Low)
- نمره‌های هر بخش (امنیت، کیفیت، ...)
- نمره کلی

## 🔴 مشکلات Critical
- لیست تمام مشکلات فوری

## 🟡 مشکلات High Priority
- لیست مشکلات مهم

## 🟢 مشکلات Medium Priority

## ⚪ مشکلات Low Priority

## ✅ نکات مثبت

## 📋 Action Plan
- فاز 1: امروز
- فاز 2: این هفته
- فاز 3: این ماه
- فاز 4: بلندمدت

## 📑 گزارش‌های تفصیلی
- گزارش کامل هر ایجنت (در collapsible sections)

## 🎯 توصیه‌های کلی

## 📊 مقایسه با بررسی قبلی

## ⏱️ زمان‌بندی پیشنهادی
```

---

## 💡 نکات و ترفندها

### 1. استفاده از Context Files

برای بررسی دقیق‌تر، فایل‌های مرتبط را مشخص کنید:

```
@code-review-coordinator بررسی فیچر محصولات شامل:
- app/Models/Product.php
- app/Http/Controllers/ProductController.php
- app/Livewire/Products/*.php
- resources/views/products/*.blade.php
```

### 2. بررسی قبل از PR/MR

```
@code-review-coordinator بررسی تغییرات branch feature/new-product-filter را قبل از merge انجام بده
```

### 3. بررسی بعد از Bug Fix

```
@code-review-coordinator بررسی کن که آیا fix اخیر bug #123 مشکلات دیگری ایجاد کرده؟
```

### 4. بررسی Performance بعد از افزودن Feature

```
@code-review-coordinator بررسی کن که feature جدید فیلترینگ چه تاثیری بر performance داشته
```

---

## 🔧 تنظیمات پیشرفته

### فعال‌سازی/غیرفعال‌سازی ایجنت‌های خاص

اگر نمی‌خواهید یک ایجنت خاص را فراخوانی کنید:

```
@code-review-coordinator بررسی ProductController بدون frontend-ui-expert
```

### تنظیم سطح جزئیات گزارش

```
@code-review-coordinator بررسی سریع پروژه (خلاصه)
@code-review-coordinator بررسی مفصل پروژه (جزئیات کامل)
```

---

## 📅 برنامه بررسی توصیه شده

### روزانه (Daily Reviews)
- بررسی تغییرات هر developer قبل از commit
- استفاده از تعداد محدودی ایجنت (security + testing)

### هفتگی (Weekly Reviews)
- بررسی کل features تکمیل شده
- استفاده از 4-5 ایجنت مرتبط

### ماهانه (Monthly Reviews)
- بررسی جامع کل پروژه
- استفاده از همه ایجنت‌ها
- مقایسه با ماه قبل

### فصلی (Quarterly Reviews)
- بررسی استراتژیک
- همه ایجنت‌ها + گزارش مفصل
- برنامه‌ریزی 3 ماه آینده

---

## 🚦 Best Practices

### ✅ توصیه می‌شود

1. **قبل از هر PR**: بررسی با coordinator
2. **قبل از deploy**: بررسی production readiness
3. **بعد از هر sprint**: بررسی کیفیت کلی
4. **ماهانه**: بررسی جامع با trend analysis

### ❌ توصیه نمی‌شود

1. بررسی تک‌تک ایجنت‌ها برای هر تغییر کوچک (از coordinator استفاده کنید)
2. نادیده گرفتن مشکلات Critical
3. بررسی بدون context (همیشه فایل‌های مرتبط را مشخص کنید)

---

## 🆘 رفع مشکلات رایج

### مشکل: خروجی خیلی طولانی است

**راه‌حل**: از گزینه خلاصه استفاده کنید
```
@code-review-coordinator بررسی خلاصه پروژه
```

### مشکل: یکی از ایجنت‌ها خطا می‌دهد

**راه‌حل**: coordinator به طور خودکار آن را skip می‌کند و به بقیه ادامه می‌دهد

### مشکل: گزارش‌ها بی‌ارتباط هستند

**راه‌حل**: context files را دقیق‌تر مشخص کنید

---

## 📞 دریافت کمک

اگر سوالی دارید:

1. این مستند را بخوانید
2. مثال‌های بالا را امتحان کنید
3. از coordinator با دستور ساده شروع کنید:
   ```
   @code-review-coordinator help
   ```

---

**نسخه مستند**: 1.0  
**آخرین بروزرسانی**: 1404/05/21  
**نویسنده**: Development Team
