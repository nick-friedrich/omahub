<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SubmissionStatus;
use App\Http\Controllers\Controller;
use App\Models\PluginSubmission;
use App\Services\Ai\AiReviewer;
use App\Services\Plugins\PluginSubmissionService;
use App\Services\Security\SecurityScanner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class AdminSubmissionController extends Controller
{
    public function __construct(
        private readonly PluginSubmissionService $submissions,
        private readonly SecurityScanner $scanner,
        private readonly AiReviewer $aiReviewer,
    ) {}

    public function index(Request $request): View
    {
        $status = $request->query('status');

        $query = PluginSubmission::query()
            ->with('plugin')
            ->orderByDesc('submitted_at');

        if (in_array($status, array_column(SubmissionStatus::cases(), 'value'), true)) {
            $query->where('status', SubmissionStatus::from($status));
        }

        return view('admin.submissions.index', [
            'submissions' => $query->paginate(20)->withQueryString(),
            'currentStatus' => $status,
            'statuses' => SubmissionStatus::cases(),
        ]);
    }

    public function show(PluginSubmission $submission): View
    {
        $submission->load('plugin');

        $plugin = $submission->plugin;

        return view('admin.submissions.show', [
            'submission' => $submission,
            'latestScan' => $plugin?->securityScans()->with('findings')->orderByDesc('id')->first(),
            'latestAiReview' => $plugin?->aiReviews()->orderByDesc('id')->first(),
        ]);
    }

    public function scan(PluginSubmission $submission): RedirectResponse
    {
        $plugin = $submission->plugin;

        if ($plugin === null) {
            return $this->backWithError('This submission has no imported plugin, so it cannot be scanned.');
        }

        try {
            $scan = $this->scanner->scan($plugin);
        } catch (\Throwable $exception) {
            return $this->backWithError("Scan failed: {$exception->getMessage()}");
        }

        $findings = $scan->findings()->count();
        $summary = $findings === 0
            ? "No obvious issues detected (commit {$scan->commit_sha})."
            : "Found {$findings} finding(s), risk level “{$scan->risk_level}” (commit {$scan->commit_sha}).";

        return $this->back()->with('status', "Scan complete. {$summary}");
    }

    public function aiReview(PluginSubmission $submission): RedirectResponse
    {
        $plugin = $submission->plugin;

        if ($plugin === null) {
            return $this->backWithError('This submission has no imported plugin, so it cannot be AI-reviewed.');
        }

        try {
            $review = $this->aiReviewer->review($plugin);
        } catch (\Throwable $exception) {
            return $this->backWithError("AI review failed: {$exception->getMessage()}");
        }

        $risk = $review->risk_level->value ?? 'none';
        $recommendation = $review->recommendation->value ?? '—';

        return $this->back()->with('status', "AI review complete. Risk level “{$risk}”, recommendation “{$recommendation}”.");
    }

    public function approve(PluginSubmission $submission): RedirectResponse
    {
        try {
            $submission = $this->submissions->approve($submission->id);
        } catch (\Throwable $exception) {
            return $this->backWithError($exception);
        }

        $name = $submission->plugin->name ?? 'submission';

        return $this->back()
            ->with('status', "Approved submission #{$submission->id}. ".($name !== 'submission' ? "Published “{$name}”." : ''));
    }

    public function reject(Request $request, PluginSubmission $submission): RedirectResponse
    {
        $reason = (string) $request->input('reason');

        try {
            $submission = $this->submissions->reject($submission->id, $reason !== '' ? $reason : null);
        } catch (\Throwable $exception) {
            return $this->backWithError($exception);
        }

        return $this->back()->with('status', "Rejected submission #{$submission->id}.");
    }

    private function back(): RedirectResponse
    {
        return Redirect::back();
    }

    private function backWithError(\Throwable|string $error): RedirectResponse
    {
        return Redirect::back()->with('error', $error instanceof \Throwable ? $error->getMessage() : $error);
    }
}
