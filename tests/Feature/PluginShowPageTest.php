<?php

namespace Tests\Feature;

use App\Enums\AiReviewStatus;
use App\Enums\PluginStatus;
use App\Exceptions\GitHubRequestException;
use App\Jobs\RefreshPlugin;
use App\Models\Plugin;
use App\Services\Plugins\GitHubRepositoryImporter;
use App\Services\Plugins\PluginVisitRefresher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class PluginShowPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_detail_page_renders_tables_and_rewrites_relative_images(): void
    {
        $plugin = Plugin::factory()->published()->create([
            'readme_markdown' => "# Intro\n\n![preview](preview.png)\n\n| A | B |\n| --- | --- |\n| 1 | 2 |",
        ]);

        $this->get("/plugins/{$plugin->slug}")
            ->assertOk()
            ->assertSee('<h1>Intro</h1>', escape: false)
            ->assertSee('<table>', escape: false)
            ->assertSee(
                "https://raw.githubusercontent.com/{$plugin->repository_owner}/{$plugin->repository_name}/main/preview.png",
                escape: false,
            );
    }

    public function test_detail_page_serves_an_escaped_readme_without_a_repository_identity(): void
    {
        $plugin = Plugin::factory()->published()->create([
            'default_branch' => null,
            'readme_markdown' => '![x](local.png) <script>bad()</script>',
        ]);

        $this->get("/plugins/{$plugin->slug}")
            ->assertOk()
            ->assertSee('src="local.png"', escape: false)
            ->assertDontSee('<script>', escape: false);
    }

    public function test_detail_page_uses_the_first_readme_image_for_social_previews(): void
    {
        $plugin = Plugin::factory()->published()->create([
            'name' => 'Window Switcher',
            'description' => 'A faster way to switch windows.',
            'readme_markdown' => "# Intro\n\n![preview](screenshots/preview.png)",
        ]);

        $imageUrl = "https://raw.githubusercontent.com/{$plugin->repository_owner}/{$plugin->repository_name}/main/screenshots/preview.png";

        $this->get("/plugins/{$plugin->slug}")
            ->assertOk()
            ->assertSee('<meta property="og:title" content="Window Switcher · Omahub">', escape: false)
            ->assertSee('<meta property="og:description" content="A faster way to switch windows.">', escape: false)
            ->assertSee('<meta property="og:image" content="'.$imageUrl.'">', escape: false)
            ->assertSee('<meta name="twitter:image" content="'.$imageUrl.'">', escape: false);
    }

    public function test_pages_without_a_plugin_preview_use_the_general_social_image(): void
    {
        $plugin = Plugin::factory()->published()->create([
            'readme_markdown' => '# No screenshots here',
        ]);

        $this->get("/plugins/{$plugin->slug}")
            ->assertOk()
            ->assertSee('<meta property="og:image" content="'.asset('og-image.png').'">', escape: false)
            ->assertSee('<meta name="twitter:card" content="summary_large_image">', escape: false);
    }

    public function test_visiting_a_stale_plugin_dispatches_one_deferred_refresh(): void
    {
        Queue::fake();

        $plugin = Plugin::factory()->published()->create([
            'last_indexed_at' => now()->subMinutes(11),
        ]);

        $this->get(route('plugins.show', $plugin))
            ->assertOk()
            ->assertSee('Checking GitHub…');

        $this->get(route('plugins.show', $plugin))->assertOk();

        Queue::assertPushed(RefreshPlugin::class, 1);
        Queue::assertPushed(RefreshPlugin::class, fn (RefreshPlugin $job): bool => $job->pluginId === $plugin->id && $job->connection === 'deferred'
        );
    }

    public function test_visiting_a_recently_indexed_plugin_does_not_dispatch_a_refresh(): void
    {
        Queue::fake();

        $plugin = Plugin::factory()->published()->create([
            'last_indexed_at' => now()->subMinutes(9),
        ]);

        $this->get(route('plugins.show', $plugin))
            ->assertOk()
            ->assertDontSee('Checking GitHub…');

        Queue::assertNothingPushed();
    }

    public function test_a_removed_plugin_page_shows_a_notice_and_no_install_command(): void
    {
        $plugin = Plugin::factory()->repositoryRemoved()->create();

        $this->get(route('plugins.show', $plugin))
            ->assertOk()
            ->assertSee('no longer available', escape: false)
            ->assertDontSee('omarchy plugin add', escape: false);
    }

    public function test_a_removed_plugin_does_not_dispatch_a_visit_refresh(): void
    {
        Queue::fake();

        $plugin = Plugin::factory()->repositoryRemoved()->create([
            'last_indexed_at' => now()->subHour(),
        ]);

        $this->get(route('plugins.show', $plugin))
            ->assertOk()
            ->assertDontSee('Checking GitHub…');

        Queue::assertNothingPushed();
    }

    public function test_detail_page_shows_the_pending_ai_advisory_review_state(): void
    {
        $plugin = Plugin::factory()->published()->create();

        $this->get(route('plugins.show', $plugin))
            ->assertOk()
            ->assertSee('AI advisory review')
            ->assertSee('not been given an AI review yet', escape: false);
    }

    public function test_detail_page_shows_a_successful_ai_review_with_summary_and_concerns(): void
    {
        $plugin = Plugin::factory()->published()->create(['latest_commit_sha' => 'abc123']);

        $plugin->aiReviews()->create([
            'commit_sha' => 'abc123',
            'status' => AiReviewStatus::Succeeded,
            'provider' => 'openrouter',
            'model' => '~deepseek/deepseek-v4-flash-latest',
            'risk_level' => 'medium',
            'recommendation' => 'review',
            'summary' => 'The install script downloads extra code and touches the user profile.',
            'concerns' => ['Downloads from an external host', 'Modifies ~/.bashrc'],
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ]);

        $this->get(route('plugins.show', $plugin))
            ->assertOk()
            ->assertSee('AI advisory review')
            ->assertSee('Review recommended', escape: false)
            ->assertSee('Downloads from an external host', escape: false)
            ->assertSee('Modifies ~/.bashrc', escape: false)
            ->assertSee('How this check works')
            ->assertSee('AI advisory only — automated analysis, not a security guarantee.', escape: false);
    }

    public function test_detail_page_marks_an_ai_review_stale_when_there_is_a_newer_commit(): void
    {
        $plugin = Plugin::factory()->published()->create(['latest_commit_sha' => 'newer123']);

        $plugin->aiReviews()->create([
            'commit_sha' => 'older456',
            'status' => AiReviewStatus::Succeeded,
            'provider' => 'openrouter',
            'model' => '~deepseek/deepseek-v4-flash-latest',
            'risk_level' => 'low',
            'recommendation' => 'install',
            'summary' => 'Fine.',
            'concerns' => [],
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ]);

        $this->get(route('plugins.show', $plugin))
            ->assertOk()
            ->assertSee('Newer commit newer12 not yet reviewed', escape: false);
    }

    public function test_refresh_status_reports_a_removed_plugin(): void
    {
        $plugin = Plugin::factory()->repositoryRemoved()->create();

        $this->getJson(route('plugins.refresh-status', $plugin))
            ->assertOk()
            ->assertJson([
                'refreshing' => false,
                'removed' => true,
            ]);
    }

    public function test_refresh_status_reports_when_indexing_finishes(): void
    {
        $plugin = Plugin::factory()->published()->create([
            'last_indexed_at' => now()->subHour(),
        ]);
        Cache::put(PluginVisitRefresher::cacheKey($plugin->id), true, now()->addMinutes(5));

        $this->getJson(route('plugins.refresh-status', $plugin))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJson([
                'refreshing' => true,
                'indexed_at' => $plugin->last_indexed_at->toISOString(),
                'commit_sha' => $plugin->latest_commit_sha,
            ]);

        $plugin->update(['last_indexed_at' => now()]);
        Cache::forget(PluginVisitRefresher::cacheKey($plugin->id));

        $this->getJson(route('plugins.refresh-status', $plugin))
            ->assertOk()
            ->assertJson([
                'refreshing' => false,
                'indexed_at' => $plugin->fresh()->last_indexed_at->toISOString(),
            ]);
    }

    public function test_refresh_job_uses_the_etag_and_releases_the_visit_lock(): void
    {
        $plugin = Plugin::factory()->published()->create([
            'github_etag' => 'W/"plugin-etag"',
        ]);

        $importer = $this->mock(GitHubRepositoryImporter::class);
        $importer->shouldReceive('import')
            ->once()
            ->with(
                "https://github.com/{$plugin->repository_owner}/{$plugin->repository_name}",
                'W/"plugin-etag"',
            )
            ->andReturn($plugin);

        $refreshToken = 'refresh-token';
        Cache::put(PluginVisitRefresher::cacheKey($plugin->id), $refreshToken, now()->addMinutes(5));

        (new RefreshPlugin($plugin->id, $refreshToken))->handle($importer);

        $this->assertFalse(Cache::has(PluginVisitRefresher::cacheKey($plugin->id)));
    }

    public function test_failed_refreshes_have_a_cooldown_before_another_visit_retries(): void
    {
        Queue::fake();

        $plugin = Plugin::factory()->published()->create([
            'last_indexed_at' => now()->subHour(),
        ]);
        Cache::put(PluginVisitRefresher::failureCacheKey($plugin->id), true, now()->addMinutes(5));

        $this->get(route('plugins.show', $plugin))
            ->assertOk()
            ->assertDontSee('Checking GitHub…');

        Queue::assertNothingPushed();
    }

    public function test_refresh_job_sets_the_failure_cooldown_when_github_fails(): void
    {
        $plugin = Plugin::factory()->published()->create();
        $refreshToken = 'refresh-token';
        Cache::put(PluginVisitRefresher::cacheKey($plugin->id), $refreshToken, now()->addMinutes(5));

        $importer = $this->mock(GitHubRepositoryImporter::class);
        $importer->shouldReceive('import')->once()->andThrow(new RuntimeException('GitHub unavailable'));

        try {
            (new RefreshPlugin($plugin->id, $refreshToken))->handle($importer);
        } catch (RuntimeException) {
            // Deferred refresh failures are reported by Laravel after the response.
        }

        $this->assertFalse(Cache::has(PluginVisitRefresher::cacheKey($plugin->id)));
        $this->assertTrue(Cache::has(PluginVisitRefresher::failureCacheKey($plugin->id)));
    }

    public function test_refresh_job_unpublishes_a_plugin_whose_repository_is_gone(): void
    {
        $plugin = Plugin::factory()->published()->create();
        $refreshToken = 'refresh-token';
        Cache::put(PluginVisitRefresher::cacheKey($plugin->id), $refreshToken, now()->addMinutes(5));

        $importer = $this->mock(GitHubRepositoryImporter::class);
        $importer->shouldReceive('import')->once()
            ->andThrow(GitHubRequestException::repositoryNotFound());

        (new RefreshPlugin($plugin->id, $refreshToken))->handle($importer);

        $plugin->refresh();
        $this->assertSame(PluginStatus::Archived, $plugin->status);
        $this->assertNotNull($plugin->getRawOriginal('repository_removed_at'));
        $this->assertFalse(Cache::has(PluginVisitRefresher::cacheKey($plugin->id)));
        $this->assertFalse(Cache::has(PluginVisitRefresher::failureCacheKey($plugin->id)));
    }
}
