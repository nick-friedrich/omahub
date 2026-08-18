<?php

namespace App\Services\Security;

use App\Enums\SecurityScanStatus;
use App\Models\Plugin;
use App\Models\SecurityScan;
use App\Security\SandboxRunner;
use App\Services\GitHub\GitHubClient;
use App\ValueObjects\GitHubRepository;
use RuntimeException;

/**
 * Orchestrates a deterministic scan of a plugin at its current commit and
 * persists the scan plus its findings. Scans are idempotent per commit: a
 * completed scan for the same SHA is returned without re-running.
 */
class SecurityScanner
{
    public function __construct(
        private readonly GitHubClient $github,
        private readonly SandboxRunner $sandbox,
    ) {}

    public function scan(Plugin $plugin): SecurityScan
    {
        $sha = (string) $plugin->latest_commit_sha;

        if ($sha === '') {
            throw new RuntimeException("Plugin “{$plugin->name}” has no latest commit to scan.");
        }

        $existing = SecurityScan::query()
            ->where('plugin_id', $plugin->id)
            ->where('commit_sha', $sha)
            ->first();

        if ($existing !== null && $existing->status === SecurityScanStatus::Succeeded) {
            return $existing;
        }

        // A running/failed scan for the same commit is replaced with a fresh run.
        $existing?->delete();

        $scan = SecurityScan::query()->create([
            'plugin_id' => $plugin->id,
            'commit_sha' => $sha,
            'status' => SecurityScanStatus::Running,
            'started_at' => now(),
        ]);

        try {
            $repository = new GitHubRepository(
                (string) $plugin->repository_owner,
                (string) $plugin->repository_name,
            );

            $tarball = $this->github->tarball($repository, $sha);
            $result = $this->sandbox->scan($repository, $sha, $tarball);
        } catch (\Throwable $exception) {
            $scan->update([
                'status' => SecurityScanStatus::Failed,
                'finished_at' => now(),
            ]);
            throw $exception;
        }

        $scan->update([
            'status' => SecurityScanStatus::Succeeded,
            'risk_level' => $result->riskLevel->value,
            'rules_run' => $result->rulesRun,
            'finished_at' => now(),
        ]);

        foreach ($result->findings as $finding) {
            $scan->findings()->create([
                'rule' => $finding->rule,
                'severity' => $finding->severity,
                'file' => $finding->file,
                'line' => $finding->line,
                'snippet' => $finding->snippet,
                'description' => $finding->description,
            ]);
        }

        return $scan->refresh();
    }
}
