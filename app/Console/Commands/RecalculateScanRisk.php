<?php

namespace App\Console\Commands;

use App\Enums\RiskLevel;
use App\Enums\SecurityScanStatus;
use App\Models\SecurityFinding;
use App\Models\SecurityScan;
use App\Security\DocumentationFile;
use Illuminate\Console\Command;

/**
 * Recomputes the whole-scan risk level from the persisted findings using the
 * same policy as ScanEngine: documentation findings (README, docs/, *.md) are
 * reported but never determine high risk, and a scan whose only findings are
 * documentation is capped at Low. Run this after changing the risk policy or
 * rule severities without re-running every scan.
 */
class RecalculateScanRisk extends Command
{
    protected $signature = 'security:recalibrate
        {--plugin= : Only recalibrate scans for this plugin ID}';

    protected $description = 'Recompute whole-scan risk levels from stored findings (documentation-aware)';

    public function handle(): int
    {
        $query = SecurityScan::query()
            ->with('findings')
            ->where('status', SecurityScanStatus::Succeeded);

        if (is_numeric($this->option('plugin'))) {
            $query->where('plugin_id', (int) $this->option('plugin'));
        }

        $scans = $query->get();

        if ($scans->isEmpty()) {
            $this->info('No successful scans to recalibrate.');

            return self::SUCCESS;
        }

        $changed = 0;

        foreach ($scans as $scan) {
            $code = $scan->findings->filter(fn (SecurityFinding $finding) => ! DocumentationFile::matches((string) $finding->file));
            $doc = $scan->findings->filter(fn (SecurityFinding $finding) => DocumentationFile::matches((string) $finding->file));

            $risk = match (true) {
                $code->isNotEmpty() => RiskLevel::aggregate(
                    $code->map(fn (SecurityFinding $finding) => RiskLevel::tryFrom($finding->severity) ?? RiskLevel::None),
                ),
                $doc->isNotEmpty() => RiskLevel::Low,
                default => RiskLevel::None,
            };

            if ($scan->risk_level !== $risk->value) {
                $scan->forceFill(['risk_level' => $risk])->save();
                $changed++;
                $this->line("  scan #{$scan->id} (plugin {$scan->plugin_id}): → {$risk->value}");
            }
        }

        $this->info("Recalibrated {$changed} of {$scans->count()} successfully scanned plugin(s).");

        return self::SUCCESS;
    }
}