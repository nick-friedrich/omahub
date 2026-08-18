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

    private function fixture(string $name): string
    {
        return base_path("tests/Fixtures/security/{$name}");
    }
}
