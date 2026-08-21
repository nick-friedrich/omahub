<?php

namespace App\Console\Commands;

use App\Security\ScanEngine;
use Illuminate\Console\Command;

/**
 * Internal command that runs the deterministic scan inside the sandbox
 * container. It reads the untrusted repository tarball from stdin and prints
 * a stable JSON result to stdout. It is not meant to be run by hand; the
 * DockerSandboxRunner invokes it from within a disposable container.
 */
class ScanExecute extends Command
{
    protected $signature = 'scan:execute
        {--owner= : Repository owner}
        {--repo= : Repository name}
        {--sha= : Commit SHA being scanned}';

    protected $description = 'Run the deterministic scan on a tarball from stdin (internal, used by the sandbox)';

    /**
     * Output must be pure JSON on stdout: nothing that a scan phase prints
     * should ever reach the terminal.
     */
    public function handle(ScanEngine $engine): int
    {
        $stdin = fopen('php://stdin', 'r');
        $tarball = $stdin === false ? '' : (string) stream_get_contents($stdin);

        $result = $engine->scanTarball($tarball);

        echo json_encode(
            $result->toArray(),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );

        return self::SUCCESS;
    }
}
