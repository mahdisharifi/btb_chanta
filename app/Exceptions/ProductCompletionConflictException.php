<?php

declare(strict_types=1);

namespace App\Exceptions;

use DomainException;

class ProductCompletionConflictException extends DomainException
{
    public function __construct(public readonly array $conflicts)
    {
        parent::__construct('محصول پس از تخصیص تسک تغییر کرده است و نیاز به رفع تداخل دارد.');
    }
}
