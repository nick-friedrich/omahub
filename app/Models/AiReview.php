<?php

namespace App\Models;

use App\Enums\AiRecommendation;
use App\Enums\AiReviewStatus;
use App\Enums\RiskLevel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An advisory AI review of a plugin repository at an exact commit. It runs on
 * top of the deterministic scan, combines those findings with an independent
 * look at the repository content, and produces a plain-language risk
 * assessment. A review is advisory only — it never replaces or blocks the
 * deterministic review or human approval.
 *
 * Like SecurityScan, a review is keyed to (plugin_id, commit_sha): re-running a
 * review for the same commit is a no-op.
 */
#[Fillable([
    'plugin_id',
    'security_scan_id',
    'commit_sha',
    'status',
    'provider',
    'model',
    'risk_level',
    'recommendation',
    'summary',
    'concerns',
    'raw_response',
    'started_at',
    'finished_at',
])]
class AiReview extends Model
{
    protected function casts(): array
    {
        return [
            'status' => AiReviewStatus::class,
            'risk_level' => RiskLevel::class,
            'recommendation' => AiRecommendation::class,
            'concerns' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Plugin, $this> */
    public function plugin(): BelongsTo
    {
        return $this->belongsTo(Plugin::class);
    }

    /** @return BelongsTo<SecurityScan, $this> */
    public function scan(): BelongsTo
    {
        return $this->belongsTo(SecurityScan::class, 'security_scan_id');
    }

    public function scopeForPlugin(Builder $query, Plugin $plugin): Builder
    {
        return $query->where('plugin_id', $plugin->id);
    }
}
