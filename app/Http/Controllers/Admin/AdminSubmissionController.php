<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SubmissionStatus;
use App\Http\Controllers\Controller;
use App\Models\PluginSubmission;
use App\Services\Plugins\PluginSubmissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class AdminSubmissionController extends Controller
{
    public function __construct(
        private readonly PluginSubmissionService $submissions,
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
        return view('admin.submissions.show', [
            'submission' => $submission->load('plugin'),
        ]);
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

    private function backWithError(\Throwable $exception): RedirectResponse
    {
        return Redirect::back()->with('error', $exception->getMessage());
    }
}
