<?php

namespace App\Providers;

use App\Security\DockerSandboxRunner;
use App\Security\LocalSandboxRunner;
use App\Security\Rules\CredentialAccessRule;
use App\Security\Rules\CurlPipeShRule;
use App\Security\Rules\DecodeAndExecuteRule;
use App\Security\Rules\DestructiveFilesystemRule;
use App\Security\Rules\EvalRule;
use App\Security\Rules\ExternalHostsRule;
use App\Security\Rules\ObfuscationRule;
use App\Security\Rules\PackageManagerRule;
use App\Security\Rules\PermissionOwnershipRule;
use App\Security\Rules\PersistenceRule;
use App\Security\Rules\ShellProfileRule;
use App\Security\Rules\SudoRule;
use App\Security\SandboxRunner;
use App\Security\ScanEngine;
use App\Services\Ai\AiClient;
use App\Services\Ai\AiReviewer;
use App\Services\Ai\OpenRouterClient;
use App\Services\Ai\RepositoryContentSampler;
use App\Services\GitHub\GitHubClient;
use App\Services\Markdown\MarkdownRenderer;
use App\Services\Security\SecurityScanner;
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

        $this->app->singleton(ScanEngine::class, function (): ScanEngine {
            $rules = [
                new CurlPipeShRule,
                new DecodeAndExecuteRule,
                new DestructiveFilesystemRule,
                new EvalRule,
                new CredentialAccessRule,
                new ExternalHostsRule,
                new ObfuscationRule,
                new PackageManagerRule,
                new PermissionOwnershipRule,
                new PersistenceRule,
                new ShellProfileRule,
                new SudoRule,
            ];

            return new ScanEngine(
                rules: $rules,
                maxFileSize: (int) config('security_scan.max_file_size'),
                maxFiles: (int) config('security_scan.max_files'),
            );
        });

        $this->app->singleton(SandboxRunner::class, function (): SandboxRunner {
            if (filter_var(config('security_scan.enabled'), FILTER_VALIDATE_BOOL)) {
                return new DockerSandboxRunner(
                    image: (string) config('security_scan.sandbox_image'),
                    containerRepoPath: (string) config('security_scan.sandbox_repo_path'),
                    hostRepoPath: config('security_scan.sandbox_host_repo_path'),
                );
            }

            return new LocalSandboxRunner($this->app->make(ScanEngine::class));
        });

        $this->app->singleton(RepositoryContentSampler::class, function (): RepositoryContentSampler {
            return new RepositoryContentSampler(
                maxFiles: (int) config('ai.max_sample_files'),
                maxLines: (int) config('ai.max_sample_lines'),
            );
        });

        $this->app->singleton(AiClient::class, function (): AiClient {
            return new OpenRouterClient(
                baseUrl: (string) config('ai.base_url'),
                key: (string) config('ai.key'),
                model: (string) config('ai.model'),
                timeout: (int) config('ai.timeout'),
            );
        });

        $this->app->singleton(AiReviewer::class, function (): AiReviewer {
            return new AiReviewer(
                scanner: $this->app->make(SecurityScanner::class),
                github: $this->app->make(GitHubClient::class),
                client: $this->app->make(AiClient::class),
                sampler: $this->app->make(RepositoryContentSampler::class),
                provider: (string) config('ai.provider'),
                model: (string) config('ai.model'),
                maxReadmeChars: (int) config('ai.max_readme_chars'),
            );
        });
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
