<?php

namespace Tests\Feature\Console;

use App\Enums\AiReviewStatus;
use App\Models\Plugin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsTarballs;
use Tests\TestCase;

class PluginsAiReviewTest extends TestCase
{
    use BuildsTarballs;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ai.key' => 'test-key']);
        config(['security_scan.enabled' => false]);
    }

    private function pluginWithFakes(string $sha = 'abc123'): Plugin
    {
        $plugin = Plugin::factory()->create([
            'repository_owner' => 'acme',
            'repository_name' => 'workspace-switcher',
            'latest_commit_sha' => $sha,
        ]);

        $tarballUrl = rtrim(config('services.github.codeload_url'), '/')
            ."/acme/workspace-switcher/tar.gz/{$sha}";

        Http::fake([
            $tarballUrl => Http::response($this->tarballFromDirectory(base_path('tests/Fixtures/security/malicious'))),
            'openrouter.ai/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'risk_level' => 'high',
                            'summary' => 'A risky plugin.',
                            'concerns' => ['Danger'],
                            'recommendation' => 'avoid',
                        ]),
                    ],
                ]],
            ]),
        ]);

        return $plugin;
    }

    public function test_command_reviews_plugins_and_persists_a_review(): void
    {
        $plugin = $this->pluginWithFakes('abc123');

        $this->artisan('plugins:ai-review', ['--ids' => (string) $plugin->id])
            ->expectsOutputToContain('Avoid')
            ->assertExitCode(0);

        $review = $plugin->aiReviews()->first();
        $this->assertNotNull($review);
        $this->assertSame(AiReviewStatus::Succeeded, $review->status);
        $this->assertSame('high', $review->risk_level?->value);
    }

    public function test_dry_run_lists_targets_without_reviewing(): void
    {
        $plugin = $this->pluginWithFakes('abc123');

        $this->artisan('plugins:ai-review', ['--ids' => (string) $plugin->id, '--dry-run' => true])
            ->expectsOutputToContain($plugin->slug)
            ->assertExitCode(0);

        $this->assertSame(0, $plugin->aiReviews()->count());
    }

    public function test_stale_only_targets_plugins_without_a_successful_review_at_their_commit(): void
    {
        $alreadyReviewed = Plugin::factory()->create(['latest_commit_sha' => 'current-sha']);
        $alreadyReviewed->aiReviews()->create([
            'commit_sha' => 'current-sha',
            'status' => AiReviewStatus::Succeeded,
            'provider' => 'openrouter',
            'model' => 'deepseek/deepseek-v4-flash-latest',
            'risk_level' => 'low',
            'recommendation' => 'install',
            'summary' => 'Fine.',
            'concerns' => [],
            'started_at' => now()->subDay(),
            'finished_at' => now()->subDay(),
        ]);

        $needsReview = Plugin::factory()->create(['latest_commit_sha' => 'another-sha']);

        $this->artisan('plugins:ai-review', ['--stale' => true, '--dry-run' => true])
            ->expectsOutputToContain($needsReview->slug)
            ->assertExitCode(0);
    }
}
