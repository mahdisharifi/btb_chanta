# نقشه دیتابیس B2B Marketplace

## 1. جداول اصلی (Core Tables)

### users
**فیلدها:**
- id, uuid, name, email, mobile, password
- mobile_verified_at, email_verified_at, remember_token
- approval_status (pending/approved/rejected/under_review)
- subscription_expires_at
- created_at, updated_at, deleted_at

**رابطه‌ها:**
- hasOne: Supplier, SupplierProfile
- hasMany: RetailPrice, UserSubscription, Transaction
- belongsToMany: Role (via spatie/laravel-permission)

---

### suppliers
**فیلدها:**
- id, user_id
- type (supplier/retailer)
- name, slug, description
- phone, mobile, email, website
- company_name, company_name_en, brand_name, logo_path
- address, city, province, province_id, city_id, postal_code
- tax_id, national_id
- verification_status, verified_at, verified_by, rejection_reason
- verification_documents, social_networks (JSON)
- is_verified, is_active
- created_at, updated_at, deleted_at

**رابطه‌ها:**
- belongsTo: User, Province, City
- hasMany: Product (ownedProducts), ProductSupplier
- belongsToMany: Product, SocialNetwork
- hasOne: SupplierProfile

---

### products
**فیلدها:**
- id, supplier_id, category_id
- title, slug, sku
- short_description, full_description, specifications (JSON)
- weight, dimensions (JSON)
- is_active, is_featured, status (Enum: active/inactive/draft)
- view_count
- seo_title, seo_description, seo_keywords, seo_canonical_url
- legacy_id
- created_at, updated_at, deleted_at

**رابطه‌ها:**
- belongsTo: Supplier, Category
- hasMany: ProductPrice, RetailPrice, ProductGallery, ProductSupplier, ProductSlugRedirect
- belongsToMany: Supplier, Attribute, AttributeValue

---

### categories
**فیلدها:**
- id, parent_id
- name, slug, description
- icon, image_path
- sort_order, is_active
- seo_title, seo_description, seo_keywords
- created_at, updated_at, deleted_at

**رابطه‌ها:**
- belongsTo: Category (parent)
- hasMany: Category (children), Product
- belongsToMany: Attribute

---

## 2. جداول قیمت‌گذاری (Pricing Tables)

### product_supplier (Pivot Table)
**فیلدها:**
- id, product_id, supplier_id
- sku, is_primary, is_active, status
- sort_order
- cost_price, commission_rate
- stock_quantity, min_order_quantity, max_order_quantity
- lead_time_days, warranty_info, notes
- activated_at, deactivated_at
- created_at, updated_at, deleted_at

**رابطه‌ها:**
- belongsTo: Product, Supplier
- hasMany: ProductPrice

---

### product_prices
**فیلدها:**
- id, product_id, product_supplier_id
- price_type (Enum: cash/check_1_month/check_2_month/check_3_month/warranty)
- price (integer - ریال)
- min_quantity, max_quantity
- warranty_months, check_months
- description, is_active
- valid_from, valid_until
- created_at, updated_at

**رابطه‌ها:**
- belongsTo: Product, ProductSupplier

---

### retail_prices
**فیلدها:**
- id, product_id, user_id
- retail_price (integer)
- is_published
- created_at, updated_at

**رابطه‌ها:**
- belongsTo: Product, User

---

## 3. جداول ویژگی‌ها (Attributes System - EAV)

### attributes
**فیلدها:**
- id, name, type (text/select/multiselect/number/boolean)
- unit, is_filterable, is_multiple, is_visible
- created_at, updated_at

**رابطه‌ها:**
- hasMany: AttributeValue
- belongsToMany: Category, Product

---

### attribute_values
**فیلدها:**
- id, attribute_id, value
- created_at, updated_at

**رابطه‌ها:**
- belongsTo: Attribute
- belongsToMany: Product

---

### attribute_product (Pivot)
**فیلدها:**
- id, product_id, attribute_value_id, value
- created_at, updated_at

---

## 4. جداول اشتراک و پرداخت

### subscription_plans
**فیلدها:**
- id, name, slug, description
- price, duration_days
- features (JSON)
- is_active, sort_order
- created_at, updated_at

---

### user_subscriptions
**فیلدها:**
- id, user_id, subscription_plan_id
- status (active/expired/cancelled)
- starts_at, ends_at
- payment_id
- created_at, updated_at

**رابطه‌ها:**
- belongsTo: User, SubscriptionPlan

---

### transactions
**فیلدها:**
- id, user_id, amount
- type (subscription/feature)
- status (pending/success/failed)
- payment_method, payment_reference
- description, metadata (JSON)
- created_at, updated_at

**رابطه‌ها:**
- belongsTo: User

---

### features
**فیلدها:**
- id, name, slug, description
- price, duration_days
- is_active
- created_at, updated_at

---

### user_features
**فیلدها:**
- id, user_id, feature_id
- status, activated_at, expires_at
- payment_id
- created_at, updated_at

**رابطه‌ها:**
- belongsTo: User, Feature

---

## 5. جداول جانبی

### product_galleries
**فیلدها:**
- id, product_id
- file_path, file_type (image/video)
- alt_text, title
- is_primary, sort_order
- created_at, updated_at

**رابطه‌ها:**
- belongsTo: Product

---

### product_slug_redirects
**فیلدها:**
- id, product_id, old_slug
- created_at, updated_at

**رابطه‌ها:**
- belongsTo: Product

---

### provinces
**فیلدها:**
- id, name, slug, is_active
- created_at, updated_at

**رابطه‌ها:**
- hasMany: City, Supplier

---

### cities
**فیلدها:**
- id, province_id, name, slug, is_active
- created_at, updated_at

**رابطه‌ها:**
- belongsTo: Province
- hasMany: Supplier

---

### sliders
**فیلدها:**
- id, title, subtitle, image_path, link
- sort_order, is_active, clicks
- created_at, updated_at

---

### seo_pages
**فیلدها:**
- id, page_url, title, description, keywords
- created_at, updated_at

---

### flowcharts
**فیلدها:**
- id, title, slug, content (JSON)
- is_active
- created_at, updated_at

---

### catalog_templates
**فیلدها:**
- id, supplier_id, name, slug
- template_data (JSON)
- is_active
- created_at, updated_at

**رابطه‌ها:**
- belongsTo: Supplier

---

### otp_verifications
**فیلدها:**
- id, mobile, code, ip_address
- expires_at, verified_at
- created_at, updated_at

---

### price_update_batches
**فیلدها:**
- id, supplier_id, filename, status
- total_items, processed_items
- created_at, updated_at

**رابطه‌ها:**
- belongsTo: Supplier
- hasMany: PriceUpdateItem

---

### price_update_items
**فیلدها:**
- id, batch_id, product_id
- old_price, new_price
- status
- created_at, updated_at

**رابطه‌ها:**
- belongsTo: PriceUpdateBatch, Product

---

### price_histories
**فیلدها:**
- id, product_id, product_price_id
- old_price, new_price
- created_at, updated_at

**رابطه‌ها:**
- belongsTo: Product, ProductPrice

---

## 6. جداول سیستم نقش‌ها (Spatie Permission)

### roles
**فیلدها:**
- id, name, guard_name
- created_at, updated_at

---

### permissions
**فیلدها:**
- id, name, guard_name
- created_at, updated_at

---

### model_has_roles (Pivot)
**فیلدها:**
- role_id, model_type, model_id

---

### role_has_permissions (Pivot)
**فیلدها:**
- permission_id, role_id

---

### model_has_permissions (Pivot)
**فیلدها:**
- permission_id, model_type, model_id

---

## 7. جداول Medialibrary (Spatie)

### media
**فیلدها:**
- id, model_type, model_id, collection_name, name
- file_name, mime_type, disk, size, manipulations, custom_properties
- responsive_images, order_column
- created_at, updated_at

---

## نقشه ERD (Entity Relationship Diagram)

```
Users
  ├─→ Supplier (1:1)
  ├─→ SupplierProfile (1:1)
  ├─→ RetailPrices (1:N)
  ├─→ UserSubscriptions (1:N)
  ├─→ Transactions (1:N)
  └─→ Roles (N:M via spatie)

Suppliers
  ├─→ User (N:1)
  ├─→ Products (1:N - ownedProducts)
  ├─→ ProductSupplier (1:N)
  ├─→ Province (N:1)
  ├─→ City (N:1)
  └─→ SocialNetworks (N:M)

Products
  ├─→ Supplier (N:1)
  ├─→ Category (N:1)
  ├─→ ProductSupplier (1:N)
  ├─→ ProductPrices (1:N)
  ├─→ RetailPrices (1:N)
  ├─→ ProductGallery (1:N)
  ├─→ Attributes (N:M)
  └─→ AttributeValues (N:M)

Categories
  ├─→ Parent (N:1 - self)
  ├─→ Children (1:N - self)
  ├─→ Products (1:N)
  └─→ Attributes (N:M)

ProductSupplier
  ├─→ Product (N:1)
  ├─→ Supplier (N:1)
  └─→ ProductPrices (1:N)

Attributes
  ├─→ AttributeValues (1:N)
  ├─→ Categories (N:M)
  └─→ Products (N:M)
```

## نکات مهم:

1. **قیمت‌گذاری سه سطحی:**
   - `product_prices`: قیمت عمده (هر تامین‌کننده برای هر محصول)
   - `retail_prices`: قیمت خرده‌فروشی (هر خرده‌فروش برای هر محصول)
   - `product_supplier`: اطلاعات موجودی و کمیسیون

2. **سیستم EAV برای ویژگی‌ها:**
   - `attributes`: تعریف ویژگی
   - `attribute_values`: مقادیر ممکن
   - `attribute_product`: ارتباط با محصول

3. **Soft Deletes:**
   - جداول: users, suppliers, products, categories, product_supplier

4. **Polymorphic Relations:**
   - media (spatie/medialibrary)
   - model_has_roles, model_has_permissions

5. **عدم استفاده از foreign keys:**
   - تمام روابط از طریق application level مدیریت می‌شوند
   - این کار برای performance و flexibility انجام شده

---

**تاریخ آخرین بروزرسانی:** 2026-08-02  
**نسخه:** 1.0  
**منبع:** b2b_marketplace_server (9).sql


---

## 8. Indexes و Performance Optimization

### Indexes روی جداول اصلی:

#### users
- `PRIMARY KEY`: id
- `UNIQUE`: email, mobile, uuid
- `INDEX`: approval_status, subscription_expires_at

#### products
- `PRIMARY KEY`: id
- `UNIQUE`: slug, sku
- `INDEX`: supplier_id, category_id, status, is_active
- `FULLTEXT`: title, short_description (برای جستجو)

#### product_supplier
- `PRIMARY KEY`: id
- `UNIQUE`: product_id, supplier_id (composite)
- `INDEX`: is_primary, is_active, status

#### product_prices
- `PRIMARY KEY`: id
- `INDEX`: product_id, product_supplier_id, price_type, is_active
- `COMPOSITE`: (product_id, is_active, valid_from, valid_until)

#### categories
- `PRIMARY KEY`: id
- `UNIQUE`: slug
- `INDEX`: parent_id, is_active, sort_order

#### attribute_product
- `PRIMARY KEY`: id
- `INDEX`: product_id, attribute_value_id
- `COMPOSITE`: (product_id, attribute_value_id)

---

## 9. Default Values و Constraints

### فیلدهای با مقدار پیش‌فرض:

```php
// Users
'approval_status' => 'pending'

// Products
'is_active' => true
'is_featured' => false
'status' => 'draft'
'view_count' => 0

// Categories
'is_active' => true
'sort_order' => 0

// Attributes
'is_filterable' => false
'is_multiple' => false
'is_visible' => true

// ProductSupplier
'is_primary' => false
'is_active' => true
'status' => 'pending'
'sort_order' => auto-increment

// ProductPrices
'is_active' => true

// Sliders
'is_active' => true
'clicks' => 0
'sort_order' => 0
```

---

## 10. Enum Values

### ProductStatus
- `active`: فعال
- `inactive`: غیرفعال
- `draft`: پیش‌نویس

### PriceType
- `cash`: نقدی
- `check_1_month`: چک ۱ ماهه
- `check_2_month`: چک ۲ ماهه
- `check_3_month`: چک ۳ ماهه
- `warranty`: گارانتی

### Supplier Verification Status
- `pending`: در انتظار
- `under_review`: در حال بررسی
- `approved`: تأیید شده
- `rejected`: رد شده

### ProductSupplier Status
- `pending`: در انتظار بررسی
- `active`: فعال
- `inactive`: غیرفعال
- `rejected`: رد شده

### User Approval Status
- `pending`: در انتظار
- `under_review`: در حال بررسی
- `approved`: تأیید شده
- `rejected`: رد شده

### Subscription Status
- `active`: فعال
- `expired`: منقضی
- `cancelled`: لغو شده

### Transaction Status
- `pending`: در انتظار
- `success`: موفق
- `failed`: ناموفق

---

## 11. Soft Deletes

### جداول با Soft Delete:
- `users`
- `suppliers`
- `products`
- `categories`
- `product_supplier`

**نکته:** تمام جداول بالا دارای فیلد `deleted_at` هستند.

---

## 12. JSON Fields

### فیلدهای JSON:

#### users
- ندارد

#### suppliers
- `social_networks`: آرایه‌ای از شبکه‌های اجتماعی
- `verification_documents`: آرایه‌ای از مدارک

#### products
- `specifications`: مشخصات فنی محصول
- `dimensions`: ابعاد (طول × عرض × ارتفاع)

#### subscription_plans
- `features`: لیست ویژگی‌های پلن

#### transactions
- `metadata`: اطلاعات اضافی تراکنش

#### flowcharts
- `content`: محتوای فلوچارت

#### catalog_templates
- `template_data`: داده‌های قالب کاتالوگ

---

## 13. Cascade Delete Rules

### قوانین حذف در Application Level:

```php
// هنگام حذف User:
- Soft delete supplier
- Soft delete all related products
- Delete retail prices
- Delete subscriptions
- Delete transactions

// هنگام حذف Product:
- Delete all product_prices
- Delete all retail_prices
- Delete all product_galleries
- Delete all product_supplier records
- Delete all attribute_product records

// هنگام حذف Category:
- Soft delete all products
- Delete all category relations

// هنگام حذف Supplier:
- Soft delete all products
- Delete all product_supplier records
```

---

## 14. Caching Strategy

### جداول کش‌شده:

1. **Categories Tree**:
   - Cache Key: `category_tree_with_counts`
   - Duration: 24 hours
   - Clear on: category create/update/delete

2. **Active Products**:
   - Cache Key: `product:{id}`
   - Duration: 1 hour
   - Clear on: product update/delete

3. **Category Attributes**:
   - Cache Key: `category:{id}:attributes`
   - Duration: 6 hours
   - Clear on: attribute create/update/delete

4. **Supplier Profile**:
   - Cache Key: `supplier:{id}:profile`
   - Duration: 1 hour
   - Clear on: supplier update

---

## 15. Data Integrity Rules

### Business Rules:

1. **یک تامین‌کننده اصلی برای هر محصول**:
   - فقط یک `product_supplier` می‌تواند `is_primary = true` داشته باشد
   - اگر primary حذف شود، اولین active جدید primary می‌شود

2. **قیمت‌گذاری**:
   - هر محصول باید حداقل یک قیمت فعال داشته باشد
   - `valid_from` باید قبل از `valid_until` باشد
   - قیمت‌ها به ریال ذخیره می‌شوند

3. **اشتراک**:
   - کاربر premium باید اشتراک فعال داشته باشد
   - انقضای اشتراک با middleware چک می‌شود

4. **تایید حساب**:
   - تامین‌کننده باید `verification_status = approved` داشته باشد
   - تایید توسط super_admin انجام می‌شود

---

## 16. Migrations Best Practices

### هنگام ایجاد Migration جدید:

1. **نام‌گذاری**:
   ```bash
   php artisan make:migration add_field_to_table --table=table_name
   ```

2. **ترتیب اجرا**:
   - توجه به وابستگی جداول
   - استفاده از `after()` برای فیلدها

3. **Rollback**:
   - همیشه متد `down()` پیاده‌سازی شود
   - حذف index‌ها قبل از حذف فیلد

4. **بعد از هر تغییر**:
   - این فایل (`DATABASE_SCHEMA.md`) را بروزرسانی کنید
   - تست‌های مربوطه را اجرا کنید

---

## 17. Query Optimization Tips

### بهترین روش‌ها:

1. **Eager Loading**:
   ```php
   Product::with(['supplier', 'category', 'prices', 'galleries'])
   ```

2. **Select Specific Columns**:
   ```php
   Product::select(['id', 'title', 'slug', 'price'])
   ```

3. **Index Usage**:
   ```php
   Product::where('status', 'active')
          ->where('is_active', true)
          ->where('category_id', $categoryId)
   ```

4. **Avoid N+1**:
   ```php
   // Instead of:
   $products->each(fn($p) => $p->supplier->name);
   
   // Use:
   Product::with('supplier:id,name')->get();
   ```

---

**تاریخ آخرین بروزرسانی:** 2026-08-02  
**نسخه:** 1.1  
**منبع:** b2b_marketplace_server (9).sql
