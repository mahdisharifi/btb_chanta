<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentPagesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * تست صفحه verify پرداخت
     */
    public function test_payment_verify_is_accessible(): void
    {
        $response = $this->get(route('payment.verify'));
        
        // این endpoint معمولاً پارامترهای خاص نیاز دارد
        // در اینجا فقط دسترسی به route را چک می‌کنیم
        $this->assertTrue(
            $response->status() === 200 || 
            $response->status() === 302 ||
            $response->status() === 400
        );
    }

    /**
     * تست صفحه callback پرداخت
     */
    public function test_payment_callback_is_accessible(): void
    {
        $response = $this->get(route('payment.callback'));
        
        // این endpoint معمولاً پارامترهای خاص نیاز دارد
        // در اینجا فقط دسترسی به route را چک می‌کنیم
        $this->assertTrue(
            $response->status() === 200 || 
            $response->status() === 302 ||
            $response->status() === 400
        );
    }

    /**
     * تست POST به payment verify
     */
    public function test_payment_verify_accepts_post_request(): void
    {
        $response = $this->post(route('payment.verify'));
        
        // چک می‌کنیم که endpoint به POST request پاسخ می‌دهد
        $this->assertTrue(
            $response->status() === 200 || 
            $response->status() === 302 ||
            $response->status() === 400 ||
            $response->status() === 422
        );
    }
}
