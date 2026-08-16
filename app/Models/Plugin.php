<?php

namespace App\Models;

use App\Enums\PluginStatus;
use Database\Factories\PluginFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'slug',
    'name',
    'description',
    'repository_url',
    'repository_owner',
    'repository_name',
    'author_name',
    'author_url',
    'license',
    'homepage_url',
    'icon_url',
    'manifest_data',
    'readme_markdown',
    'default_branch',
    'latest_commit_sha',
    'latest_version',
    'stars_count',
    'forks_count',
    'open_issues_count',
    'is_archived',
    'last_pushed_at',
    'last_indexed_at',
    'published_at',
    'status',
])]
class Plugin extends Model
{
    /** @use HasFactory<PluginFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'manifest_data' => 'array',
            'is_archived' => 'boolean',
            'last_pushed_at' => 'datetime',
            'last_indexed_at' => 'datetime',
            'published_at' => 'datetime',
            'status' => PluginStatus::class,
        ];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', PluginStatus::Published)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(PluginSubmission::class);
    }
}
