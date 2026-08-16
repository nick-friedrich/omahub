<?php

namespace App\Services\GitHub;

use App\Exceptions\GitHubRequestException;
use App\ValueObjects\GitHubRepository;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class GitHubClient
{
    /** @return array<string, mixed> */
    public function repository(GitHubRepository $repository): array
    {
        $response = $this->get("repos/{$repository->owner}/{$repository->name}");

        if ($response->notFound()) {
            throw GitHubRequestException::repositoryNotFound();
        }

        $this->ensureSuccessful($response);

        return $response->json();
    }

    public function manifest(GitHubRepository $repository, string $branch): ?string
    {
        return $this->rawFile($repository, 'manifest.json', $branch);
    }

    public function readme(GitHubRepository $repository, string $branch): ?string
    {
        $response = $this->get(
            "repos/{$repository->owner}/{$repository->name}/readme",
            ['ref' => $branch],
            'application/vnd.github.raw+json',
        );

        if ($response->notFound()) {
            return null;
        }

        $this->ensureSuccessful($response);

        return $response->body();
    }

    public function fileExists(GitHubRepository $repository, string $path, string $branch): bool
    {
        $encodedPath = implode('/', array_map('rawurlencode', explode('/', $path)));
        $response = $this->get(
            "repos/{$repository->owner}/{$repository->name}/contents/{$encodedPath}",
            ['ref' => $branch],
        );

        if ($response->notFound()) {
            return false;
        }

        $this->ensureSuccessful($response);

        return true;
    }

    /** @return array<string, mixed> */
    public function headCommit(GitHubRepository $repository, string $branch): array
    {
        $response = $this->get("repos/{$repository->owner}/{$repository->name}/commits/{$branch}");
        $this->ensureSuccessful($response);

        return $response->json();
    }

    public function latestVersion(GitHubRepository $repository): ?string
    {
        $release = $this->get("repos/{$repository->owner}/{$repository->name}/releases/latest");

        if ($release->successful()) {
            return $release->json('tag_name');
        }

        if (! $release->notFound()) {
            $this->ensureSuccessful($release);
        }

        $tags = $this->get("repos/{$repository->owner}/{$repository->name}/tags", ['per_page' => 1]);
        $this->ensureSuccessful($tags);

        return $tags->json('0.name');
    }

    public function licenseIdentifier(GitHubRepository $repository): ?string
    {
        $response = $this->get("repos/{$repository->owner}/{$repository->name}/license");

        if ($response->notFound()) {
            return null;
        }

        $this->ensureSuccessful($response);
        $identifier = $response->json('license.spdx_id');

        return is_string($identifier) && $identifier !== 'NOASSERTION' ? $identifier : null;
    }

    private function rawFile(GitHubRepository $repository, string $path, string $branch): ?string
    {
        $response = $this->get(
            "repos/{$repository->owner}/{$repository->name}/contents/{$path}",
            ['ref' => $branch],
            'application/vnd.github.raw+json',
        );

        if ($response->notFound()) {
            return null;
        }

        $this->ensureSuccessful($response);

        return $response->body();
    }

    /** @param array<string, mixed> $query */
    private function get(string $path, array $query = [], string $accept = 'application/vnd.github+json'): Response
    {
        try {
            return $this->request($accept)->get($path, $query);
        } catch (ConnectionException) {
            throw GitHubRequestException::networkFailure();
        }
    }

    private function request(string $accept): PendingRequest
    {
        $request = Http::baseUrl(rtrim(config('services.github.api_url'), '/'))
            ->accept($accept)
            ->withHeaders([
                'X-GitHub-Api-Version' => '2022-11-28',
                'User-Agent' => config('services.github.user_agent'),
            ])
            ->timeout(15)
            ->retry(2, 200, throw: false);

        $token = config('services.github.token');

        return filled($token) ? $request->withToken($token) : $request;
    }

    private function ensureSuccessful(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        if ($response->status() === 429 || ($response->status() === 403 && $response->header('X-RateLimit-Remaining') === '0')) {
            throw GitHubRequestException::rateLimited();
        }

        throw GitHubRequestException::requestFailed($response->status());
    }
}
