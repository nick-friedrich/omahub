<?php

namespace App\Http\Controllers;

use App\Enums\SubmissionStatus;
use App\Exceptions\DuplicateSubmissionException;
use App\Exceptions\InvalidGitHubRepositoryUrl;
use App\Http\Requests\SubmitPluginRequest;
use App\Services\Plugins\PluginSubmissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class SubmitController extends Controller
{
    public function __construct(
        private readonly PluginSubmissionService $submissions,
    ) {}

    public function index(): View
    {
        return view('submit');
    }

    public function store(SubmitPluginRequest $request): RedirectResponse
    {
        // Honeypot field filled by an automated bot: acknowledge without doing any work.
        if ($request->filled('website')) {
            return $this->confirmation(accepted: true, submitted: false);
        }

        try {
            $submission = $this->submissions->submit($request->validated('repository_url'));
        } catch (InvalidGitHubRepositoryUrl $exception) {
            return back()
                ->withErrors(['repository_url' => $exception->getMessage()])
                ->withInput();
        } catch (DuplicateSubmissionException $exception) {
            return back()
                ->withErrors(['repository_url' => $exception->getMessage()])
                ->withInput();
        }

        return $this->confirmation(
            accepted: $submission->status === SubmissionStatus::Pending,
            submitted: true,
        );
    }

    private function confirmation(bool $accepted, bool $submitted): RedirectResponse
    {
        if (! $submitted) {
            return Redirect::route('submit')->with('status', 'received');
        }

        return Redirect::route('submit')->with('status', $accepted ? 'pending' : 'import_failed');
    }
}
