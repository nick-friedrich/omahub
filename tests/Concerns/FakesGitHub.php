<?php

namespace Tests\Concerns;

use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

trait FakesGitHub
{
    /**
     * Fake the GitHub API for the workspace-switcher fixture. Use $routes to
     * override or replace individual responses (keyed by request path).
     *
     * @param  array<string, mixed>  $routes
     */
    private function fakeGitHub(
        string $owner = 'acme',
        string $name = 'workspace-switcher',
        int $stars = 42,
        string $sha = 'abc123',
        ?string $manifest = null,
        array $routes = [],
        ?string $redirectedOwner = null,
    ): void {
        $manifest ??= file_get_contents(base_path('tests/Fixtures/plugins/valid/manifest.json'));
        $base = "/repos/{$owner}/{$name}";
        $ownerLogin = $redirectedOwner ?? $owner;

        $defaults = [
            $base => Http::response([
                'name' => $name,
                'html_url' => "https://github.com/{$ownerLogin}/{$name}",
                'description' => 'A workspace utility.',
                'homepage' => 'https://example.com/workspace-switcher',
                'default_branch' => 'main',
                'stargazers_count' => $stars,
                'forks_count' => 7,
                'open_issues_count' => 2,
                'archived' => false,
                'pushed_at' => '2026-08-15T12:00:00Z',
                'license' => ['spdx_id' => 'MIT'],
                'owner' => [
                    'login' => $ownerLogin,
                    'html_url' => "https://github.com/{$ownerLogin}",
                ],
            ]),
            "{$base}/contents/manifest.json" => Http::response($manifest, 200, ['Content-Type' => 'application/json']),
            "{$base}/contents/Service.qml" => Http::response(['type' => 'file']),
            "{$base}/contents/Widget.qml" => Http::response(['type' => 'file']),
            "{$base}/readme" => Http::response('# Workspace Switcher'),
            "{$base}/commits/main" => Http::response(['sha' => $sha]),
            "{$base}/releases/latest" => Http::response(['tag_name' => 'v1.2.0']),
            "{$base}/license" => Http::response(['license' => ['spdx_id' => 'MIT']]),
        ];

        $responses = array_replace($defaults, $routes);

        Http::swap(new Factory);
        Http::fake(function (Request $request) use ($responses) {
            $path = parse_url($request->url(), PHP_URL_PATH);

            if (! array_key_exists($path, $responses)) {
                throw new RuntimeException("Unexpected GitHub request: {$request->url()}");
            }

            return $responses[$path];
        });
    }
}
