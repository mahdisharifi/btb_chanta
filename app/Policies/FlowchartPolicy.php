<?php

namespace App\Policies;

use App\Models\Flowchart;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class FlowchartPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // همه کاربران لاگین کرده می‌توانند لیست را ببینند (البته دسترسی روت‌ها هم توسط middleware کنترل می‌شود)
        return $user->exists();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Flowchart $flowchart): bool
    {
        // ادمین می‌تواند همه فلوچارت‌ها را ببیند
        if ($user->hasRole('admin')) {
            return true;
        }

        // کاربران عادی فقط می‌توانند فلوچارت‌های خودشان را ببینند
        return $user->id === $flowchart->user_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // تمام کاربران تایید شده می‌توانند فلوچارت جدید بسازند
        return $user->exists();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Flowchart $flowchart): bool
    {
        // ادمین حق ویرایش همه فلوچارت‌ها را دارد
        if ($user->hasRole('admin')) {
            return true;
        }

        // کاربران عادی فقط می‌توانند فلوچارت‌های خود را ویرایش کنند
        return $user->id === $flowchart->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Flowchart $flowchart): bool
    {
        // ادمین حق حذف همه فلوچارت‌ها را دارد
        if ($user->hasRole('admin')) {
            return true;
        }

        // کاربران عادی فقط می‌توانند فلوچارت‌های خود را حذف کنند
        return $user->id === $flowchart->user_id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Flowchart $flowchart): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Flowchart $flowchart): bool
    {
        return false;
    }
}
