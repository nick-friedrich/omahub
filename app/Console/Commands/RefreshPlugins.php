<?php

namespace App\Console\Commands;

use App\Exceptions\GitHubRequestException;
use App\Models\Plugin;
use App\Services\Plugins\GitHubRepositoryImporter;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class RefreshPlugins extends Command
{
    protected $signature = 'plugins:refresh
        {--ids= : Comma-separated plugin IDs to refresh}
        {--after= : Only refresh plugins with an ID greater than this (resume a stopped run)}
        {--missing-readme : Refresh only plugins whose README is empty or null}
        {--dry-run : Report what would be refreshed without calling GitHub}
        {--limit= : Maximum number of plugins to refresh}';

    protected $description = 'Revisit and update existing plugins against their GitHub repository (metadata and README)';

    public function handle(GitHubRepositoryImporter $importer): int
    {
        $plugins = $this->targetPlugins();

        if ($plugins->isEmpty()) {
            $this->info('No plugins matched the given filters.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info("Refreshing {$plugins->count()} plugin(s).");
        $this->newLine();

        if ($this->option('dry-run')) {
            $this->table(
                ['ID', 'Slug', 'Repository'],
                $plugins->map(fn (Plugin $plugin) => [
                    $plugin->id,
                    $plugin->slug,
                    "{$plugin->repository_owner}/{$plugin->repository_name}",
                ]),
            );

            return self::SUCCESS;
        }

        $updated = 0;
        $unchanged = 0;
        $removed = 0;
        $failed = 0;

        foreach ($plugins as $plugin) {
            $label = "{$plugin->repository_owner}/{$plugin->repository_name}";
            $previousSha = (string) $plugin->latest_commit_sha;
            $etag = is_string($plugin->github_etag) ? $plugin->github_etag : null;

            try {
                $fresh = $importer->import($this->repositoryUrl($plugin), filled($etag) ? $etag : null);
            } catch (GitHubRequestException $exception) {
                if ($exception->isRateLimit) {
                    $this->newLine();
                    $this->error('Stopped: '.$exception->getMessage());
                    $this->warn('Set GITHUB_TOKEN in .env (e.g. `echo "GITHUB_TOKEN=$(gh auth token)" >> .env`) to raise the limit.');
                    $this->warn("Resume where this run stopped with: php artisan plugins:refresh --after={$plugin->id}".($this->option('missing-readme') ? ' --missing-readme' : ''));

                    return self::FAILURE;
                }

                if ($exception->isNotFound) {
                    // Repository deleted or made private — unpublish so it is no
                    // longer listed, and remember so we can restore it if it
                    // comes back.
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

            if ($fresh->latest_commit_sha !== $previousSha) {
                $updated++;
                $this->line("  OK    {$label} ({$fresh->latest_commit_sha}) — readme present");
            } else {
                $unchanged++;
                $this->line("  SAME  {$label} — unchanged since last run");
            }
        }

        $this->newLine();

        if ($failed > 0 || $removed > 0) {
            $this->error("Complete: {$updated} updated, {$unchanged} unchanged, {$removed} removed, {$failed} failed.");
        } else {
            $this->info("Complete: {$updated} updated, {$unchanged} unchanged.");
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
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

        if ($this->option('missing-readme')) {
            $query->where(function (Builder $q): void {
                $q->whereNull('readme_markdown')->orWhere('readme_markdown', '');
            });
        }

        $limit = $this->option('limit');
        if (is_string($limit) && is_numeric($limit) && (int) $limit > 0) {
            $query->limit((int) $limit);
        }

        return $query->get();
    }

    private function repositoryUrl(Plugin $plugin): string
    {
        return "https://github.com/{$plugin->repository_owner}/{$plugin->repository_name}";
    }
}
