<?php

namespace App\Security;

use App\ValueObjects\GitHubRepository;

/**
 * Runs the scan directly on the current process. For local development and
 * tests where Docker is unavailable (SCAN_SANDBOX_ENABLED=false).
 */
final class LocalSandboxRunner implements SandboxRunner
{
    public function __construct(private readonly ScanEngine $engine) {}

    public function scan(GitHubRepository $repository, string $sha, string $tarball): ScanResult
    {
        return $this->engine->scanTarball($tarball);
    }
}
