<?php

namespace Tests\Feature\Console;

use App\Enums\PluginStatus;
use App\Enums\SubmissionStatus;
use App\Models\Plugin;
use App\Models\PluginSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubmissionCommandsTest extends TestCase
{
    use RefreshDatabase;

    private function pendingSubmission(): PluginSubmission
    {
        $plugin = Plugin::factory()->create(['status' => PluginStatus::Pending]);

        return PluginSubmission::factory()->create([
            'repository_url' => "https://github.com/{$plugin->repository_owner}/{$plugin->repository_name}",
            'plugin_id' => $plugin->id,
            'status' => SubmissionStatus::Pending,
            'submitted_at' => now(),
        ]);
    }

    public function test_approve_publishes_the_linked_plugin(): void
    {
        $submission = $this->pendingSubmission();

        $this->artisan('submissions:approve', ['id' => (string) $submission->id])
            ->expectsOutputToContain("published {$submission->plugin->name}")
            ->assertSuccessful();

        $submission->refresh();
        $plugin = $submission->plugin;

        $this->assertSame(SubmissionStatus::Approved, $submission->status);
        $this->assertNotNull($submission->reviewed_at);
        $this->assertSame(PluginStatus::Published, $plugin->status);
        $this->assertNotNull($plugin->published_at);
    }

    public function test_approve_rejects_an_already_reviewed_submission(): void
    {
        $submission = $this->pendingSubmission();
        $submission->update(['status' => SubmissionStatus::Approved, 'reviewed_at' => now()]);

        $this->artisan('submissions:approve', ['id' => (string) $submission->id])
            ->expectsOutputToContain('Only a pending submission can be approved')
            ->assertFailed();
    }

    public function test_reject_marks_submission_rejected_and_settles_pending_plugin(): void
    {
        $submission = $this->pendingSubmission();

        $this->artisan('submissions:reject', [
            'id' => (string) $submission->id,
            'reason' => 'Does not contain a valid manifest',
        ])->expectsOutputToContain("Rejected submission #{$submission->id}")->assertSuccessful();

        $submission->refresh();
        $this->assertSame(SubmissionStatus::Rejected, $submission->status);
        $this->assertNotNull($submission->reviewed_at);
        $this->assertSame('Does not contain a valid manifest', $submission->failure_reason);
        $this->assertSame(PluginStatus::Rejected, $submission->plugin->status);
    }

    public function test_reject_does_not_touch_an_already_published_plugin(): void
    {
        $plugin = Plugin::factory()->published()->create();
        $submission = PluginSubmission::factory()->create([
            'repository_url' => "https://github.com/{$plugin->repository_owner}/{$plugin->repository_name}",
            'plugin_id' => $plugin->id,
            'status' => SubmissionStatus::Pending,
            'submitted_at' => now(),
        ]);

        $this->artisan('submissions:reject', ['id' => (string) $submission->id])->assertSuccessful();

        $plugin->refresh();
        $this->assertSame(PluginStatus::Published, $plugin->status);
    }

    public function test_list_shows_pending_submissions_by_default(): void
    {
        $this->pendingSubmission();

        $this->artisan('submissions:list')
            ->expectsOutputToContain('ID')
            ->expectsOutputToContain('pending')
            ->assertSuccessful();
    }

    public function test_list_reports_empty_state(): void
    {
        $this->artisan('submissions:list')
            ->expectsOutputToContain('There are no submissions awaiting review')
            ->assertSuccessful();
    }
}
