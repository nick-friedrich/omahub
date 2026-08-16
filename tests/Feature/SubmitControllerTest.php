<?php

namespace Tests\Feature;

use App\Enums\PluginStatus;
use App\Enums\SubmissionStatus;
use App\Models\Plugin;
use App\Models\PluginSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\FakesGitHub;
use Tests\TestCase;

class SubmitControllerTest extends TestCase
{
    use FakesGitHub;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // The in-memory cache is shared across tests in a single process, so reset
        // the per-minute submission limiter between tests.
        Cache::flush();
    }

    public function test_submit_form_is_displayed(): void
    {
        $this->get(route('submit'))
            ->assertOk()
            ->assertSee('Submit a plugin')
            ->assertSee(route('submit.store'), escape: false)
            ->assertSee('name="repository_url"', escape: false);
    }

    public function test_valid_submission_creates_a_pending_submission(): void
    {
        $this->fakeGitHub();

        $this->submit('https://github.com/acme/workspace-switcher')
            ->assertRedirect(route('submit'))
            ->assertSessionHas('status', 'pending');

        $this->assertDatabaseCount('plugins', 1);
        $this->assertDatabaseCount('plugin_submissions', 1);

        $this->assertDatabaseHas('plugin_submissions', [
            'repository_url' => 'https://github.com/acme/workspace-switcher',
            'status' => SubmissionStatus::Pending->value,
        ]);

        $plugin = Plugin::firstOrFail();
        $this->assertSame(PluginStatus::Pending, $plugin->status);
        $this->assertNull($plugin->published_at);
        $this->assertSame($plugin->id, PluginSubmission::query()->firstOrFail()->plugin_id);
    }

    public function test_a_submitted_plugin_is_not_publicly_visible_until_approved(): void
    {
        $this->fakeGitHub();

        $this->submit('https://github.com/acme/workspace-switcher');

        // Pending plugins must not appear on public pages.
        $this->get(route('plugins.index'))->assertDontSee('Workspace Switcher');
    }

    public function test_invalid_url_is_rejected_without_a_submission(): void
    {
        $this->submit('https://example.com/pluginswidget')
            ->assertSessionHasErrors('repository_url');

        $this->assertDatabaseCount('plugin_submissions', 0);
        $this->assertDatabaseCount('plugins', 0);
    }

    public function test_duplicate_pending_submission_is_rejected(): void
    {
        $this->fakeGitHub();

        $this->submit('https://github.com/acme/workspace-switcher');

        // A second submission for the same repository while the first is pending.
        $this->submit('https://github.com/acme/workspace-switcher')
            ->assertSessionHasErrors('repository_url');

        $this->assertDatabaseCount('plugin_submissions', 1);
    }

    public function test_failed_import_records_a_failed_submission_without_leaking_details(): void
    {
        Http::fake(['*' => Http::response('', 404)]);

        $this->submit('https://github.com/acme/workspace-switcher')
            ->assertRedirect(route('submit'))
            ->assertSessionHas('status', 'import_failed');

        $this->assertDatabaseCount('plugins', 0);
        $this->assertDatabaseCount('plugin_submissions', 1);

        $submission = PluginSubmission::query()->firstOrFail();
        $this->assertSame(SubmissionStatus::Failed, $submission->status);
        $this->assertInstanceOf(\DateTimeInterface::class, $submission->submitted_at);
        $this->assertIsString($submission->failure_reason);
    }

    public function test_honeypot_field_is_ignored_and_no_submission_is_created(): void
    {
        $this->fakeGitHub();

        $this->submit('https://github.com/acme/workspace-switcher', ['website' => 'https://spam.example.com'])
            ->assertRedirect(route('submit'))
            ->assertSessionHas('status', 'received');

        $this->assertDatabaseCount('plugin_submissions', 0);
        $this->assertDatabaseCount('plugins', 0);
    }

    public function test_submissions_are_rate_limited(): void
    {
        $this->fakeGitHub();

        // The limiter allows three submissions per minute for this IP.
        for ($i = 0; $i < 3; $i++) {
            $this->submit('https://github.com/acme/workspace-switcher');
            $this->fakeGitHub(); // re-seed fakes for the next iteration
            PluginSubmission::query()->delete();
            Plugin::query()->delete();
        }

        $this->submit('https://github.com/acme/workspace-switcher')
            ->assertStatus(429);
    }

    /**
     * POST a submission as a browser would, including a valid CSRF token.
     */
    private function submit(string $url, array $extra = []): TestResponse
    {
        session()->start();

        return $this->post(route('submit.store'), [
            '_token' => csrf_token(),
            'repository_url' => $url,
        ] + $extra);
    }
}
