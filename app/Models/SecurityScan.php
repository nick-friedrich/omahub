<?php

namespace App\Models;

use App\Enums\SecurityScanStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function plugin(): BelongsTo
    {
        return $this->belongsTo(Plugin::class);
    }

    /** @return HasMany<SecurityFinding, $this> */
    public function findings(): HasMany
    {
        return $this->hasMany(SecurityFinding::class);
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
