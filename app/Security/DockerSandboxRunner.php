<?php

namespace App\Security;

use App\ValueObjects\GitHubRepository;
use RuntimeException;

/**
 * Runs the deterministic scan inside a disposable Docker container.
 *
 * The scanner image mounts this repository read-only and executes the shared
 * `scan:execute` artisan command, so the sandbox always runs the same rule
 * code as the host. The untrusted tarball is piped over stdin and extracted
 * inside the container, never on the host.
 */
final class DockerSandboxRunner implements SandboxRunner
{
    public function __construct(
        private readonly string $image,
        private readonly string $containerRepoPath,
    ) {}

    public function scan(GitHubRepository $repository, string $sha, string $tarball): ScanResult
    {
        $hostRepo = rtrim(base_path(), '/');
        $containerRepo = rtrim($this->containerRepoPath, '/');
        $mount = "{$hostRepo}:{$containerRepo}:ro";

        $command = [
            'docker', 'run', '--rm', '-i',
            '-v', $mount,
            '-w', $containerRepo,
            $this->image,
            'php', 'artisan', 'scan:execute',
            "--owner={$repository->owner}",
            "--repo={$repository->name}",
            "--sha={$sha}",
        ];

        [$code, $stdout, $stderr] = $this->run($command, $tarball);

        if ($code !== 0) {
            throw new RuntimeException(
                'Sandbox scan failed (exit '.$code.'): '.trim($stderr !== '' ? $stderr : $stdout),
            );
        }

        $data = json_decode($stdout, true);

        if (! is_array($data)) {
            throw new RuntimeException('Sandbox scan returned invalid JSON output.');
        }

        return ScanResult::fromArray($data);
    }

    /**
     * @param  list<string>  $command
     * @return array{0: int, 1: string, 2: string}
     */
    private function run(array $command, string $stdin): array
    {
        $descriptor = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptor, $pipes);

        if (! is_resource($process)) {
            throw new RuntimeException('Could not start the sandbox container.');
        }

        if ($stdin !== '') {
            fwrite($pipes[0], $stdin);
        }

        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        return [$exitCode, (string) $stdout, (string) $stderr];
    }
}
