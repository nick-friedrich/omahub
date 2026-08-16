<?php

namespace App\ValueObjects;

use App\Exceptions\InvalidGitHubRepositoryUrl;

final readonly class GitHubRepository
{
    public function __construct(
        public string $owner,
        public string $name,
    ) {}

    public static function fromUrl(string $url): self
    {
        $parts = parse_url(trim($url));

        if (
            $parts === false
            || ($parts['scheme'] ?? null) !== 'https'
            || strtolower($parts['host'] ?? '') !== 'github.com'
            || isset($parts['query'])
            || isset($parts['fragment'])
        ) {
            throw new InvalidGitHubRepositoryUrl;
        }

        $segments = array_values(array_filter(
            explode('/', trim($parts['path'] ?? '', '/')),
            static fn (string $segment): bool => $segment !== '',
        ));

        if (count($segments) !== 2) {
            throw new InvalidGitHubRepositoryUrl;
        }

        [$owner, $name] = $segments;
        $name = preg_replace('/\.git$/i', '', $name) ?? '';

        if (
            ! preg_match('/^[A-Za-z0-9](?:[A-Za-z0-9-]{0,38})$/', $owner)
            || ! preg_match('/^[A-Za-z0-9_.-]+$/', $name)
        ) {
            throw new InvalidGitHubRepositoryUrl;
        }

        return new self(strtolower($owner), strtolower($name));
    }

    public function canonicalUrl(): string
    {
        return "https://github.com/{$this->owner}/{$this->name}";
    }
}
