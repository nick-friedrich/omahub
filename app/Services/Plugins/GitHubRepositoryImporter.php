<?php

namespace App\Services\Plugins;

use App\Enums\PluginStatus;
use App\Exceptions\ManifestValidationException;
use App\Models\Plugin;
use App\Services\GitHub\GitHubClient;
use App\ValueObjects\GitHubRepository;
use Illuminate\Support\Facades\DB;
use UnexpectedValueException;

class GitHubRepositoryImporter
{
    public function __construct(
        private readonly GitHubClient $github,
        private readonly ManifestValidator $manifests,
    ) {}

    public function import(string $url): Plugin
    {
        $requestedRepository = GitHubRepository::fromUrl($url);
        $repositoryData = $this->github->repository($requestedRepository);
        $repository = $this->repositoryFromResponse($repositoryData);
        $defaultBranch = $repositoryData['default_branch'] ?? null;

        if (! is_string($defaultBranch) || $defaultBranch === '') {
            throw new UnexpectedValueException('GitHub did not return a default branch for this repository.');
        }

        $manifestJson = $this->github->manifest($repository, $defaultBranch);

        if ($manifestJson === null) {
            throw new ManifestValidationException(['manifest.json was not found at the repository root.']);
        }

        $manifest = $this->manifests->validate($manifestJson);
        $this->validateEntryPointsExist($manifest, $repository, $defaultBranch);
        $readme = $this->github->readme($repository, $defaultBranch);
        $commit = $this->github->headCommit($repository, $defaultBranch);
        $latestVersion = $this->github->latestVersion($repository) ?? $manifest['version'];
        $license = $this->license($manifest, $repositoryData);
        $commitSha = $commit['sha'] ?? null;

        if (! is_string($commitSha) || $commitSha === '') {
            throw new UnexpectedValueException('GitHub did not return the latest commit SHA.');
        }

        return DB::transaction(function () use (
            $requestedRepository,
            $repository,
            $repositoryData,
            $manifest,
            $readme,
            $defaultBranch,
            $commitSha,
            $latestVersion,
            $license,
        ): Plugin {
            $plugin = Plugin::query()->firstOrNew([
                'repository_owner' => $requestedRepository->owner,
                'repository_name' => $requestedRepository->name,
            ]);

            if (! $plugin->exists) {
                // The repository may have moved (GitHub redirects). When another
                // row already tracks the canonical owner/name, refresh that row
                // instead of creating a duplicate for the same plugin.
                $canonical = Plugin::query()
                    ->where('repository_owner', $repository->owner)
                    ->where('repository_name', $repository->name)
                    ->first();

                if ($canonical !== null) {
                    $plugin = $canonical;
                } else {
                    // Distinct repositories can share a manifest id (forks,
                    // copies); keep slugs unique so one bad match cannot
                    // break the whole refresh.
                    $plugin->slug = $this->uniqueSlug(strtolower($manifest['id']));
                    $plugin->status = PluginStatus::Pending;
                }
            }

            $plugin->fill([
                'name' => trim($manifest['name']),
                'description' => $this->optionalString($manifest['description'] ?? $repositoryData['description'] ?? null),
                'repository_url' => $repositoryData['html_url'] ?? $repository->canonicalUrl(),
                'repository_owner' => $repository->owner,
                'repository_name' => $repository->name,
                'author_name' => $this->optionalString($manifest['author'] ?? null) ?? $repository->owner,
                'author_url' => $repositoryData['owner']['html_url'] ?? "https://github.com/{$repository->owner}",
                'license' => $license,
                'homepage_url' => $this->validUrl($repositoryData['homepage'] ?? null),
                'icon_url' => $this->validUrl($manifest['icon'] ?? null),
                'manifest_data' => $manifest,
                'readme_markdown' => $readme,
                'default_branch' => $defaultBranch,
                'latest_commit_sha' => $commitSha,
                'latest_version' => $latestVersion,
                'stars_count' => $repositoryData['stargazers_count'] ?? 0,
                'forks_count' => $repositoryData['forks_count'] ?? 0,
                'open_issues_count' => $repositoryData['open_issues_count'] ?? 0,
                'is_archived' => $repositoryData['archived'] ?? false,
                'last_pushed_at' => $repositoryData['pushed_at'] ?? null,
                'last_indexed_at' => now(),
            ]);
            $plugin->save();

            return $plugin->refresh();
        });
    }

    /** @param array<string, mixed> $manifest */
    private function validateEntryPointsExist(
        array $manifest,
        GitHubRepository $repository,
        string $branch,
    ): void {
        $missing = [];

        foreach ($manifest['entryPoints'] as $path) {
            if (! $this->github->fileExists($repository, $path, $branch)) {
                $missing[] = $path;
            }
        }

        if ($missing !== []) {
            throw new ManifestValidationException([
                'The following entry point files were not found: '.implode(', ', $missing).'.',
            ]);
        }
    }

    private function uniqueSlug(string $base): string
    {
        $candidate = $base;
        $suffix = 2;

        while (Plugin::query()->where('slug', $candidate)->exists()) {
            $candidate = "{$base}-{$suffix}";
            $suffix++;
        }

        return $candidate;
    }

    /** @param array<string, mixed> $repositoryData */
    private function repositoryFromResponse(array $repositoryData): GitHubRepository
    {
        $owner = $repositoryData['owner']['login'] ?? null;
        $name = $repositoryData['name'] ?? null;

        if (! is_string($owner) || ! is_string($name)) {
            throw new UnexpectedValueException('GitHub returned incomplete repository metadata.');
        }

        return new GitHubRepository(strtolower($owner), strtolower($name));
    }

    /** @param array<string, mixed> $manifest
     * @param  array<string, mixed>  $repositoryData
     */
    private function license(array $manifest, array $repositoryData): ?string
    {
        $manifestLicense = $this->optionalString($manifest['license'] ?? null);
        $repositoryLicense = $this->optionalString($repositoryData['license']['spdx_id'] ?? null);

        if ($repositoryLicense === 'NOASSERTION') {
            $repositoryLicense = null;
        }

        return $manifestLicense ?? $repositoryLicense;
    }

    private function optionalString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function validUrl(mixed $value): ?string
    {
        return is_string($value) && filter_var($value, FILTER_VALIDATE_URL) ? $value : null;
    }
}
