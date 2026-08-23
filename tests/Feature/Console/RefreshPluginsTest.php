<?php

namespace Tests\Feature\Console;

use App\Enums\PluginStatus;
use App\Models\Plugin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\FakesGitHub;
use Tests\TestCase;

class RefreshPluginsTest extends TestCase
{
    use FakesGitHub;
    use RefreshDatabase;

    public function test_it_reimports_existing_plugins_and_fetches_their_readmes(): void
    {
        $this->fakeGitHub(sha: str_repeat('a', 40));
        $plugin = Plugin::factory()->create([
            'repository_owner' => 'acme',
            'repository_name' => 'workspace-switcher',
            'readme_markdown' => null,
            'status' => PluginStatus::Published,
            'published_at' => now(),
        ]);

        $this->artisan('plugins:refresh', ['--ids' => (string) $plugin->id])
            ->expectsOutputToContain('readme present')
            ->assertSuccessful();

        $plugin->refresh();
        $this->assertSame('# Workspace Switcher', $plugin->readme_markdown);
        $this->assertSame(str_repeat('a', 40), $plugin->latest_commit_sha);
        $this->assertSame(PluginStatus::Published, $plugin->status);
    }

    public function test_an_unexpected_import_failure_is_reported_instead_of_aborting_the_run(): void
    {
        // A 500 from the API becomes a request-failed exception; the command
        // must report it as a per-plugin failure and keep going.
        $this->fakeGitHub(routes: [
            '/repos/acme/workspace-switcher' => Http::response([], 500),
        ]);
        $failed = Plugin::factory()->create([
            'repository_owner' => 'acme',
            'repository_name' => 'workspace-switcher',
        ]);

        $this->artisan('plugins:refresh', ['--ids' => (string) $failed->id])
            ->expectsOutputToContain('FAILED')
            ->assertFailed();
    }

    public function test_it_aborts_on_rate_limit_with_a_resume_hint(): void
    {
        $this->fakeGitHub(routes: [
            '/repos/acme/workspace-switcher' => Http::response([], 429, ['X-RateLimit-Remaining' => '0']),
        ]);
        Plugin::factory()->create([
            'repository_owner' => 'acme',
            'repository_name' => 'workspace-switcher',
            'readme_markdown' => null,
        ]);
        Plugin::factory()->create([
            'repository_owner' => 'other',
            'repository_name' => 'something',
            'readme_markdown' => null,
        ]);

        $this->artisan('plugins:refresh')
            ->expectsOutputToContain('Stopped:')
            ->expectsOutputToContain('--after=')
            ->assertFailed();

        // Neither plugin was refreshed after the abort.
        $this->assertDatabaseHas('plugins', ['repository_name' => 'something', 'readme_markdown' => null]);
    }

    public function test_missing_readme_flag_only_targets_plugins_without_a_readme(): void
    {
        $withReadme = Plugin::factory()->create([
            'repository_owner' => 'acme',
            'repository_name' => 'workspace-switcher',
            'readme_markdown' => '# Existing',
        ]);
        Plugin::factory()->create([
            'repository_owner' => 'acme',
            'repository_name' => 'second-plugin',
            'readme_markdown' => null,
        ]);

        // --dry-run avoids any HTTP calls, so it cleanly shows only the
        // matched plugin (the one missing a readme) in the target list.
        $this->artisan('plugins:refresh', ['--missing-readme' => true, '--dry-run' => true])
            ->expectsOutputToContain('acme/second-plugin')
            ->doesntExpectOutputToContain('acme/workspace-switcher')
            ->assertSuccessful();

        $withReadme->refresh();
        $this->assertSame('# Existing', $withReadme->readme_markdown);
    }

    public function test_dry_run_does_not_call_github_or_change_data(): void
    {
        $this->fakeGitHub();
        $plugin = Plugin::factory()->create([
            'repository_owner' => 'acme',
            'repository_name' => 'workspace-switcher',
            'readme_markdown' => null,
        ]);

        $this->artisan('plugins:refresh', ['--ids' => (string) $plugin->id, '--dry-run' => true])
            ->expectsOutputToContain('acme/workspace-switcher')
            ->assertSuccessful();

        $plugin->refresh();
        $this->assertNull($plugin->readme_markdown);
    }

    public function test_it_reports_and_survives_a_failed_repository(): void
    {
        $this->fakeGitHub(routes: [
            '/repos/missing/nowhere' => Http::response([], 500),
        ]);
        Plugin::factory()->create([
            'repository_owner' => 'acme',
            'repository_name' => 'workspace-switcher',
            'status' => PluginStatus::Published,
        ]);
        Plugin::factory()->create([
            'repository_owner' => 'missing',
            'repository_name' => 'nowhere',
            'status' => PluginStatus::Pending,
        ]);

        $this->artisan('plugins:refresh')
            ->expectsOutputToContain('FAILED')
            ->expectsOutputToContain('1 failed')
            ->assertFailed();

        // The valid one still got refreshed; no exception escaped.
        $this->assertDatabaseCount('plugins', 2);
    }

    public function test_a_deleted_repository_is_unpublished_and_flagged(): void
    {
        $this->fakeGitHub(routes: [
            '/repos/acme/vaporised' => Http::response(['message' => 'Not Found'], 404),
        ]);
        $plugin = Plugin::factory()->create([
            'repository_owner' => 'acme',
            'repository_name' => 'vaporised',
            'status' => PluginStatus::Published,
            'published_at' => now(),
        ]);

        $this->artisan('plugins:refresh', ['--ids' => (string) $plugin->id])
            ->expectsOutputToContain('GONE')
            ->assertSuccessful();

        $plugin->refresh();
        $this->assertSame(PluginStatus::Archived, $plugin->status);
        $this->assertNotNull($plugin->repository_removed_at);
    }
}
