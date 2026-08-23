<?php

namespace App\Services\Plugins;

use App\Jobs\RefreshPlugin;
use App\Models\Plugin;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

class PluginVisitRefresher
{
    private const REFRESH_AFTER_MINUTES = 10;

    private const LOCK_MINUTES = 10;

    public const FAILURE_COOLDOWN_MINUTES = 5;

    public function refreshIfStale(Plugin $plugin): bool
    {
        if ($plugin->last_indexed_at !== null
            && $plugin->last_indexed_at->isAfter(now()->subMinutes(self::REFRESH_AFTER_MINUTES))) {
            return false;
        }

        if (Cache::has(self::failureCacheKey((int) $plugin->getKey()))) {
            return false;
        }

        $key = self::cacheKey((int) $plugin->getKey());
        $refreshToken = (string) Str::uuid();

        if (! Cache::add($key, $refreshToken, now()->addMinutes(self::LOCK_MINUTES))) {
            return true;
        }

        try {
            RefreshPlugin::dispatch((int) $plugin->getKey(), $refreshToken)->onConnection('deferred');
        } catch (Throwable $exception) {
            if (Cache::get($key) === $refreshToken) {
                Cache::forget($key);
            }

            throw $exception;
        }

        return true;
    }

    public function isRefreshing(Plugin $plugin): bool
    {
        return Cache::has(self::cacheKey((int) $plugin->getKey()));
    }

    public static function cacheKey(int $pluginId): string
    {
        return "plugins:refreshing:{$pluginId}";
    }

    public static function failureCacheKey(int $pluginId): string
    {
        return "plugins:refresh-failed:{$pluginId}";
    }
}
