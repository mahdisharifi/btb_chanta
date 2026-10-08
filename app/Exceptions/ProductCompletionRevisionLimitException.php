<?php

declare(strict_types=1);

namespace App\Exceptions;

use DomainException;

class ProductCompletionRevisionLimitException extends DomainException
{
    public function __construct()
    {
        parent::__construct('سقف مجاز درخواست اصلاح برای این تسک تکمیل شده است.');
    }
}
