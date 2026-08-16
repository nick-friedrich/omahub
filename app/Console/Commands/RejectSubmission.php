<?php

namespace App\Console\Commands;

use App\Services\Plugins\PluginSubmissionService;
use Illuminate\Console\Command;

class RejectSubmission extends Command
{
    protected $signature = 'submissions:reject {id : The plugin submission ID} {reason? : Optional reason for rejection}';

    protected $description = 'Reject a pending plugin submission';

    public function handle(PluginSubmissionService $submissions): int
    {
        try {
            $submission = $submissions->reject(
                (int) $this->argument('id'),
                $this->argument('reason'),
            );
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Rejected submission #{$submission->id}.");

        return self::SUCCESS;
    }
}
