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

    /**
     * Root directory for this plugin's raw content on GitHub, used to resolve
     * relative image paths in the README. Returns null when repository identity
     * is unavailable.
     */
    public function rawContentBaseUrl(): ?string
    {
        if (! is_string($this->default_branch) || $this->default_branch === '') {
            return null;
        }

        return "https://raw.githubusercontent.com/{$this->repository_owner}/{$this->repository_name}/{$this->default_branch}";
    }

    /**
     * GitHub web link to a repository path at a specific ref (commit SHA or
     * branch), with an optional line anchor — used for security findings.
     */
    public function githubBlobUrl(string $path, ?string $ref = null, ?int $line = null): string
    {
        $resolvedRef = is_string($ref) && $ref !== '' ? $ref : (string) ($this->default_branch ?? 'HEAD');
        $url = "https://github.com/{$this->repository_owner}/{$this->repository_name}/blob/{$resolvedRef}/{$path}";

        return $line !== null && $line > 0 ? $url.'#L'.$line : $url;
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

    public function securityScans(): HasMany
    {
        return $this->hasMany(SecurityScan::class);
    }
}
