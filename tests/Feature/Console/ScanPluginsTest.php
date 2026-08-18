<?php

namespace Tests\Feature\Console;

use App\Models\Plugin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsTarballs;
use Tests\TestCase;

class ScanPluginsTest extends TestCase
{
    use BuildsTarballs;
    use RefreshDatabase;

    private function fakeTarball(Plugin $plugin, string $sha, string $fixture): void
    {
        $url = rtrim(config('services.github.codeload_url'), '/')
            ."/{$plugin->repository_owner}/{$plugin->repository_name}/tar.gz/{$sha}";

        Http::fake([
            $url => Http::response($this->tarballFromDirectory(base_path("tests/Fixtures/security/{$fixture}"))),
        ]);
    }

    public function test_command_scans_plugins_and_reports_findings(): void
    {
        $danger = Plugin::factory()->create([
            'repository_owner' => 'acme',
            'repository_name' => 'workspace-switcher',
            'latest_commit_sha' => 'abc123',
        ]);
        $this->fakeTarball($danger, 'abc123', 'malicious');

        $this->artisan('plugins:scan', ['--ids' => (string) $danger->id])
            ->expectsOutputToContain('curl_pipe_sh')
            ->assertExitCode(0);

        $this->assertSame(1, $danger->securityScans()->count());
        $this->assertSame('high', $danger->securityScans()->first()->risk_level);
    }

    public function test_command_reports_no_findings_for_clean_plugins(): void
    {
        $clean = Plugin::factory()->create([
            'repository_owner' => 'acme',
            'repository_name' => 'workspace-switcher',
            'latest_commit_sha' => 'abc123',
        ]);
        $this->fakeTarball($clean, 'abc123', 'clean');

        $this->artisan('plugins:scan', ['--ids' => (string) $clean->id])
            ->expectsOutputToContain('None')
            ->assertExitCode(0);

        $this->assertSame('none', $clean->securityScans()->first()->risk_level);
    }

    public function test_dry_run_lists_targets_without_scanning(): void
    {
        $plugin = Plugin::factory()->create(['latest_commit_sha' => 'abc123']);

        $this->artisan('plugins:scan', ['--ids' => (string) $plugin->id, '--dry-run' => true])
            ->expectsOutputToContain($plugin->slug)
            ->assertExitCode(0);

        $this->assertSame(0, $plugin->securityScans()->count());
    }
}
