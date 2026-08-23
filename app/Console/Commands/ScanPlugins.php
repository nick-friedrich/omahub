<?php

namespace App\Console\Commands;

use App\Enums\RiskLevel;
use App\Enums\SecurityScanStatus;
use App\Exceptions\GitHubRequestException;
use App\Models\Plugin;
use App\Models\SecurityFinding;
use App\Models\SecurityScan;
use App\Services\Security\SecurityScanner;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ScanPlugins extends Command
{
    protected $signature = 'plugins:scan
        {--ids= : Comma-separated plugin IDs to scan}
        {--after= : Only scan plugins with an ID greater than this (resume a stopped run)}
        {--stale : Only scan plugins whose latest commit has not been successfully scanned}
        {--dry-run : Report what would be scanned without running anything}
        {--limit= : Maximum number of plugins to scan}';

    protected $description = 'Run the deterministic security scan on plugins at their current commit';

    public function handle(SecurityScanner $scanner): int
    {
        $plugins = $this->targetPlugins();

        if ($plugins->isEmpty()) {
            $this->info('No plugins matched the given filters.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info("Scanning {$plugins->count()} plugin(s).");
        $this->newLine();

        if ($this->option('dry-run')) {
            $this->table(
                ['ID', 'Slug', 'Repository', 'Commit'],
                $plugins->map(fn (Plugin $plugin) => [
                    $plugin->id,
                    $plugin->slug,
                    "{$plugin->repository_owner}/{$plugin->repository_name}",
                    (string) $plugin->latest_commit_sha,
                ]),
            );

            return self::SUCCESS;
        }

        $scanned = 0;
        $removed = 0;
        $failed = 0;

        foreach ($plugins as $plugin) {
            $label = "{$plugin->repository_owner}/{$plugin->repository_name}";

            try {
                $scan = $scanner->scan($plugin);
            } catch (GitHubRequestException $exception) {
                if ($exception->isRateLimit) {
                    $this->newLine();
                    $this->error('Stopped: '.$exception->getMessage());
                    $this->warn('Set GITHUB_TOKEN in .env (e.g. `echo "GITHUB_TOKEN=$(gh auth token)" >> .env`) to raise the limit.');
                    $this->warn("Resume where this run stopped with: php artisan plugins:scan --after={$plugin->id}");

                    return self::FAILURE;
                }

                if ($exception->isNotFound) {
                    $plugin->markRepositoryRemoved();
                    $removed++;
                    $this->warn("  GONE  {$label} — repository no longer available (unpublished)");

                    continue;
                }

                $failed++;
                $this->error("FAILED  {$label}: {$exception->getMessage()}");

                continue;
            } catch (\Throwable $exception) {
                $failed++;
                $this->error("FAILED  {$label}: {$exception->getMessage()}");

                continue;
            }

            $scanned++;
            $this->printScan($scan, $label);
        }

        $this->newLine();

        if ($failed > 0 || $removed > 0) {
            $this->error("Complete: {$scanned} scanned, {$removed} removed, {$failed} failed.");
        } else {
            $this->info("Complete: {$scanned} scanned.");
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function printScan(SecurityScan $scan, string $label): void
    {
        $risk = RiskLevel::tryFrom((string) $scan->risk_level) ?? RiskLevel::None;

        /** @var Collection<int, SecurityFinding> $findings */
        $findings = $scan->findings()->get();

        if ($findings->isEmpty()) {
            $this->info("  OK    {$label} — {$risk->label()} ({$scan->commit_sha})");

            return;
        }

        $this->warn("  FIND  {$label} — {$risk->label()}, {$findings->count()} finding(s) ({$scan->commit_sha})");

        foreach ($findings as $finding) {
            $location = "{$finding->file}".($finding->line ? ":{$finding->line}" : '');
            $this->line("        [{$finding->severity}] {$finding->rule} {$location} — {$finding->description}");
        }
    }

    /** @return Collection<int, Plugin> */
    private function targetPlugins()
    {
        $query = Plugin::query();

        $ids = $this->option('ids');
        if (is_string($ids) && $ids !== '') {
            $idList = array_filter(array_map('intval', explode(',', $ids)), fn (int $id): bool => $id > 0);
            $query->whereIntegerInRaw('id', $idList);
        }

        $after = $this->option('after');
        if (is_string($after) && is_numeric($after) && (int) $after >= 1) {
            $query->where('id', '>', (int) $after);
        }

        // Scan only plugins whose latest commit has no successful scan yet.
        // Idempotency (scan unique per plugin+commit) makes this the cheap way
        // to keep reviewed state current: refresh commits, then scan --stale.
        // Plugins whose repository has disappeared are skipped — the refresh
        // step flags them, so there is nothing left to scan.
        if ($this->option('stale')) {
            $query
                ->whereNull('repository_removed_at')
                ->whereNotNull('latest_commit_sha')
                ->whereDoesntHave('securityScans', function (Builder $q): void {
                    $q->where('status', SecurityScanStatus::Succeeded)
                        ->whereColumn('security_scans.commit_sha', 'plugins.latest_commit_sha');
                });
        }

        $limit = $this->option('limit');
        if (is_string($limit) && is_numeric($limit) && (int) $limit > 0) {
            $query->limit((int) $limit);
        }

        return $query->get();
    }
}
