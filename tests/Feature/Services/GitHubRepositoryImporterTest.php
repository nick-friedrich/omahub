<?php

namespace Tests\Feature\Services;

use App\Enums\PluginStatus;
use App\Exceptions\GitHubRequestException;
use App\Exceptions\ManifestValidationException;
use App\Models\Plugin;
use App\Services\Plugins\GitHubRepositoryImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\FakesGitHub;
use Tests\TestCase;

class GitHubRepositoryImporterTest extends TestCase
{
    use FakesGitHub;
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

    public function test_a_moved_repository_updates_the_existing_plugin_instead_of_duplicating_it(): void
    {
        $this->fakeGitHub(sha: str_repeat('a', 40));
        $original = app(GitHubRepositoryImporter::class)->import('https://github.com/acme/workspace-switcher');

        // The repository has since been renamed: GitHub redirects acme -> newowner.
        $this->fakeGitHub(sha: str_repeat('b', 40), redirectedOwner: 'newowner');
        $updated = app(GitHubRepositoryImporter::class)->import('https://github.com/acme/workspace-switcher');

        $this->assertSame($original->id, $updated->id);
        $this->assertSame('newowner', $updated->repository_owner);
        $this->assertSame('workspace-switcher', $updated->repository_name);
        $this->assertDatabaseCount('plugins', 1);
    }

    public function test_a_duplicate_manifest_id_gets_a_unique_slug_instead_of_colliding(): void
    {
        Plugin::factory()->create([
            'slug' => 'acme.workspace-switcher',
            'repository_owner' => 'dillanmateushkl',
            'repository_name' => 'multi-monitor-workspaces',
        ]);

        $this->fakeGitHub();
        $plugin = app(GitHubRepositoryImporter::class)->import('https://github.com/acme/workspace-switcher');

        $this->assertSame('acme.workspace-switcher-2', $plugin->slug);
        $this->assertDatabaseCount('plugins', 2);
    }

    public function test_unchanged_repository_is_skipped_when_the_etag_matches(): void
    {
        $this->fakeGitHub(sha: str_repeat('a', 40));
        $plugin = app(GitHubRepositoryImporter::class)->import('https://github.com/acme/workspace-switcher');

        $etag = $plugin->github_etag;
        $this->assertNotNull($etag);

        $refresh = app(GitHubRepositoryImporter::class)->import(
            'https://github.com/acme/workspace-switcher',
            $etag,
        );

        $this->assertSame($plugin->id, $refresh->id);
        $this->assertSame(str_repeat('a', 40), $refresh->latest_commit_sha);
        $this->assertSame($etag, $refresh->github_etag);
        $this->assertDatabaseCount('plugins', 1);
    }

    public function test_a_removed_repository_restores_the_plugin_when_it_comes_back(): void
    {
        $this->fakeGitHub(sha: str_repeat('a', 40));
        $plugin = app(GitHubRepositoryImporter::class)->import('https://github.com/acme/workspace-switcher');
        $plugin->markRepositoryRemoved();
        $this->assertTrue($plugin->refresh()->isRepositoryRemoved());

        $restored = app(GitHubRepositoryImporter::class)->import('https://github.com/acme/workspace-switcher');

        $this->assertSame($plugin->id, $restored->id);
        $this->assertFalse($restored->isRepositoryRemoved());
        $this->assertNull($restored->repository_removed_at);
    }

    public function test_importing_delete_not_found_throws_a_not_found_exception(): void
    {
        Http::fake([
            'api.github.com/*' => Http::response(['message' => 'Not Found'], 404),
        ]);

        $this->expectException(GitHubRequestException::class);
        $this->expectExceptionMessage('The GitHub repository was not found or is not public.');

        try {
            app(GitHubRepositoryImporter::class)->import('https://github.com/acme/deleted-repo');
        } finally {
            $this->assertDatabaseCount('plugins', 0);
        }
    }
}
