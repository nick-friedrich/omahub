<?php

namespace App\Console\Commands;

use App\Enums\AiRecommendation;
use App\Enums\AiReviewStatus;
use App\Enums\RiskLevel;
use App\Exceptions\GitHubRequestException;
use App\Models\AiReview;
use App\Models\Plugin;
use App\Services\Ai\AiReviewer;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PluginsAiReview extends Command
{
    protected $signature = 'plugins:ai-review
        {--ids= : Comma-separated plugin IDs to review}
        {--after= : Only review plugins with an ID greater than this (resume a stopped run)}
        {--stale : Only review plugins whose latest commit has not been successfully AI-reviewed}
        {--dry-run : Report what would be reviewed without running anything}
        {--limit= : Maximum number of plugins to review}';

    protected $description = 'Run the advisory AI security review on plugins at their current commit (depends on the deterministic scan)';

    public function handle(AiReviewer $reviewer): int
    {
        $plugins = $this->targetPlugins();

        if ($plugins->isEmpty()) {
            $this->info('No plugins matched the given filters.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->table(
                ['ID', 'Slug', 'Repository', 'Commit'],
                $plugins->map(fn (Plugin $plugin) => [
                    $plugin->id,
                    $plugin->slug,
                    "{$plugin->repository_owner}/{$plugin->repository_name}",
                    (string) $plugin->latest_commit_sha,
                ]),
            );

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info("Reviewing {$plugins->count()} plugin(s).");
        $this->newLine();

        $reviewed = 0;
        $removed = 0;
        $failed = 0;

        foreach ($plugins as $plugin) {
            $label = "{$plugin->repository_owner}/{$plugin->repository_name}";

            try {
                $review = $reviewer->review($plugin);
            } catch (GitHubRequestException $exception) {
                if ($exception->isRateLimit) {
                    $this->newLine();
                    $this->error('Stopped: '.$exception->getMessage());
                    $this->warn('Set GITHUB_TOKEN in .env (e.g. `echo "GITHUB_TOKEN=$(gh auth token)" >> .env`) to raise the limit.');
                    $this->warn("Resume where this run stopped with: php artisan plugins:ai-review --after={$plugin->id}");

                    return self::FAILURE;
                }

                if ($exception->isNotFound) {
                    $plugin->markRepositoryRemoved();
                    $removed++;
                    $this->warn("  GONE  {$label} — repository no longer available (unpublished)");

                    continue;
                }

                $failed++;
                $this->error("FAILED  {$label}: {$exception->getMessage()}");

                continue;
            } catch (\Throwable $exception) {
                $failed++;
                $this->error("FAILED  {$label}: {$exception->getMessage()}");

                continue;
            }

            $reviewed++;
            $this->printReview($review, $label);
        }

        $this->newLine();

        if ($failed > 0 || $removed > 0) {
            $this->error("Complete: {$reviewed} reviewed, {$removed} removed, {$failed} failed.");
        } else {
            $this->info("Complete: {$reviewed} reviewed.");
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function printReview(AiReview $review, string $label): void
    {
        $risk = RiskLevel::tryFrom((string) $review->risk_level?->value) ?? RiskLevel::None;
        $recommendation = AiRecommendation::tryFrom((string) $review->recommendation?->value);
        $recommendationLabel = $recommendation?->label() ?? '—';

        $summary = trim((string) $review->summary);

        $this->info("  AI    {$label} — {$risk->label()}, recommendation “{$recommendationLabel}” ({$review->commit_sha})");

        if ($summary !== '') {
            $this->line("        {$summary}");
        }
    }

    /** @return Collection<int, Plugin> */
    private function targetPlugins()
    {
        $query = Plugin::query();

        $ids = $this->option('ids');
        if (is_string($ids) && $ids !== '') {
            $idList = array_filter(array_map('intval', explode(',', $ids)), fn (int $id): bool => $id > 0);
            $query->whereIntegerInRaw('id', $idList);
        }

        $after = $this->option('after');
        if (is_string($after) && is_numeric($after) && (int) $after >= 1) {
            $query->where('id', '>', (int) $after);
        }

        // Review only plugins whose latest commit has no successful AI review yet.
        // AiReviewer resolves the deterministic scan itself, so no separate scan
        // gate is needed here.
        if ($this->option('stale')) {
            $query
                ->whereNull('repository_removed_at')
                ->whereNotNull('latest_commit_sha')
                ->whereDoesntHave('aiReviews', function (Builder $q): void {
                    $q->where('status', AiReviewStatus::Succeeded)
                        ->whereColumn('ai_reviews.commit_sha', 'plugins.latest_commit_sha');
                });
        }

        $limit = $this->option('limit');
        if (is_string($limit) && is_numeric($limit) && (int) $limit > 0) {
            $query->limit((int) $limit);
        }

        return $query->get();
    }
}
