<?php

namespace Tests\Feature\Admin;

use App\Models\Plugin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsTarballs;
use Tests\TestCase;

class AdminAiReviewTest extends TestCase
{
    use BuildsTarballs;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->admin()->create());
        config(['ai.key' => 'test-key']);
        config(['security_scan.enabled' => false]);
    }

    private function fakeTarball(Plugin $plugin, string $sha, string $fixture): void
    {
        $url = rtrim(config('services.github.codeload_url'), '/')
            ."/{$plugin->repository_owner}/{$plugin->repository_name}/tar.gz/{$sha}";

        Http::fake([
            $url => Http::response($this->tarballFromDirectory(base_path("tests/Fixtures/security/{$fixture}"))),
        ]);
    }

    private function fakeOpenRouter(): void
    {
        Http::fake([
            'openrouter.ai/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'risk_level' => 'low',
                            'summary' => 'A clean plugin. No danger found.',
                            'concerns' => [],
                            'recommendation' => 'install',
                        ]),
                    ],
                ]],
            ]),
        ]);
    }

    public function test_edit_page_shows_the_ai_review_panel_when_none_exists(): void
    {
        $plugin = Plugin::factory()->create(['latest_commit_sha' => 'abc123']);

        $this->get(route('admin.plugins.edit', $plugin))
            ->assertOk()
            ->assertSee('AI advisory review')
            ->assertSee('Run AI review');
    }

    public function test_ai_review_action_runs_and_stores_a_review(): void
    {
        $plugin = Plugin::factory()->create([
            'repository_owner' => 'acme',
            'repository_name' => 'workspace-switcher',
            'latest_commit_sha' => 'abc123',
        ]);
        $this->fakeTarball($plugin, 'abc123', 'clean');
        $this->fakeOpenRouter();

        $this->post(route('admin.plugins.ai-review', $plugin))
            ->assertRedirect()
            ->assertSessionHas('status', function (string $status): bool {
                return str_contains($status, 'AI review complete.');
            });

        $review = $plugin->aiReviews()->first();
        $this->assertNotNull($review);
        $this->assertSame('succeeded', $review->status->value);
        $this->assertSame('low', $review->risk_level?->value);
    }

    public function test_ai_review_action_records_a_failed_review_on_provider_error(): void
    {
        $plugin = Plugin::factory()->create([
            'repository_owner' => 'acme',
            'repository_name' => 'workspace-switcher',
            'latest_commit_sha' => 'abc123',
        ]);
        $this->fakeTarball($plugin, 'abc123', 'clean');

        Http::fake([
            'openrouter.ai/*' => Http::response('Server Error', 500),
        ]);

        $this->post(route('admin.plugins.ai-review', $plugin))
            ->assertRedirect()
            ->assertSessionHas('error');

        $review = $plugin->aiReviews()->first();
        $this->assertNotNull($review);
        $this->assertSame('failed', $review->status->value);
    }
}
