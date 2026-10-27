<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductCompletionTaskStatus: string
{
    case DRAFT = 'draft';
    case AVAILABLE = 'available';
    case ASSIGNED = 'assigned';
    case ACCEPTED = 'accepted';
    case IN_PROGRESS = 'in_progress';
    case SUBMITTED = 'submitted';
    case CHANGES_REQUESTED = 'changes_requested';
    case RESUBMITTED = 'resubmitted';
    case APPROVED = 'approved';
    case CONFLICTED = 'conflicted';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case EXPIRED = 'expired';
    case DISPUTED = 'disputed';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'پیش‌نویس',
            self::AVAILABLE => 'آماده واگذاری',
            self::ASSIGNED => 'تخصیص داده‌شده',
            self::ACCEPTED => 'پذیرفته‌شده',
            self::IN_PROGRESS => 'در حال انجام',
            self::SUBMITTED => 'ارسال‌شده',
            self::CHANGES_REQUESTED => 'نیازمند اصلاح',
            self::RESUBMITTED => 'دوباره ارسال‌شده',
            self::APPROVED => 'تأییدشده',
            self::CONFLICTED => 'دارای تداخل',
            self::COMPLETED => 'تکمیل‌شده',
            self::CANCELLED => 'لغوشده',
            self::EXPIRED => 'منقضی‌شده',
            self::DISPUTED => 'مورد اختلاف',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::AVAILABLE => 'sky',
            self::ASSIGNED => 'blue',
            self::ACCEPTED => 'indigo',
            self::IN_PROGRESS => 'amber',
            self::SUBMITTED, self::RESUBMITTED => 'purple',
            self::CHANGES_REQUESTED => 'orange',
            self::APPROVED, self::COMPLETED => 'emerald',
            self::CONFLICTED, self::DISPUTED => 'red',
            self::CANCELLED, self::EXPIRED => 'slate',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::COMPLETED,
            self::CANCELLED,
            self::EXPIRED,
        ], true);
    }

    public function canEditDraft(): bool
    {
        return $this === self::IN_PROGRESS;
    }

    public function canBeReviewed(): bool
    {
        return in_array($this, [self::SUBMITTED, self::RESUBMITTED], true);
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, match ($this) {
            self::DRAFT => [self::AVAILABLE, self::ASSIGNED, self::CANCELLED],
            self::AVAILABLE => [self::ASSIGNED, self::ACCEPTED, self::EXPIRED, self::CANCELLED],
            self::ASSIGNED => [self::AVAILABLE, self::ACCEPTED, self::EXPIRED, self::CANCELLED],
            self::ACCEPTED => [self::IN_PROGRESS, self::CANCELLED, self::EXPIRED],
            self::IN_PROGRESS => [self::SUBMITTED, self::RESUBMITTED, self::CANCELLED, self::EXPIRED],
            self::SUBMITTED, self::RESUBMITTED => [
                self::IN_PROGRESS,
                self::CHANGES_REQUESTED,
                self::APPROVED,
                self::CONFLICTED,
                self::CANCELLED,
                self::DISPUTED,
            ],
            self::CHANGES_REQUESTED => [self::IN_PROGRESS, self::CANCELLED, self::EXPIRED, self::DISPUTED],
            self::APPROVED => [self::COMPLETED, self::CONFLICTED, self::DISPUTED],
            self::CONFLICTED => [self::APPROVED, self::CHANGES_REQUESTED, self::CANCELLED],
            self::COMPLETED => [self::DISPUTED],
            self::EXPIRED => [self::ASSIGNED, self::CANCELLED],
            self::DISPUTED => [self::COMPLETED, self::CANCELLED],
            self::CANCELLED => [],
        }, true);
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
