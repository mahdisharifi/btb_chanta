<?php

namespace App\Enums;

enum ProductStatus: string
{
    case DRAFT = 'draft';
    case PENDING_REVIEW = 'pending_review';
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case OUT_OF_STOCK = 'out_of_stock';

    /**
     * Get human-readable label (Persian).
     */
    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'پیش‌نویس',
            self::PENDING_REVIEW => 'در انتظار بررسی',
            self::ACTIVE => 'فعال',
            self::INACTIVE => 'غیرفعال',
            self::OUT_OF_STOCK => 'ناموجود',
        };
    }

    /**
     * Get badge color for UI.
     */
    public function color(): string
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::PENDING_REVIEW => 'amber',
            self::ACTIVE => 'emerald',
            self::INACTIVE => 'red',
            self::OUT_OF_STOCK => 'orange',
        };
    }

    /**
     * Get icon for UI.
     */
    public function icon(): string
    {
        return match ($this) {
            self::DRAFT => 'pencil-square',
            self::PENDING_REVIEW => 'clock',
            self::ACTIVE => 'check-circle',
            self::INACTIVE => 'x-circle',
            self::OUT_OF_STOCK => 'exclamation-triangle',
        };
    }

    /**
     * Whether the product is publicly visible.
     */
    public function isVisible(): bool
    {
        return $this === self::ACTIVE;
    }

    /**
     * Whether the product can be edited.
     */
    public function isEditable(): bool
    {
        return in_array($this, [
            self::DRAFT,
            self::PENDING_REVIEW,
            self::ACTIVE,
            self::INACTIVE,
        ]);
    }

    /**
     * Get all values as array.
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get all as key => label array (for dropdowns).
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }
        return $options;
    }
}
