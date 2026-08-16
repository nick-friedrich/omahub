<?php

namespace App\Providers;

use App\Services\Markdown\MarkdownRenderer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(MarkdownRenderer::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('submissions', function (object $job) {
            return Limit::perMinute(3)->by($job->ip());
        });
    }
}
