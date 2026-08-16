<?php

namespace App\Exceptions;

use RuntimeException;

class ManifestValidationException extends RuntimeException
{
    /** @param list<string> $errors */
    public function __construct(public readonly array $errors)
    {
        parent::__construct('Invalid manifest.json: '.implode(' ', $errors));
    }
}
