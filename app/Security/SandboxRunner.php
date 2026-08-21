<?php

namespace App\Security;

use App\ValueObjects\GitHubRepository;

/**
 * Runs the deterministic scan against untrusted repository content in a way
 * that does not execute on the host. Production uses Docker; tests and local
 * development without Docker use the local runner.
 */
interface SandboxRunner
{
    public function scan(GitHubRepository $repository, string $sha, string $tarball): ScanResult;
}
