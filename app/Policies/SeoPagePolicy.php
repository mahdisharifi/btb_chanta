<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\SeoPage;
use App\Models\User;

use Illuminate\Auth\Access\Response;
use Illuminate\Auth\Access\HandlesAuthorization;

class SeoPagePolicy
{
    use HandlesAuthorization;

    /**
     * مشاهده لیست صفحات SEO
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('super_admin');
    }
    /**
     * مشاهده جزئیات یک صفحه SEO
     */
    public function view(User $user, SeoPage $seoPage): bool
    {
        return $user->hasRole('super_admin');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::SUPER_ADMIN->value);
    }

    /**
     * ویرایش تنظیمات SEO
     */
    public function update(User $user): bool
    {
        return $user->hasRole('super_admin');
    }

    /**
     * حذف صفحه SEO
     */
    public function delete(User $user, SeoPage $seoPage): bool
    {
        return $user->hasRole('super_admin');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, SeoPage $seoPage): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, SeoPage $seoPage): bool
    {
        return false;
    }
    /**
     * مدیریت Sitemap
     */
    public function manageSitemap(User $user): bool
    {
        return $user->hasRole('super_admin');
    }
}
