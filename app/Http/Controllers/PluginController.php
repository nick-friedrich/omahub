<?php

namespace App\Http\Controllers;

use App\Services\Markdown\MarkdownRenderer;
use App\Services\Plugins\PluginDirectory;
use App\Services\Plugins\PluginVisitRefresher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PluginController extends Controller
{
    public function index(Request $request, PluginDirectory $directory, MarkdownRenderer $markdown): View
    {
        $plugins = $directory->browse(
            $request->query('category'),
            $request->query('tag'),
        );

        return view('plugins.index', [
            'plugins' => $plugins,
            'previewImages' => $plugins->getCollection()->mapWithKeys(fn ($plugin): array => [
                $plugin->getKey() => $plugin->readme_markdown !== null
                    ? $markdown->firstImageUrl($plugin->readme_markdown, $plugin->rawContentBaseUrl())
                    : null,
            ])->all(),
            'categories' => $directory->categoriesWithCounts(),
            'activeCategory' => $request->query('category') ?: null,
            'activeTag' => $request->query('tag') ?: null,
        ]);
    }

    public function show(
        string $slug,
        PluginDirectory $directory,
        MarkdownRenderer $markdown,
        PluginVisitRefresher $refresher,
    ): View {
        $plugin = $directory->findBySlug($slug);

        abort_if($plugin === null, 404);

        $refreshing = $refresher->refreshIfStale($plugin);

        $previewImage = $plugin->readme_markdown !== null
            ? $markdown->firstImageUrl($plugin->readme_markdown, $plugin->rawContentBaseUrl())
            : null;

        return view('plugins.show', [
            'plugin' => $plugin,
            'refreshing' => $refreshing,
            'previewImage' => $previewImage,
            'latestScan' => $plugin->securityScans()->with('findings')->orderByDesc('id')->first(),
            'readme' => $plugin->readme_markdown !== null
                ? $markdown->render($plugin->readme_markdown, $plugin->rawContentBaseUrl())
                : null,
        ]);
    }

    public function refreshStatus(
        string $slug,
        PluginDirectory $directory,
        PluginVisitRefresher $refresher,
    ): JsonResponse {
        $plugin = $directory->findBySlug($slug);

        abort_if($plugin === null, 404);

        return response()->json([
            'refreshing' => $refresher->isRefreshing($plugin),
            'indexed_at' => $plugin->last_indexed_at?->toISOString(),
            'commit_sha' => $plugin->latest_commit_sha,
        ])->header('Cache-Control', 'no-store');
    }
}
