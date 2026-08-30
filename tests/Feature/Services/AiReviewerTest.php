<?php

namespace Tests\Feature\Services;

use App\Enums\AiReviewStatus;
use App\Enums\PluginStatus;
use App\Enums\SecurityScanStatus;
use App\Models\Plugin;
use App\Services\Ai\AiReviewer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsTarballs;
use Tests\TestCase;

class AiReviewerTest extends TestCase
{
    use BuildsTarballs;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ai.key' => 'test-key']);
        // Scan in-process (no Docker needed) so the AI tests exercise the full
        // reviewer flow anywhere.
        config(['security_scan.enabled' => false]);
    }

    private function createPlugin(string $sha = 'abc123', bool $published = false): Plugin
    {
        $plugin = Plugin::factory()->create([
            'repository_owner' => 'acme',
            'repository_name' => 'workspace-switcher',
            'latest_commit_sha' => $sha,
        ]);

        if ($published) {
            $plugin->forceFill([
                'status' => PluginStatus::Published,
                'published_at' => now(),
            ])->save();
        }

        return $plugin->refresh();
    }

    private function tarballUrl(string $sha): string
    {
        return rtrim(config('services.github.codeload_url'), '/')
            .'/acme/workspace-switcher/tar.gz/'.$sha;
    }

    private function payload(string $risk, string $recommendation, string $summary, array $concerns = []): string
    {
        return json_encode([
            'risk_level' => $risk,
            'summary' => $summary,
            'concerns' => $concerns,
            'recommendation' => $recommendation,
        ]);
    }

    /**
     * Stub every outbound call in a single Http::fake so later calls don't
     * clobber earlier fakes. $tarballs maps sha => fixture name; $aiPayloads is
     * an ordered list of OpenAI-style JSON payloads to replay.
     *
     * @param  array<string, string>  $tarballs
     * @param  array<int, array{risk: string, recommendation: string, summary: string, concerns?: array<int, string>}>  $aiPayloads
     */
    private function stubHttp(array $tarballs, array $aiPayloads): void
    {
        $stubs = [];

        foreach ($tarballs as $sha => $fixture) {
            $stubs[$this->tarballUrl($sha)] = Http::response(
                $this->tarballFromDirectory(base_path("tests/Fixtures/security/{$fixture}")),
            );
        }

        $responses = array_map(
            fn (array $p): array => ['choices' => [['message' => ['content' => $this->payload(
                $p['risk'],
                $p['recommendation'],
                $p['summary'],
                $p['concerns'] ?? [],
            )]]]],
            $aiPayloads,
        );

        $stubs['openrouter.ai/*'] = count($responses) === 1
            ? Http::response($responses[0])
            : Http::sequence($responses);

        Http::fake($stubs);
    }

    public function test_it_runs_a_review_on_top_of_the_deterministic_scan_and_persists_it(): void
    {
        $plugin = $this->createPlugin('abc123');
        $this->stubHttp(['abc123' => 'malicious'], [
            ['risk' => 'high', 'recommendation' => 'review', 'summary' => 'The install script downloads and executes an obfuscated payload, then persists itself.', 'concerns' => ['Obfuscated call in setup.sh', 'Persistence via cron']],
        ]);

        $review = app(AiReviewer::class)->review($plugin);

        $this->assertSame(AiReviewStatus::Succeeded, $review->status);
        $this->assertSame('high', $review->risk_level?->value);
        $this->assertSame('review', $review->recommendation?->value);
        $this->assertSame('abc123', $review->commit_sha);
        $this->assertStringContainsString('obfuscated payload', (string) $review->summary);
        $this->assertSame(['Obfuscated call in setup.sh', 'Persistence via cron'], $review->concerns);
        $this->assertNotNull($review->raw_response);
        $this->assertSame('openrouter', $review->provider);
        $this->assertSame(config('ai.model'), $review->model);

        // The review depends on the deterministic scan: one for the same commit.
        $scan = $plugin->securityScans()->first();
        $this->assertNotNull($scan);
        $this->assertSame(SecurityScanStatus::Succeeded, $scan->status);
        $this->assertSame($scan->id, $review->security_scan_id);
        $this->assertSame('high', $scan->risk_level);
    }

    public function test_reviewing_the_same_commit_is_idempotent(): void
    {
        $plugin = $this->createPlugin('abc123');
        $this->stubHttp(['abc123' => 'malicious'], [
            ['risk' => 'high', 'recommendation' => 'review', 'summary' => 'Risky.'],
        ]);

        $first = app(AiReviewer::class)->review($plugin);
        $second = app(AiReviewer::class)->review($plugin);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, $plugin->aiReviews()->count());
        $this->assertSame(1, $plugin->securityScans()->count());
    }

    public function test_a_new_commit_triggers_a_fresh_review(): void
    {
        $plugin = $this->createPlugin('abc123');
        $this->stubHttp(['abc123' => 'clean', 'def456' => 'clean'], [
            ['risk' => 'low', 'recommendation' => 'install', 'summary' => 'Safe for sha one.'],
            ['risk' => 'medium', 'recommendation' => 'review', 'summary' => 'Second commit looks different.'],
        ]);

        app(AiReviewer::class)->review($plugin);

        $plugin->update(['latest_commit_sha' => 'def456']);

        $review = app(AiReviewer::class)->review($plugin);

        $this->assertSame('def456', $review->commit_sha);
        $this->assertSame('medium', $review->risk_level?->value);
        $this->assertSame('review', $review->recommendation?->value);
        $this->assertSame(2, $plugin->aiReviews()->count());
        $this->assertSame(2, $plugin->securityScans()->count());
    }

    public function test_a_failed_ai_call_records_a_failed_review(): void
    {
        $plugin = $this->createPlugin('abc123');

        $stubs = [$this->tarballUrl('abc123') => Http::response($this->tarballFromDirectory(base_path('tests/Fixtures/security/malicious')))];
        $stubs['openrouter.ai/*'] = Http::response('Server Error', 500);
        Http::fake($stubs);

        try {
            app(AiReviewer::class)->review($plugin);
            $this->fail('Review should have thrown for a failed AI call.');
        } catch (\Throwable) {
            // expected
        }

        $review = $plugin->aiReviews()->first();
        $this->assertNotNull($review);
        $this->assertSame(AiReviewStatus::Failed, $review->status);
        $this->assertNull($review->risk_level);
        $this->assertNull($review->summary);
    }

    public function test_an_invalid_model_response_is_recorded_as_failed(): void
    {
        $plugin = $this->createPlugin('abc123');

        $stubs = [$this->tarballUrl('abc123') => Http::response($this->tarballFromDirectory(base_path('tests/Fixtures/security/malicious')))];
        $stubs['openrouter.ai/*'] = Http::response([
            'choices' => [['message' => ['content' => '{"risk_level":"bogus"}']]],
        ]);
        Http::fake($stubs);

        try {
            app(AiReviewer::class)->review($plugin);
            $this->fail('Review should have thrown for an invalid model response.');
        } catch (\Throwable) {
            // expected
        }

        $review = $plugin->aiReviews()->first();
        $this->assertNotNull($review);
        $this->assertSame(AiReviewStatus::Failed, $review->status);
    }

    public function test_a_high_critical_risk_with_avoid_recommendation_auto_unpublishes_a_published_plugin(): void
    {
        $plugin = $this->createPlugin('abc123', true);
        $this->stubHttp(['abc123' => 'malicious'], [
            ['risk' => 'high', 'recommendation' => 'avoid', 'summary' => 'Downloads and executes an obfuscated payload.', 'concerns' => ['Obfuscated call in setup.sh']],
        ]);

        app(AiReviewer::class)->review($plugin);

        $plugin->refresh();
        $this->assertTrue($plugin->isAiUnpublished());
        $this->assertSame(PluginStatus::Archived, $plugin->status);
        // published_at is preserved so the plugin can be re-published manually.
        $this->assertNotNull($plugin->published_at);
    }

    public function test_critical_risk_with_avoid_auto_unpublishes(): void
    {
        $plugin = $this->createPlugin('abc123', true);
        $this->stubHttp(['abc123' => 'malicious'], [
            ['risk' => 'critical', 'recommendation' => 'avoid', 'summary' => 'Wipes the system.', 'concerns' => ['rm -rf in postinstall']],
        ]);

        app(AiReviewer::class)->review($plugin);

        $plugin->refresh();
        $this->assertTrue($plugin->isAiUnpublished());
        $this->assertSame(PluginStatus::Archived, $plugin->status);
    }

    public function test_high_risk_without_avoid_recommendation_does_not_unpublish(): void
    {
        $plugin = $this->createPlugin('abc123', true);
        $this->stubHttp(['abc123' => 'malicious'], [
            ['risk' => 'high', 'recommendation' => 'review', 'summary' => 'Concerning but not clearly avoid.', 'concerns' => ['Something odd']],
        ]);

        app(AiReviewer::class)->review($plugin);

        $plugin->refresh();
        $this->assertFalse($plugin->isAiUnpublished());
        $this->assertSame(PluginStatus::Published, $plugin->status);
    }

    public function test_avoid_recommendation_without_high_critical_risk_does_not_unpublish(): void
    {
        $plugin = $this->createPlugin('abc123', true);
        $this->stubHttp(['abc123' => 'malicious'], [
            ['risk' => 'medium', 'recommendation' => 'avoid', 'summary' => 'Avoid for now.', 'concerns' => []],
        ]);

        app(AiReviewer::class)->review($plugin);

        $plugin->refresh();
        $this->assertFalse($plugin->isAiUnpublished());
        $this->assertSame(PluginStatus::Published, $plugin->status);
    }

    public function test_a_clean_new_commit_reviews_but_does_not_auto_republish(): void
    {
        $plugin = $this->createPlugin('bad123', true);
        $this->stubHttp(['bad123' => 'malicious', 'good456' => 'clean'], [
            ['risk' => 'high', 'recommendation' => 'avoid', 'summary' => 'Bad commit.', 'concerns' => []],
            ['risk' => 'low', 'recommendation' => 'install', 'summary' => 'Good commit.', 'concerns' => []],
        ]);

        // Auto-unpublish on the bad commit.
        app(AiReviewer::class)->review($plugin);
        $plugin->refresh();
        $this->assertTrue($plugin->isAiUnpublished());
        $this->assertSame(PluginStatus::Archived, $plugin->status);

        // A later clean commit is reviewed but does NOT auto-republish.
        $plugin->forceFill(['latest_commit_sha' => 'good456'])->save();

        $review = app(AiReviewer::class)->review($plugin);
        $plugin->refresh();

        $this->assertSame('good456', $review->commit_sha);
        $this->assertSame('low', $review->risk_level?->value);
        $this->assertSame(PluginStatus::Archived, $plugin->status);
        $this->assertTrue($plugin->isAiUnpublished());
    }
}
