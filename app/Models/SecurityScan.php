<?php

namespace App\Models;

use App\Enums\SecurityScanStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * A deterministic content scan of a single plugin repository at an exact commit.
 *
 * A scan is uniquely identified by (plugin_id, commit_sha), so re-running a scan
 * against the same commit is a no-op and results are reproducible.
 */
#[Fillable([
    'plugin_id',
    'commit_sha',
    'status',
    'risk_level',
    'rules_run',
    'started_at',
    'finished_at',
])]
class SecurityScan extends Model
{
    protected function casts(): array
    {
        return [
            'status' => SecurityScanStatus::class,
            'rules_run' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Plugin, $this> */
    public function plugin(): BelongsTo
    {
        return $this->belongsTo(Plugin::class);
    }

    /** @return HasMany<SecurityFinding, $this> */
    public function findings(): HasMany
    {
        return $this->hasMany(SecurityFinding::class);
    }

    /**
     * Findings ordered most-relevant-first: executable-code findings sorted by
     * severity (critical → high → medium → low), then documentation findings
     * last. Documentation findings are grouped at the end regardless of their
     * severity since they describe usage, not executable code.
     *
     * @return Collection<int, SecurityFinding>
     */
    public function sortedFindings(): Collection
    {
        $severityRank = [
            'critical' => 4,
            'high' => 3,
            'medium' => 2,
            'low' => 1,
        ];

        return $this->findings
            ->sortByDesc(function (SecurityFinding $finding) use ($severityRank): int {
                $rank = $severityRank[strtolower((string) $finding->severity)] ?? 0;

                return $finding->isDocumentation() ? $rank : 100 + $rank;
            })
            ->values();
    }

    public function scopeForPlugin(Builder $query, Plugin $plugin): Builder
    {
        return $query->where('plugin_id', $plugin->id);
    }

    public function latestForPlugin(Builder $query, Plugin $plugin): Builder
    {
        return $this->scopeForPlugin($query, $plugin)->orderByDesc('id');
    }
}
