<?php

namespace App\Exceptions;

use RuntimeException;

class GitHubRequestException extends RuntimeException
{
    public static function repositoryNotFound(): self
    {
        return new self('The GitHub repository was not found or is not public.');
    }

    public static function rateLimited(): self
    {
        return new self('GitHub API rate limit exceeded. Configure GITHUB_TOKEN or try again later.');
    }

    public static function networkFailure(): self
    {
        return new self('Could not connect to the GitHub API. Try again later.');
    }

    public static function requestFailed(int $status): self
    {
        return new self("GitHub API request failed with status {$status}.");
    }
}
