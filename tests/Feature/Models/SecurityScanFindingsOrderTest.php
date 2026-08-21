<?php

namespace Tests\Feature\Models;

use App\Enums\SecurityScanStatus;
use App\Models\Plugin;
use App\Models\SecurityScan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityScanFindingsOrderTest extends TestCase
{
    use RefreshDatabase;

    private function makeScan(): SecurityScan
    {
        return SecurityScan::query()->create([
            'plugin_id' => Plugin::factory()->create()->id,
            'commit_sha' => 'abc123',
            'status' => SecurityScanStatus::Succeeded,
            'risk_level' => 'high',
            'rules_run' => [],
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ]);
    }

    public function test_sorted_findings_order_high_to_medium_to_low_to_docs(): void
    {
        $scan = $this->makeScan();

        foreach ([
            ['file' => 'install.sh', 'severity' => 'medium', 'rule' => 'sudo', 'description' => 'Elevates privileges.'],
            ['file' => 'README.md', 'severity' => 'medium', 'rule' => 'sudo', 'description' => 'Usage example.'],
            ['file' => 'Service.qml', 'severity' => 'high', 'rule' => 'persistence', 'description' => 'Starts at boot.'],
            ['file' => 'docs/guide.md', 'severity' => 'high', 'rule' => 'persistence', 'description' => 'Docs mention a systemd unit.'],
            ['file' => 'helper.sh', 'severity' => 'low', 'rule' => 'external_hosts', 'description' => 'Contacts a host.'],
        ] as $finding) {
            $scan->findings()->create($finding);
        }

        $ordered = $scan->sortedFindings();

        $this->assertSame(
            ['Service.qml', 'install.sh', 'helper.sh', 'docs/guide.md', 'README.md'],
            $ordered->map(fn ($finding) => $finding->file)->all(),
        );
    }
}
