<?php

namespace Tests\Feature\Admin;

use App\Enums\SubmissionStatus;
use App\Models\Plugin;
use App\Models\PluginSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsTarballs;
use Tests\TestCase;

class AdminSubmissionReviewTest extends TestCase
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

    private function submissionWithPlugin(): PluginSubmission
    {
        $plugin = Plugin::factory()->create([
            'repository_owner' => 'acme',
            'repository_name' => 'workspace-switcher',
            'latest_commit_sha' => 'abc123',
        ]);

        return PluginSubmission::factory()->create([
            'repository_url' => $plugin->repository_url,
            'plugin_id' => $plugin->id,
            'status' => SubmissionStatus::Pending,
        ]);
    }

    private function fakeTarball(string $sha, string $fixture): void
    {
        $url = rtrim(config('services.github.codeload_url'), '/')
            .'/acme/workspace-switcher/tar.gz/'.$sha;

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
                            'risk_level' => 'medium',
                            'summary' => 'Review recommended before publishing.',
                            'concerns' => ['Check install.sh'],
                            'recommendation' => 'review',
                        ]),
                    ],
                ]],
            ]),
        ]);
    }

    public function test_show_page_displays_the_review_panel(): void
    {
        $submission = $this->submissionWithPlugin();

        $this->get(route('admin.submissions.show', $submission))
            ->assertOk()
            ->assertSee('Security & AI review')
            ->assertSee('Run scan')
            ->assertSee('Run AI review');
    }

    public function test_scan_action_runs_on_the_submissions_plugin(): void
    {
        $submission = $this->submissionWithPlugin();
        $this->fakeTarball('abc123', 'malicious');

        $this->post(route('admin.submissions.scan', $submission))
            ->assertRedirect()
            ->assertSessionHas('status', function (string $status): bool {
                return str_contains($status, 'Scan complete.');
            });

        $scan = $submission->plugin->securityScans()->first();
        $this->assertNotNull($scan);
        $this->assertSame('high', $scan->risk_level);
    }

    public function test_ai_review_action_runs_on_the_submissions_plugin(): void
    {
        $submission = $this->submissionWithPlugin();
        $this->fakeTarball('abc123', 'malicious');
        $this->fakeOpenRouter();

        $this->post(route('admin.submissions.ai-review', $submission))
            ->assertRedirect()
            ->assertSessionHas('status', function (string $status): bool {
                return str_contains($status, 'AI review complete.');
            });

        $review = $submission->plugin->aiReviews()->first();
        $this->assertNotNull($review);
        $this->assertSame('medium', $review->risk_level?->value);
    }

    public function test_a_submission_without_a_plugin_cannot_be_scanned(): void
    {
        $submission = PluginSubmission::factory()->create([
            'plugin_id' => null,
            'status' => SubmissionStatus::Failed,
        ]);

        $this->get(route('admin.submissions.show', $submission))
            ->assertOk()
            ->assertSee('cannot be scanned or AI-reviewed');

        $this->post(route('admin.submissions.scan', $submission))
            ->assertRedirect()
            ->assertSessionHas('error');
    }
}
