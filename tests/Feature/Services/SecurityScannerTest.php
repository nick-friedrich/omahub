<?php

namespace Tests\Feature\Services;

use App\Enums\SecurityScanStatus;
use App\Models\Plugin;
use App\Services\Security\SecurityScanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsTarballs;
use Tests\TestCase;

class SecurityScannerTest extends TestCase
{
    use BuildsTarballs;
    use RefreshDatabase;

    private function createPlugin(string $sha = 'abc123'): Plugin
    {
        return Plugin::factory()->create([
            'repository_owner' => 'acme',
            'repository_name' => 'workspace-switcher',
            'latest_commit_sha' => $sha,
        ]);
    }

    private function fakeTarball(string $sha, string $fixture): void
    {
        $url = rtrim(config('services.github.codeload_url'), '/')
            .'/acme/workspace-switcher/tar.gz/'.$sha;

        Http::fake([
            $url => Http::response($this->tarballFromDirectory(base_path("tests/Fixtures/security/{$fixture}"))),
        ]);
    }

    public function test_it_scans_and_persists_a_successful_result(): void
    {
        $plugin = $this->createPlugin('abc123');
        $this->fakeTarball('abc123', 'malicious');

        $scan = app(SecurityScanner::class)->scan($plugin);

        $this->assertSame(SecurityScanStatus::Succeeded, $scan->status);
        $this->assertSame('high', $scan->risk_level);
        $this->assertSame('abc123', $scan->commit_sha);
        $this->assertNotEmpty($scan->findings);
        $this->assertDatabaseHas('security_scans', [
            'plugin_id' => $plugin->id,
            'commit_sha' => 'abc123',
            'risk_level' => 'high',
        ]);
    }

    public function test_rescanning_the_same_commit_is_idempotent(): void
    {
        $plugin = $this->createPlugin('abc123');
        $this->fakeTarball('abc123', 'malicious');

        $first = app(SecurityScanner::class)->scan($plugin);
        $second = app(SecurityScanner::class)->scan($plugin);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, $plugin->securityScans()->count());
        $this->assertTrue($first->findings()->count() > 0);
    }

    public function test_a_new_commit_triggers_a_fresh_scan(): void
    {
        $plugin = $this->createPlugin('abc123');
        $this->fakeTarball('abc123', 'clean');
        app(SecurityScanner::class)->scan($plugin);

        $plugin->update(['latest_commit_sha' => 'def456']);
        $this->fakeTarball('def456', 'malicious');

        $scan = app(SecurityScanner::class)->scan($plugin);

        $this->assertSame('def456', $scan->commit_sha);
        $this->assertSame(2, $plugin->securityScans()->count());
        $this->assertSame('high', $scan->risk_level);
    }

    public function test_a_clean_repo_produces_no_findings(): void
    {
        $plugin = $this->createPlugin('abc123');
        $this->fakeTarball('abc123', 'clean');

        $scan = app(SecurityScanner::class)->scan($plugin);

        $this->assertSame(SecurityScanStatus::Succeeded, $scan->status);
        $this->assertSame('none', $scan->risk_level);
        $this->assertCount(0, $scan->findings);
    }

    public function test_a_failed_tarball_download_records_a_failed_scan(): void
    {
        $plugin = $this->createPlugin('abc123');

        Http::fake(['*' => Http::response('Not Found', 404)]);

        try {
            app(SecurityScanner::class)->scan($plugin);
            $this->fail('Scan should have thrown for a missing tarball.');
        } catch (\Throwable) {
            // expected
        }

        $scan = $plugin->securityScans()->first();
        $this->assertNotNull($scan);
        $this->assertSame(SecurityScanStatus::Failed, $scan->status);
        $this->assertNull($scan->risk_level);
        $this->assertCount(0, $scan->findings);
    }
}
