<?php

namespace App\Models;

use App\Enums\SubmissionStatus;
use Database\Factories\PluginSubmissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'repository_url',
    'plugin_id',
    'status',
    'failure_reason',
    'submitted_at',
    'reviewed_at',
])]
class PluginSubmission extends Model
{
    /** @use HasFactory<PluginSubmissionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => SubmissionStatus::class,
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Plugin, $this> */
    public function plugin(): BelongsTo
    {
        return $this->belongsTo(Plugin::class);
    }
}
