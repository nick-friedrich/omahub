<?php

namespace App\Http\Controllers;

use App\Models\Plugin;
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
            'totalPlugins' => $directory->totalPublishedCount(),
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

        if ($plugin === null) {
            $plugin = Plugin::query()
                ->where('slug', $slug)
                ->whereNotNull('repository_removed_at')
                ->first();
        }

        abort_if($plugin === null, 404);

        $removed = $plugin->isRepositoryRemoved();

        $refreshing = $removed ? false : $refresher->refreshIfStale($plugin);

        $previewImage = $plugin->readme_markdown !== null
            ? $markdown->firstImageUrl($plugin->readme_markdown, $plugin->rawContentBaseUrl())
            : null;

        return view('plugins.show', [
            'plugin' => $plugin,
            'removed' => $removed,
            'refreshing' => $refreshing,
            'previewImage' => $previewImage,
            'latestScan' => $plugin->securityScans()->with('findings')->orderByDesc('id')->first(),
            'latestAiReview' => $plugin->aiReviews()->orderByDesc('id')->first(),
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

        if ($plugin === null) {
            $plugin = Plugin::query()
                ->where('slug', $slug)
                ->whereNotNull('repository_removed_at')
                ->first();
        }

        abort_if($plugin === null, 404);

        return response()->json([
            'refreshing' => $plugin->isRepositoryRemoved()
                ? false
                : $refresher->isRefreshing($plugin),
            'removed' => $plugin->isRepositoryRemoved(),
            'indexed_at' => $plugin->last_indexed_at?->toISOString(),
            'commit_sha' => $plugin->latest_commit_sha,
        ])->header('Cache-Control', 'no-store');
    }
}
