<?php

namespace App\Exceptions;

use DomainException;

class DuplicateSubmissionException extends DomainException
{
    public function __construct()
    {
        parent::__construct('A submission for this repository is already awaiting review.');
    }
}
