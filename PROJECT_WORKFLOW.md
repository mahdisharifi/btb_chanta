# Workflow Diagram - B2B Marketplace

## ساختار نقش‌های کاربری

```mermaid
graph TD
    subgraph Users["👥 نقش‌های کاربری"]
        SUPER_ADMIN["🔐 Super Admin<br/>مدیر ارشد"]
        ADMIN["👤 Admin<br/>مدیر"]
        SUPPLIER["🏭 Supplier<br/>تأمین‌کننده"]
        REGULAR_SUPPLIER["📦 Regular Supplier<br/>تأمین‌کننده عادی"]
        OUTSOURCER["✍️ Outsourcer<br/>پیمانکار محتوا"]
        REVIEWER["👁️ Reviewer<br/>بازبین محتوا"]
        FINANCE_MANAGER["💰 Finance Manager<br/>مدیر مالی"]
        RETAILER_PREMIUM["⭐ Retailer Premium<br/>کاسب ویژه"]
        RETAILER_BASIC["🛒 Retailer Basic<br/>کاسب عادی"]
        END_USER["👤 End User<br/>کاربر عادی"]
    end
```

## ساختار ماژول‌های اصلی

```mermaid
graph TB
    subgraph Core["🔧 هسته اصلی"]
        User["User<br/>کاربران"]
        Product["Product<br/>محصولات"]
        Category["Category<br/>دسته‌بندی"]
        Supplier["Supplier<br/>تأمین‌کنندگان"]
    end
    
    subgraph Pricing["💰 سیستم قیمت‌گذاری"]
        ProductPrice["Product Price<br/>قیمت‌های عمده"]
        RetailPrice["Retail Price<br/>قیمت خرده‌فروشی"]
        PriceUpdateBatch["Price Update Batch<br/>باتچ قیمت‌گذاری"]
    end
    
    subgraph Outsourcing["📝 سیستم برونسپاری"]
        ProductCompletionTask["Product Completion Task<br/>تسک‌های تکمیل محصول"]
        ProductCompletionSubmission["Submission<br/>ارائه‌ها"]
        CommissionRule["Commission Rule<br/>قوانین کمیسیون"]
    end
    
    subgraph Commerce["🛒 سیستم تجارت"]
        Order["Order<br/>سفارشات"]
        Cart["Cart<br/>سبد خرید"]
        Transaction["Transaction<br/>تراکنش‌ها"]
    end
    
    subgraph Content["📄 محتوا و رسانه"]
        Gallery["Gallery<br/>گالری تصاویر"]
        Slider["Slider<br/>اسلایدرها"]
        Attribute["Attribute<br/>ویژگی‌ها"]
    end
    
    User --> Supplier
    Supplier --> Product
    Category --> Product
    Product --> ProductPrice
    Product --> RetailPrice
    Product --> ProductCompletionTask
    ProductCompletionTask --> ProductCompletionSubmission
    ProductCompletionTask --> CommissionRule
```

## گردش‌کار برونسپاری محتوا (Outsourcing Workflow)

```mermaid
flowchart TD
    Start([شروع]) --> Admin[مدیر تسک ایجاد می‌کند]
    Admin --> Assign{انتصاب}
    
    Assign -->|outsourcer| Outsourcer[پیمانکار]
    Assign -->|reviewer| Reviewer[بازبین]
    
    Outsourcer --> Workspace[ورک‌سپیس]
    Workspace --> Upload[آپلود تصاویر/محتوا]
    Upload --> Submit[ارائه کار]
    
    Submit --> ReviewProcess{بررسی}
    ReviewProcess -->|تایید| Approved[تایید شده]
    ReviewProcess -->|اصلاح| Revision[نیاز به اصلاح]
    ReviewProcess -->|رد| Rejected[رد شده]
    
    Revision --> Workspace
    Rejected --> End1([پایان])
    
    Approved --> Publish[انتشار محصول]
    Publish --> Commission[محاسبه کمیسیون]
    Commission --> Settlement[تسویه حساب]
    Settlement --> End2([پایان])
    
    style Admin fill:#f9f,stroke:#333
    style Outsourcer fill:#bbf,stroke:#333
    style Reviewer fill:#bfb,stroke:#333
    style Approved fill:#4f4,stroke:#333
    style Rejected fill:#f44,stroke:#333
```

## گردش‌کار قیمت‌گذاری سریع (Fast Pricing)

```mermaid
flowchart LR
    Supplier[تأمین‌کننده] --> Import[واردات فایل Excel]
    Import --> Parse[پارس کردن داده‌ها]
    Parse --> Validate{اعتبارسنجی}
    Validate -->|معتبر| Preview[پیش‌نمایش]
    Validate -->|نامعتبر| Errors[نمایش خطاها]
    Errors --> Import
    
    Preview --> Percentage[اعمال درصد]
    Percentage --> Apply[اعمال تغییرات]
    Apply --> Batch[ایجاد باتچ]
    Batch --> Update[بروزرسانی قیمت‌ها]
    Update --> Complete([اتمام])
    
    style Supplier fill:#bbf
    style Complete fill:#4f4
```

## روابط پایگاه داده (ER Diagram)

```mermaid
erDiagram
    USER ||--o{ SUPPLIER : "has one"
    USER ||--o{ RETAIL_PRICE : "sets"
    USER ||--o{ TRANSACTION : "makes"
    USER ||--o{ USER_SUBSCRIPTION : "subscribes"
    
    SUPPLIER ||--o{ PRODUCT : "owns"
    SUPPLIER ||--o{ PRODUCT_SUPPLIER : "supplies"
    SUPPLIER ||--o{ PRODUCT_COMPLETION_TASK : "creates"
    SUPPLIER ||--o{ COMMISSION_RULE : "defines"
    
    PRODUCT ||--o{ PRODUCT_PRICE : "has"
    PRODUCT ||--o{ GALLERY : "has"
    PRODUCT ||--o{ PRODUCT_COMPLETION_TASK : "needs"
    PRODUCT }o--|| CATEGORY : "belongs to"
    PRODUCT }o--o{ SUPPLIER : "supplied by"
    
    PRODUCT_COMPLETION_TASK ||--o{ PRODUCT_COMPLETION_SUBMISSION : "receives"
    PRODUCT_COMPLETION_TASK ||--o{ PRODUCT_COMPLETION_REVISION : "tracks"
    PRODUCT_COMPLETION_TASK }o--|| OUTSOURCER : "assigned to"
    
    PRODUCT_COMPLETION_SUBMISSION ||--o{ OUTSOURCING_SETTLEMENT : "settles"
    
    CATEGORY ||--o{ CATEGORY : "has children"
```

## مسیرهای API (API Endpoints)

```mermaid
graph LR
    subgraph Public["🌐 عمومی"]
        Products["GET /api/v1/products"]
        Categories["GET /api/v1/categories"]
        Search["GET /api/v1/search"]
        Login["POST /api/v1/auth/login"]
        Register["POST /api/v1/auth/register"]
    end
    
    subgraph Auth["🔒 احراز هویت شده"]
        Logout["POST /api/v1/auth/logout"]
        User["GET /api/v1/auth/user"]
        MyProducts["GET /api/v1/my-products"]
        RetailPrices["GET /api/v1/retail-prices"]
    end
    
    subgraph Roles["👥 بر اساس نقش"]
        SupplierAPI["Supplier APIs<br/>مدیریت محصولات"]
        RetailerAPI["Retailer APIs<br/>قیمت‌گذاری خرده"]
    end
    
    Login --> Auth
    Register --> Auth
    Auth --> SupplierAPI
    Auth --> RetailerAPI
```

## داشبورد بر اساس نقش (Role-based Dashboard)

```mermaid
flowchart TD
    Login[ورود کاربر] --> Check{بررسی نقش}
    
    Check -->|super_admin| AdminDashboard[داشبورد ادمین ارشد]
    Check -->|admin| OutsourcingDashboard[داشبورد برونسپاری]
    Check -->|outsourcer| TasksIndex[لیست تسک‌ها]
    Check -->|reviewer| ReviewTasks[تسک‌های بازبینی]
    Check -->|finance_manager| Commissions[مدیریت کمیسیون‌ها]
    Check -->|supplier/regular_supplier| SupplierDashboard[داشبورد تأمین‌کننده]
    Check -->|retailer_premium/retailer_basic| RetailerDashboard[داشبورد کاسب]
    Check -->|end_user| RoleSelection[انتخاب نقش]
    
    AdminDashboard --> FullAccess[دسترسی کامل]
    OutsourcingDashboard --> ManageTasks[مدیریت تسک‌ها]
    TasksIndex --> Workspace[ورک‌سپیس]
    ReviewTasks --> ReviewInterface[رابط بازبینی]
    SupplierDashboard --> Products[مدیریت محصولات]
    RetailerDashboard --> Browse[مرور محصولات]
```

## سیستم احراز هویت (Authentication Flow)

```mermaid
sequenceDiagram
    participant U as کاربر
    participant A as App
    participant D as Database
    
    U->>A: ورود موبایل/ایمیل
    A->>D: بررسی کاربر
    D-->>A: کاربر یافت شد
    A->>U: ارسال کد تایید
    U->>A: وارد کردن کد
    A->>D: تایید موبایل
    D-->>A: تایید شد
    A->>U: ریدایرکت به داشبورد
    
    Note over A,D: بررسی نقش و دسترسی
    A->>D: دریافت نقش‌ها
    D-->>A: لیست نقش‌ها
    A->>U: نمایش داشبورد مناسب
```

## سیستم کش و Performance

```mermaid
flowchart TB
    subgraph Client["🖥️ کلاینت"]
        Browser[مرورگر]
        Cache[Cache Storage]
    end
    
    subgraph CDN["🌐 CDN"]
        Static[فایل‌های استاتیک]
        Images[تصاویر]
    end
    
    subgraph Server["⚙️ سرور"]
        Redis[Redis Cache]
        DB[(MySQL)]
    end
    
    Browser -->|Request| CDN
    CDN -->|Cache Hit| Browser
    CDN -->|Cache Miss| Server
    Server -->|Query| Redis
    Redis -->|Hit| Server
    Redis -->|Miss| DB
    DB --> Redis
    Server --> CDN
```

---

**تاریخ ایجاد:** 1407/08/01  
**آخرین بروزرسانی:** 1407/08/01
