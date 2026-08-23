<?php

namespace App\Jobs;

use App\Models\Plugin;
use App\Services\Plugins\GitHubRepositoryImporter;
use App\Services\Plugins\PluginVisitRefresher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Throwable;

class RefreshPlugin implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $pluginId,
        public readonly string $refreshToken,
    ) {}

    public function handle(GitHubRepositoryImporter $importer): void
    {
        try {
            $plugin = Plugin::query()->find($this->pluginId);

            if ($plugin === null) {
                return;
            }

            $etag = is_string($plugin->github_etag) ? $plugin->github_etag : null;

            $importer->import(
                "https://github.com/{$plugin->repository_owner}/{$plugin->repository_name}",
                filled($etag) ? $etag : null,
            );
        } catch (Throwable $exception) {
            Cache::put(
                PluginVisitRefresher::failureCacheKey($this->pluginId),
                true,
                now()->addMinutes(PluginVisitRefresher::FAILURE_COOLDOWN_MINUTES),
            );

            throw $exception;
        } finally {
            $key = PluginVisitRefresher::cacheKey($this->pluginId);

            if (Cache::get($key) === $this->refreshToken) {
                Cache::forget($key);
            }
        }
    }
}
