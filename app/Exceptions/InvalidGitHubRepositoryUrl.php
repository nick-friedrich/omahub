<?php

namespace App\Exceptions;

use InvalidArgumentException;

class InvalidGitHubRepositoryUrl extends InvalidArgumentException
{
    public function __construct()
    {
        parent::__construct('Enter a public GitHub repository URL such as https://github.com/owner/repository.');
    }
}
