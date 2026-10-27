# راهنمای Factory و Seeder برای B2B Marketplace

## وضعیت Factory‌های موجود

### ✅ Factory‌های موجود (Laravel Default):
- `UserFactory` - موجود در `database/factories/UserFactory.php`

### ❓ Factory‌های مورد نیاز برای تست‌ها:

## 1. ProductFactory

```php
<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Category;
use App\Models\Supplier;
use App\Enums\ProductStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'category_id' => Category::factory(),
            'title' => fake()->words(3, true),
            'slug' => fake()->unique()->slug(),
            'sku' => 'PRD-' . strtoupper(fake()->bothify('??####')),
            'short_description' => fake()->sentence(),
            'full_description' => fake()->paragraphs(3, true),
            'specifications' => [
                'weight' => fake()->numberBetween(100, 5000) . ' g',
                'dimensions' => fake()->numberBetween(10, 100) . 'x' . fake()->numberBetween(10, 100) . 'x' . fake()->numberBetween(10, 100) . ' mm',
            ],
            'weight' => fake()->numberBetween(100, 5000),
            'is_active' => true,
            'is_featured' => false,
            'status' => ProductStatus::ACTIVE,
            'view_count' => fake()->numberBetween(0, 1000),
            'seo_title' => fake()->words(5, true),
            'seo_description' => fake()->sentence(),
            'seo_keywords' => implode(',', fake()->words(5)),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
            'status' => ProductStatus::INACTIVE,
        ]);
    }

    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_featured' => true,
        ]);
    }
}
```

---

## 2. CategoryFactory

```php
<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        $name = fake()->words(2, true);
        
        return [
            'parent_id' => null,
            'name' => $name,
            'slug' => \Str::slug($name),
            'description' => fake()->sentence(),
            'icon' => 'heroicon-o-folder',
            'image_path' => null,
            'sort_order' => fake()->numberBetween(0, 100),
            'is_active' => true,
            'seo_title' => fake()->words(3, true),
            'seo_description' => fake()->sentence(),
            'seo_keywords' => implode(',', fake()->words(3)),
        ];
    }

    public function child(?int $parentId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => $parentId ?? Category::factory()->create()->id,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
```

---

## 3. AttributeFactory

```php
<?php

namespace Database\Factories;

use App\Models\Attribute;
use App\Enums\AttributeType;
use Illuminate\Database\Eloquent\Factories\Factory;

class AttributeFactory extends Factory
{
    protected $model = Attribute::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'type' => AttributeType::TEXT,
            'unit' => fake()->optional()->randomElement(['گرم', 'سانتی‌متر', 'میلی‌لیتر', 'وات']),
            'is_filterable' => fake()->boolean(30),
            'is_multiple' => false,
            'is_visible' => true,
        ];
    }

    public function filterable(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_filterable' => true,
        ]);
    }

    public function multiple(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_multiple' => true,
        ]);
    }
}
```

---

## 4. AttributeValueFactory

```php
<?php

namespace Database\Factories;

use App\Models\AttributeValue;
use App\Models\Attribute;
use Illuminate\Database\Eloquent\Factories\Factory;

class AttributeValueFactory extends Factory
{
    protected $model = AttributeValue::class;

    public function definition(): array
    {
        return [
            'attribute_id' => Attribute::factory(),
            'value' => fake()->word(),
        ];
    }
}
```

---

## 5. CatalogTemplateFactory / CatalogFactory

```php
<?php

namespace Database\Factories;

use App\Models\CatalogTemplate;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

class CatalogTemplateFactory extends Factory
{
    protected $model = CatalogTemplate::class;

    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'name' => fake()->words(2, true),
            'slug' => fake()->unique()->slug(),
            'template_data' => [
                'pages' => fake()->numberBetween(1, 10),
                'layout' => 'grid',
                'show_prices' => true,
            ],
            'is_active' => true,
        ];
    }
}
```

---

## 6. FeatureFactory

```php
<?php

namespace Database\Factories;

use App\Models\Feature;
use Illuminate\Database\Eloquent\Factories\Factory;

class FeatureFactory extends Factory
{
    protected $model = Feature::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'slug' => fake()->unique()->slug(),
            'description' => fake()->sentence(),
            'price' => fake()->numberBetween(10000, 1000000),
            'duration_days' => fake()->numberBetween(30, 365),
            'is_active' => true,
        ];
    }
}
```

---

## 7. FlowchartFactory

```php
<?php

namespace Database\Factories;

use App\Models\Flowchart;
use Illuminate\Database\Eloquent\Factories\Factory;

class FlowchartFactory extends Factory
{
    protected $model = Flowchart::class;

    public function definition(): array
    {
        return [
            'title' => fake()->words(3, true),
            'slug' => fake()->unique()->slug(),
            'content' => [
                'nodes' => [],
                'edges' => [],
            ],
            'is_active' => true,
        ];
    }
}
```

---

## 8. SupplierFactory (Update to UserFactory)

```php
// در UserFactory اضافه کنید:

public function supplier(): static
{
    return $this->afterCreating(function (User $user) {
        Supplier::factory()->create(['user_id' => $user->id]);
    });
}
```

---

## Seeders

### DatabaseSeeder.php

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            ProvinceSeeder::class,
            CategorySeeder::class,
            AttributeSeeder::class,
            SubscriptionPlanSeeder::class,
            FeatureSeeder::class,
        ]);
    }
}
```

---

### RoleSeeder.php

```php
<?php

namespace Database\Seeders;

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // ایجاد نقش‌ها
        $roles = [
            'super_admin' => 'مدیر ارشد',
            'admin' => 'مدیر',
            'supplier' => 'تامین‌کننده',
            'regular_supplier' => 'تامین‌کننده عادی',
            'retailer_premium' => 'خرده‌فروش ویژه',
            'retailer_basic' => 'خرده‌فروش عادی',
            'end_user' => 'کاربر عادی',
        ];

        foreach ($roles as $name => $label) {
            Role::create([
                'name' => $name,
                'guard_name' => 'web',
            ]);
        }

        // ایجاد permission‌های پایه
        $permissions = [
            'view_dashboard',
            'manage_users',
            'manage_products',
            'manage_orders',
            'view_reports',
        ];

        foreach ($permissions as $permission) {
            Permission::create([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        // اختصاص permission‌ها به نقش‌ها
        $superAdmin = Role::findByName('super_admin');
        $superAdmin->givePermissionTo(Permission::all());
    }
}
```

---

## نحوه استفاده در تست‌ها

### مثال استفاده از Factory:

```php
// ایجاد یک محصول با تمام وابستگی‌ها
$product = Product::factory()->create();

// ایجاد یک محصول با دسته‌بندی خاص
$category = Category::factory()->create();
$product = Product::factory()->create(['category_id' => $category->id]);

// ایجاد یک کاربر با نقش supplier
$user = User::factory()->create();
$user->assignRole('supplier');
$supplier = Supplier::factory()->create(['user_id' => $user->id]);

// ایجاد یک محصول inactive
$product = Product::factory()->inactive()->create();

// ایجاد یک دسته‌بندی فرزند
$parent = Category::factory()->create();
$child = Category::factory()->child($parent->id)->create();
```

---

## اجرای Factory‌ها و Seeder‌ها

```bash
# اجرای همه seederها
php artisan db:seed

# اجرای یک seeder خاص
php artisan db:seed --class=RoleSeeder

# Fresh migrate + seed
php artisan migrate:fresh --seed

# اجرای factory در tinker
php artisan tinker
>>> User::factory()->count(10)->create()
>>> Product::factory()->count(50)->create()
```

---

**تاریخ آخرین بروزرسانی:** 2026-08-02  
**نسخه:** 1.0
