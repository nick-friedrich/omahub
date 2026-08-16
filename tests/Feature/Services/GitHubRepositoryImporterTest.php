<?php

namespace Tests\Feature\Services;

use App\Enums\PluginStatus;
use App\Exceptions\ManifestValidationException;
use App\Services\Plugins\GitHubRepositoryImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class GitHubRepositoryImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_repository_manifest_and_github_metadata(): void
    {
        $this->fakeGitHub(stars: 42, sha: str_repeat('a', 40));

        $plugin = app(GitHubRepositoryImporter::class)->import('https://github.com/Acme/workspace-switcher');

        $this->assertSame('acme.workspace-switcher', $plugin->slug);
        $this->assertSame('Workspace Switcher', $plugin->name);
        $this->assertSame('acme', $plugin->repository_owner);
        $this->assertSame('workspace-switcher', $plugin->repository_name);
        $this->assertSame('v1.2.0', $plugin->latest_version);
        $this->assertSame(42, $plugin->stars_count);
        $this->assertSame(str_repeat('a', 40), $plugin->latest_commit_sha);
        $this->assertSame('# Workspace Switcher', $plugin->readme_markdown);
        $this->assertSame('MIT', $plugin->license);
        $this->assertSame(PluginStatus::Pending, $plugin->status);
        $this->assertNull($plugin->published_at);
    }

    public function test_importing_the_same_repository_updates_the_existing_plugin(): void
    {
        $this->fakeGitHub(stars: 10, sha: str_repeat('a', 40));
        $original = app(GitHubRepositoryImporter::class)->import('https://github.com/acme/workspace-switcher');
        $original->update([
            'status' => PluginStatus::Published,
            'published_at' => now(),
        ]);

        $this->fakeGitHub(stars: 99, sha: str_repeat('b', 40));
        $updated = app(GitHubRepositoryImporter::class)->import('https://github.com/ACME/workspace-switcher.git');

        $this->assertSame($original->id, $updated->id);
        $this->assertSame(99, $updated->stars_count);
        $this->assertSame(str_repeat('b', 40), $updated->latest_commit_sha);
        $this->assertSame(PluginStatus::Published, $updated->status);
        $this->assertDatabaseCount('plugins', 1);
    }

    public function test_invalid_manifest_does_not_create_a_partial_plugin(): void
    {
        $this->fakeGitHub(manifest: '{"schemaVersion": 1}');

        $this->expectException(ManifestValidationException::class);

        try {
            app(GitHubRepositoryImporter::class)->import('https://github.com/acme/workspace-switcher');
        } finally {
            $this->assertDatabaseCount('plugins', 0);
        }
    }

    private function fakeGitHub(
        int $stars = 42,
        string $sha = 'abc123',
        ?string $manifest = null,
    ): void {
        $manifest ??= file_get_contents(base_path('tests/Fixtures/plugins/valid/manifest.json'));

        Http::swap(new Factory);
        Http::fake(function (Request $request) use ($stars, $sha, $manifest) {
            $path = parse_url($request->url(), PHP_URL_PATH);

            return match ($path) {
                '/repos/acme/workspace-switcher' => Http::response([
                    'name' => 'workspace-switcher',
                    'html_url' => 'https://github.com/Acme/workspace-switcher',
                    'description' => 'A workspace utility.',
                    'homepage' => 'https://example.com/workspace-switcher',
                    'default_branch' => 'main',
                    'stargazers_count' => $stars,
                    'forks_count' => 7,
                    'open_issues_count' => 2,
                    'archived' => false,
                    'pushed_at' => '2026-08-15T12:00:00Z',
                    'license' => ['spdx_id' => 'MIT'],
                    'owner' => [
                        'login' => 'Acme',
                        'html_url' => 'https://github.com/Acme',
                    ],
                ]),
                '/repos/acme/workspace-switcher/contents/manifest.json' => Http::response($manifest),
                '/repos/acme/workspace-switcher/contents/Service.qml',
                '/repos/acme/workspace-switcher/contents/Widget.qml' => Http::response(['type' => 'file']),
                '/repos/acme/workspace-switcher/readme' => Http::response('# Workspace Switcher'),
                '/repos/acme/workspace-switcher/commits/main' => Http::response(['sha' => $sha]),
                '/repos/acme/workspace-switcher/releases/latest' => Http::response(['tag_name' => 'v1.2.0']),
                '/repos/acme/workspace-switcher/license' => Http::response(['license' => ['spdx_id' => 'MIT']]),
                default => throw new RuntimeException("Unexpected GitHub request: {$request->url()}"),
            };
        });
    }
}
