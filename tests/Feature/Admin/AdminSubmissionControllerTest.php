<?php

namespace Tests\Feature\Admin;

use App\Enums\PluginStatus;
use App\Enums\SubmissionStatus;
use App\Models\Plugin;
use App\Models\PluginSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSubmissionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_approving_a_submission_publishes_its_plugin(): void
    {
        $plugin = Plugin::factory()->create(['status' => PluginStatus::Pending]);
        $submission = PluginSubmission::factory()->create([
            'plugin_id' => $plugin->id,
            'status' => SubmissionStatus::Pending,
        ]);

        $this->post(route('admin.submissions.approve', $submission))
            ->assertRedirect();

        $this->assertDatabaseHas('plugin_submissions', [
            'id' => $submission->id,
            'status' => SubmissionStatus::Approved->value,
        ]);
        $this->assertNotNull($submission->fresh()->reviewed_at);

        $this->assertDatabaseHas('plugins', [
            'id' => $plugin->id,
            'status' => PluginStatus::Published->value,
        ]);
        $this->assertNotNull($plugin->fresh()->published_at);
    }

    public function test_rejecting_a_submission_marks_it_rejected_with_the_reason(): void
    {
        $submission = PluginSubmission::factory()->create([
            'status' => SubmissionStatus::Pending,
        ]);

        $this->post(route('admin.submissions.reject', $submission), ['reason' => 'Not a real plugin'])
            ->assertRedirect();

        $this->assertDatabaseHas('plugin_submissions', [
            'id' => $submission->id,
            'status' => SubmissionStatus::Rejected->value,
            'failure_reason' => 'Not a real plugin',
        ]);
    }

    public function test_approving_a_non_pending_submission_returns_an_error(): void
    {
        $submission = PluginSubmission::factory()->create([
            'status' => SubmissionStatus::Approved,
        ]);

        $this->post(route('admin.submissions.approve', $submission))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_submission_index_can_be_filtered_by_status(): void
    {
        PluginSubmission::factory()->create([
            'repository_url' => 'https://github.com/acme/pending-one',
            'status' => SubmissionStatus::Pending,
        ]);
        PluginSubmission::factory()->create([
            'repository_url' => 'https://github.com/acme/approved-one',
            'status' => SubmissionStatus::Approved,
        ]);

        $response = $this->get(route('admin.submissions.index', ['status' => 'approved']))
            ->assertOk();

        $content = $response->getContent();
        $this->assertStringContainsString('https://github.com/acme/approved-one', $content);
        $this->assertStringNotContainsString('https://github.com/acme/pending-one', $content);
    }
}
