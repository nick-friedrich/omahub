<?php

namespace App\Services\Security;

use App\Enums\SecurityScanStatus;
use App\Models\Plugin;
use App\Models\SecurityScan;
use App\Security\SandboxRunner;
use App\Services\GitHub\GitHubClient;
use App\ValueObjects\GitHubRepository;
use Illuminate\Support\Facades\Cache;
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

        // Serialize scans per plugin so two concurrent processes (e.g. the hourly
        // scheduler and an ad-hoc/admin scan) never delete each other's in-flight
        // SecurityScan row. Without this, the non-atomic delete-then-create can
        // race and produce FK violations / "no query results for model" errors.
        $lock = Cache::lock('security-scan:'.$plugin->id, 1800);

        if (! $lock->get()) {
            throw new RuntimeException("A scan for plugin “{$plugin->name}” is already running.");
        }

        try {
            return $this->scanLocked($plugin, $sha);
        } finally {
            $lock->release();
        }
    }

    private function scanLocked(Plugin $plugin, string $sha): SecurityScan
    {
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
