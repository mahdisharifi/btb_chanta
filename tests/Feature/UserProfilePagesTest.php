<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserProfilePagesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * تست صفحه انتخاب نقش برای کاربر end_user
     */
    public function test_end_user_can_access_role_selection(): void
    {
        $user = User::factory()->create([
            'mobile_verified_at' => now()
        ]);
        $user->assignRole(UserRole::END_USER);

        $response = $this->actingAs($user)
            ->get(route('user.role-selection'));

        $response->assertStatus(200);
    }

    /**
     * تست صفحه تکمیل اطلاعات تامین‌کننده
     */
    public function test_user_can_access_supplier_complete_form(): void
    {
        $user = User::factory()->create([
            'mobile_verified_at' => now()
        ]);
        $user->assignRole(UserRole::END_USER);

        $response = $this->actingAs($user)
            ->get(route('user.register.supplier.complete'));

        $response->assertStatus(200);
    }

    /**
     * تست صفحه تکمیل اطلاعات خرده‌فروش
     */
    public function test_user_can_access_retailer_complete_form(): void
    {
        $user = User::factory()->create([
            'mobile_verified_at' => now()
        ]);
        $user->assignRole(UserRole::END_USER);

        $response = $this->actingAs($user)
            ->get(route('user.register.retailer.complete'));

        $response->assertStatus(200);
    }

    /**
     * تست صفحه ویرایش پروفایل تامین‌کننده
     */
    public function test_supplier_can_access_profile_edit(): void
    {
        $user = User::factory()->create([
            'mobile_verified_at' => now(),
            'approval_status' => 'approved'
        ]);
        $user->assignRole(UserRole::SUPPLIER);

        $response = $this->actingAs($user)
            ->get(route('supplier.profile.edit'));

        $response->assertStatus(200);
    }

    /**
     * تست صفحه ویرایش پروفایل خرده‌فروش
     */
    public function test_retailer_can_access_profile_edit(): void
    {
        $user = User::factory()->create([
            'mobile_verified_at' => now(),
            'approval_status' => 'approved',
            'subscription_expires_at' => now()->addMonth()
        ]);
        $user->assignRole(UserRole::RETAILER_PREMIUM);

        $response = $this->actingAs($user)
            ->get(route('retailer.profile.edit'));

        $response->assertStatus(200);
    }

    /**
     * تست صفحه حساب در انتظار تایید
     */
    public function test_pending_user_can_access_pending_page(): void
    {
        $user = User::factory()->create([
            'mobile_verified_at' => now(),
            'approval_status' => 'pending'
        ]);
        $user->assignRole(UserRole::SUPPLIER);

        $response = $this->actingAs($user)
            ->get(route('account.pending'));

        $response->assertStatus(200);
    }

    /**
     * تست صفحه حساب در حال بررسی
     */
    public function test_under_review_user_can_access_under_review_page(): void
    {
        $user = User::factory()->create([
            'mobile_verified_at' => now(),
            'approval_status' => 'under_review'
        ]);
        $user->assignRole(UserRole::SUPPLIER);

        $response = $this->actingAs($user)
            ->get(route('account.under-review'));

        $response->assertStatus(200);
    }

    /**
     * تست صفحه حساب رد شده
     */
    public function test_rejected_user_can_access_rejected_page(): void
    {
        $user = User::factory()->create([
            'mobile_verified_at' => now(),
            'approval_status' => 'rejected'
        ]);
        $user->assignRole(UserRole::SUPPLIER);

        $response = $this->actingAs($user)
            ->get(route('account.rejected'));

        $response->assertStatus(200);
    }

    /**
     * تست صفحه ویرایش اکانت
     */
    public function test_authenticated_user_can_access_account_edit(): void
    {
        $user = User::factory()->create([
            'mobile_verified_at' => now()
        ]);

        $response = $this->actingAs($user)
            ->get(route('account.edit'));

        $response->assertStatus(200);
    }

    /**
     * تست redirect صفحه پروفایل به داشبورد
     */
    public function test_profile_redirects_to_dashboard(): void
    {
        $user = User::factory()->create([
            'mobile_verified_at' => now(),
            'approval_status' => 'approved'
        ]);
        $user->assignRole(UserRole::SUPPLIER);

        $response = $this->actingAs($user)
            ->get(route('profile'));

        $response->assertRedirect(route('dashboard'));
    }

    /**
     * تست صفحه تایید موبایل
     */
    public function test_user_with_unverified_mobile_can_access_verify_mobile_page(): void
    {
        $user = User::factory()->create([
            'mobile_verified_at' => null
        ]);

        $response = $this->actingAs($user)
            ->get(route('verify.mobile'));

        $response->assertStatus(200);
    }

    /**
     * تست redirect کاربر با موبایل تایید شده از صفحه تایید موبایل
     */
    public function test_user_with_verified_mobile_redirects_from_verify_mobile_page(): void
    {
        $user = User::factory()->create([
            'mobile_verified_at' => now(),
            'approval_status' => 'approved'
        ]);
        $user->assignRole(UserRole::SUPPLIER);

        $response = $this->actingAs($user)
            ->get(route('verify.mobile'));

        $response->assertRedirect();
    }
}
