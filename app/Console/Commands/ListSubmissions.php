<?php

namespace App\Console\Commands;

use App\Enums\SubmissionStatus;
use App\Models\PluginSubmission;
use Illuminate\Console\Command;

class ListSubmissions extends Command
{
    protected $signature = 'submissions:list {--all : List every submission, not just pending ones}';

    protected $description = 'List plugin submissions awaiting review';

    public function handle(): int
    {
        $query = PluginSubmission::query()
            ->with('plugin')
            ->orderBy('submitted_at');

        if (! $this->option('all')) {
            $query->where('status', SubmissionStatus::Pending);
        }

        $submissions = $query->get();

        if ($submissions->isEmpty()) {
            $this->info($this->option('all')
                ? 'No submissions have been recorded.'
                : 'There are no submissions awaiting review.');

            return self::SUCCESS;
        }

        $rows = [];
        foreach ($submissions as $submission) {
            $rows[] = [
                (string) $submission->id,
                $submission->status->value,
                $submission->repository_url,
                $submission->plugin !== null ? $submission->plugin->name : '—',
                $submission->submitted_at->toDateTimeString(),
            ];
        }

        $this->table(['ID', 'Status', 'Repository', 'Plugin', 'Submitted'], $rows);

        return self::SUCCESS;
    }
}
