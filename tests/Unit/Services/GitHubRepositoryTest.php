<?php

namespace Tests\Unit\Services;

use App\Exceptions\InvalidGitHubRepositoryUrl;
use App\ValueObjects\GitHubRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class GitHubRepositoryTest extends TestCase
{
    public function test_it_normalizes_a_public_github_repository_url(): void
    {
        $repository = GitHubRepository::fromUrl('https://github.com/Acme/Workspace-Switcher.git/');

        $this->assertSame('acme', $repository->owner);
        $this->assertSame('workspace-switcher', $repository->name);
        $this->assertSame('https://github.com/acme/workspace-switcher', $repository->canonicalUrl());
    }

    #[DataProvider('invalidUrls')]
    public function test_it_rejects_unsupported_urls(string $url): void
    {
        $this->expectException(InvalidGitHubRepositoryUrl::class);

        GitHubRepository::fromUrl($url);
    }

    public static function invalidUrls(): array
    {
        return [
            'non GitHub host' => ['https://example.com/acme/plugin'],
            'non HTTPS URL' => ['http://github.com/acme/plugin'],
            'repository path' => ['https://github.com/acme/plugin/tree/main'],
            'missing repository' => ['https://github.com/acme'],
            'query string' => ['https://github.com/acme/plugin?tab=readme'],
        ];
    }
}
