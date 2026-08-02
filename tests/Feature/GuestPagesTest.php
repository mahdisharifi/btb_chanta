<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestPagesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * تست صفحه اصلی سایت
     */
    public function test_home_page_is_accessible(): void
    {
        $response = $this->get(route('home'));
        $response->assertStatus(200);
    }

    /**
     * تست صفحه لیست محصولات
     */
    public function test_products_index_page_is_accessible(): void
    {
        $response = $this->get(route('products.index'));
        $response->assertStatus(200);
    }

    /**
     * تست صفحه جزئیات محصول
     */
    public function test_product_show_page_is_accessible(): void
    {
        $product = Product::factory()->create([
            'slug' => 'test-product',
            'status' => 'active'
        ]);

        $response = $this->get(route('products.show', $product->slug));
        $response->assertStatus(200);
    }

    /**
     * تست صفحه لیست دسته‌بندی‌ها
     */
    public function test_categories_index_page_is_accessible(): void
    {
        $response = $this->get(route('categories.index'));
        $response->assertStatus(200);
    }

    /**
     * تست صفحه جزئیات دسته‌بندی
     */
    public function test_category_show_page_is_accessible(): void
    {
        $category = Category::factory()->create([
            'slug' => 'test-category'
        ]);

        $response = $this->get(route('categories.show', $category->slug));
        $response->assertStatus(200);
    }

    /**
     * تست صفحه جستجو
     */
    public function test_search_page_is_accessible(): void
    {
        $response = $this->get(route('search'));
        $response->assertStatus(200);
    }

    /**
     * تست صفحه درباره ما
     */
    public function test_about_page_is_accessible(): void
    {
        $response = $this->get(route('about'));
        $response->assertStatus(200);
    }

    /**
     * تست صفحه تماس با ما
     */
    public function test_contact_page_is_accessible(): void
    {
        $response = $this->get(route('contact'));
        $response->assertStatus(200);
    }

    /**
     * تست صفحه قوانین و مقررات
     */
    public function test_terms_page_is_accessible(): void
    {
        $response = $this->get(route('terms'));
        $response->assertStatus(200);
    }

    /**
     * تست صفحه حریم خصوصی
     */
    public function test_privacy_page_is_accessible(): void
    {
        $response = $this->get(route('privacy'));
        $response->assertStatus(200);
    }

    /**
     * تست Sitemap اصلی
     */
    public function test_sitemap_index_is_accessible(): void
    {
        $response = $this->get(route('sitemap.index'));
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/xml; charset=UTF-8');
    }

    /**
     * تست Sitemap محصولات
     */
    public function test_sitemap_products_is_accessible(): void
    {
        $response = $this->get(route('sitemap.products'));
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/xml; charset=UTF-8');
    }

    /**
     * تست Sitemap دسته‌بندی‌ها
     */
    public function test_sitemap_categories_is_accessible(): void
    {
        $response = $this->get(route('sitemap.categories'));
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/xml; charset=UTF-8');
    }

    /**
     * تست Sitemap صفحات استاتیک
     */
    public function test_sitemap_static_is_accessible(): void
    {
        $response = $this->get(route('sitemap.static'));
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/xml; charset=UTF-8');
    }

    /**
     * تست redirect به login برای dashboard بدون احراز هویت
     */
    public function test_dashboard_redirects_to_login_for_guest(): void
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }
}
