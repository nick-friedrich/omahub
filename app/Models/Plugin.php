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
use Illuminate\Database\Eloquent\Relations\HasOne;

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
    'github_etag',
    'stars_count',
    'forks_count',
    'open_issues_count',
    'is_archived',
    'repository_removed_at',
    'ai_unpublished_at',
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
            'repository_removed_at' => 'datetime',
            'ai_unpublished_at' => 'datetime',
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

    public function isRepositoryRemoved(): bool
    {
        return $this->repository_removed_at !== null;
    }

    /**
     * Whether the plugin was auto-unpublished because an AI advisory review of
     * its latest commit rated it high/critical risk with an "avoid"
     * recommendation. Restoration is always manual (an admin re-publishes).
     */
    public function isAiUnpublished(): bool
    {
        return $this->ai_unpublished_at !== null;
    }

    public function markAiUnpublished(): void
    {
        $this->forceFill([
            'status' => PluginStatus::Archived,
            'ai_unpublished_at' => now(),
        ])->save();
    }

    public function markRepositoryRemoved(): void
    {
        $this->forceFill([
            'status' => PluginStatus::Archived,
            'repository_removed_at' => now(),
        ])->save();
    }

    public function clearRepositoryRemoved(): void
    {
        $this->forceFill([
            'repository_removed_at' => null,
            // Restore to Published when the plugin had a public listing before
            // the repository disappeared (published_at is preserved on removal).
            'status' => $this->published_at !== null ? PluginStatus::Published : PluginStatus::Pending,
        ])->save();
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

    /** @return BelongsToMany<Category, $this> */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    /** @return BelongsToMany<Tag, $this> */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /** @return HasMany<PluginSubmission, $this> */
    public function submissions(): HasMany
    {
        return $this->hasMany(PluginSubmission::class);
    }

    /** @return HasMany<SecurityScan, $this> */
    public function securityScans(): HasMany
    {
        return $this->hasMany(SecurityScan::class);
    }

    /**
     * The most recently created security scan for this plugin.
     *
     * @return HasOne<SecurityScan, $this>
     */
    public function latestSecurityScan(): HasOne
    {
        return $this->hasOne(SecurityScan::class)->latestOfMany();
    }

    /** @return HasMany<AiReview, $this> */
    public function aiReviews(): HasMany
    {
        return $this->hasMany(AiReview::class);
    }

    /**
     * The most recently created AI review for this plugin.
     *
     * @return HasOne<AiReview, $this>
     */
    public function latestAiReview(): HasOne
    {
        return $this->hasOne(AiReview::class)->latestOfMany();
    }
}
