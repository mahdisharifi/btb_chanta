<?php

namespace App\Exceptions;

class CategorySlugRedirectException extends \RuntimeException
{
    public function __construct(public readonly string $newSlug)
    {
        parent::__construct('Category slug has moved.');
    }
}
