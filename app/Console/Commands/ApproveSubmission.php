<?php

namespace App\Console\Commands;

use App\Services\Plugins\PluginSubmissionService;
use Illuminate\Console\Command;

class ApproveSubmission extends Command
{
    protected $signature = 'submissions:approve {id : The plugin submission ID}';

    protected $description = 'Approve a pending submission and publish its plugin';

    public function handle(PluginSubmissionService $submissions): int
    {
        try {
            $submission = $submissions->approve((int) $this->argument('id'));
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $plugin = $submission->plugin;
        $this->info($plugin !== null
            ? "Approved submission #{$submission->id} and published {$plugin->name}."
            : "Approved submission #{$submission->id}. It had no linked plugin to publish.");

        return self::SUCCESS;
    }
}
