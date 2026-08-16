<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PluginStatus;
use App\Enums\SubmissionStatus;
use App\Http\Controllers\Controller;
use App\Models\Plugin;
use App\Models\PluginSubmission;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function index(): View
    {
        $pendingSubmissions = PluginSubmission::query()
            ->where('status', SubmissionStatus::Pending)
            ->orderBy('submitted_at')
            ->with('plugin')
            ->get();

        return view('admin.dashboard', [
            'pendingSubmissions' => $pendingSubmissions,
            'submissionCounts' => [
                'pending' => PluginSubmission::query()->where('status', SubmissionStatus::Pending)->count(),
                'approved' => PluginSubmission::query()->where('status', SubmissionStatus::Approved)->count(),
                'rejected' => PluginSubmission::query()->where('status', SubmissionStatus::Rejected)->count(),
                'failed' => PluginSubmission::query()->where('status', SubmissionStatus::Failed)->count(),
            ],
            'pluginCounts' => [
                'published' => Plugin::query()->where('status', PluginStatus::Published)->count(),
                'pending' => Plugin::query()->where('status', PluginStatus::Pending)->count(),
                'archived' => Plugin::query()->where('status', PluginStatus::Archived)->count(),
                'rejected' => Plugin::query()->where('status', PluginStatus::Rejected)->count(),
            ],
        ]);
    }
}
