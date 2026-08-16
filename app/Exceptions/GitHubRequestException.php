<?php

namespace App\Exceptions;

use RuntimeException;

class GitHubRequestException extends RuntimeException
{
    public const RATE_LIMIT_MESSAGE = 'GitHub API rate limit exceeded. Configure GITHUB_TOKEN or try again later.';

    public bool $isRateLimit = false;

    public static function repositoryNotFound(): self
    {
        return new self('The GitHub repository was not found or is not public.');
    }

    public static function rateLimited(): self
    {
        return (new self(self::RATE_LIMIT_MESSAGE))->markRateLimit();
    }

    public static function networkFailure(): self
    {
        return new self('Could not connect to the GitHub API. Try again later.');
    }

    public static function requestFailed(int $status): self
    {
        return new self("GitHub API request failed with status {$status}.");
    }

    private function markRateLimit(): static
    {
        $this->isRateLimit = true;

        return $this;
    }
}
