<?php

declare(strict_types=1);

namespace App\Exceptions;

use DomainException;

class ProductCompletionDraftVersionConflictException extends DomainException
{
    public function __construct(public readonly int $expected, public readonly int $actual)
    {
        parent::__construct('پیش‌نویس توسط فرآیند دیگری تغییر کرده است. صفحه را بروزرسانی کنید.');
    }
}
