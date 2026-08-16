<?php

namespace App\Services\Plugins;

use App\Enums\PluginStatus;
use App\Enums\SubmissionStatus;
use App\Exceptions\DuplicateSubmissionException;
use App\Exceptions\GitHubRequestException;
use App\Exceptions\InvalidGitHubRepositoryUrl;
use App\Exceptions\ManifestValidationException;
use App\Models\Plugin;
use App\Models\PluginSubmission;
use App\ValueObjects\GitHubRepository;
use UnexpectedValueException;

class PluginSubmissionService
{
    public function __construct(
        private readonly GitHubRepositoryImporter $importer,
    ) {}

    /**
     * Validate and record a community submission for a public GitHub repository.
     *
     * The importer runs synchronously. Successful imports leave the plugin pending
     * for a maintainer to publish; import failures are recorded so a maintainer can
     * investigate. Public validations and duplicate detection happen before any
     * network work.
     */
    public function submit(string $url): PluginSubmission
    {
        $repository = GitHubRepository::fromUrl($url);
        $canonicalUrl = $repository->canonicalUrl();

        if ($this->hasPendingSubmission($canonicalUrl)) {
            throw new DuplicateSubmissionException;
        }

        $status = SubmissionStatus::Pending;
        $pluginId = null;
        $failureReason = null;

        try {
            $plugin = $this->importer->import($canonicalUrl);
            $pluginId = $plugin->id;
        } catch (InvalidGitHubRepositoryUrl|GitHubRequestException|ManifestValidationException|UnexpectedValueException $exception) {
            $status = SubmissionStatus::Failed;
            $failureReason = $exception->getMessage();
        } catch (\Throwable $exception) {
            report($exception);
            $status = SubmissionStatus::Failed;
            $failureReason = 'The plugin could not be imported. A maintainer will investigate.';
        }

        return PluginSubmission::query()->create([
            'repository_url' => $canonicalUrl,
            'plugin_id' => $pluginId,
            'status' => $status,
            'failure_reason' => $failureReason,
            'submitted_at' => now(),
        ]);
    }

    /**
     * Publish a pending submission and its plugin.
     */
    public function approve(int $id): PluginSubmission
    {
        $submission = PluginSubmission::findOrFail($id);

        if ($submission->status !== SubmissionStatus::Pending) {
            throw new \LogicException("Only a pending submission can be approved (this one is {$submission->status->value}).");
        }

        $submission->update([
            'status' => SubmissionStatus::Approved,
            'reviewed_at' => now(),
        ]);

        $plugin = $submission->plugin;

        if ($plugin !== null) {
            $plugin->update([
                'status' => PluginStatus::Published,
                'published_at' => $plugin->published_at ?? now(),
            ]);
        }

        return $submission->refresh();
    }

    /**
     * Reject a pending submission, leaving any linked (still unpublished) plugin pending-free.
     */
    public function reject(int $id, ?string $reason = null): PluginSubmission
    {
        $submission = PluginSubmission::findOrFail($id);

        if ($submission->status !== SubmissionStatus::Pending) {
            throw new \LogicException("Only a pending submission can be rejected (this one is {$submission->status->value}).");
        }

        $submission->update([
            'status' => SubmissionStatus::Rejected,
            'failure_reason' => trim((string) $reason) !== '' ? trim($reason) : $submission->failure_reason,
            'reviewed_at' => now(),
        ]);

        $this->settleRejectedPlugin($submission->plugin);

        return $submission->refresh();
    }

    public function pendingCount(): int
    {
        return PluginSubmission::query()
            ->where('status', SubmissionStatus::Pending)
            ->count();
    }

    private function hasPendingSubmission(string $canonicalUrl): bool
    {
        return PluginSubmission::query()
            ->where('repository_url', $canonicalUrl)
            ->where('status', SubmissionStatus::Pending)
            ->exists();
    }

    private function settleRejectedPlugin(?Plugin $plugin): void
    {
        if ($plugin !== null && $plugin->status === PluginStatus::Pending) {
            $plugin->update(['status' => PluginStatus::Rejected]);
        }
    }
}
