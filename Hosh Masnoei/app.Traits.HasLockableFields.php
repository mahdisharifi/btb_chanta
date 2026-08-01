<?php

declare(strict_types=1);

namespace App\Traits;

trait HasLockableFields
{
    /**
     * کلیدی که برای یافتن فیلدهای قفل‌شده در config استفاده می‌شود.
     * ابتدا type مدل (supplier/retailer) و سپس نقش کاربر را ملاک قرار می‌دهد.
     * در صورت نیاز در مدل override شود.
     */
    public function lockableRoleKey(): ?string
    {
        if (! empty($this->type)) {
            return $this->type; // 'supplier' یا 'retailer'
        }

        return $this->user?->getRoleNames()->first();
    }

    /**
     * آیا حساب یک‌بار تایید شده است؟
     *
     * نکته: ملاک، وجود verified_at است نه verification_status؛
     * چون verification_status هنگام ویرایش به under_review برمی‌گردد
     * و قفل را بی‌اثر می‌کند، ولی verified_at پایدار باقی می‌ماند.
     */
    public function isApprovedForLock(): bool
    {
        return $this->verified_at !== null;
    }

    /**
     * نام قدیمی (با غلط املایی) برای سازگاری با کدهای احتمالی پیشین.
     *
     * @deprecated از isApprovedForLock() استفاده کنید.
     */
    public function isAprovedForLock(): bool
    {
        return $this->isApprovedForLock();
    }

    /**
     * فهرست فیلدهایی که هم‌اکنون قفل هستند.
     *
     * @return array<int, string>
     */
    public function lockedFields(): array
    {
        if (! $this->isApprovedForLock()) {
            return [];
        }

        $role = $this->lockableRoleKey();

        if ($role === null) {
            return [];
        }

        return config("profile_fields.locked_after_approval.{$role}", []);
    }

    public function isFieldLocked(string $field): bool
    {
        return in_array($field, $this->lockedFields(), true);
    }
}
