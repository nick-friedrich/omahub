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
        private readonly ?string $hostRepoPath = null,
    ) {}

    public function scan(GitHubRepository $repository, string $sha, string $tarball): ScanResult
    {
        if ($this->image === '') {
            throw new RuntimeException(
                'No sandbox image configured — set SCAN_SANDBOX_IMAGE to a local image tag (see agents.md).',
            );
        }

        // The -v source is resolved by the Docker daemon. When the app itself runs
        // inside a container (Docker-out-of-Docker), base_path() is a container path;
        // SCAN_SANDBOX_HOST_REPO_PATH must then hold the host path of this repo.
        $hostRepo = rtrim($this->hostRepoPath ?? base_path(), '/');
        $containerRepo = rtrim($this->containerRepoPath, '/');
        $mount = "{$hostRepo}:{$containerRepo}:ro";

        $command = [
            'docker', 'run', '--rm', '-i',
            '-v', $mount,
            '-w', $containerRepo,
            $this->image,
            // The scan reads the full tarball into memory, and large repos exceed
            // the default 128M CLI limit — raise it for the sandbox process.
            'php', '-d', 'memory_limit=1G', 'artisan', 'scan:execute',
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
