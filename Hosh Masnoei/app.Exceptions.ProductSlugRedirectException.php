<?php
// app/Exceptions/ProductSlugRedirectException.php

namespace App\Exceptions;

use Exception;

class ProductSlugRedirectException extends Exception
{
    public function __construct(public readonly string $newSlug)
    {
        parent::__construct();
    }
}
