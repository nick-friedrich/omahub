<?php

namespace App\Http\Controllers;

use App\Services\Markdown\MarkdownRenderer;
use App\Services\Plugins\PluginDirectory;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(PluginDirectory $directory, MarkdownRenderer $markdown): View
    {
        $recentlyUpdated = $directory->recentlyUpdated();
        $newest = $directory->newest();
        $popular = $directory->popular();
        $plugins = $recentlyUpdated->concat($newest)->concat($popular)->unique('id');

        return view('home', [
            'recentlyUpdated' => $recentlyUpdated,
            'newest' => $newest,
            'popular' => $popular,
            'previewImages' => $plugins->mapWithKeys(fn ($plugin): array => [
                $plugin->getKey() => $plugin->readme_markdown !== null
                    ? $markdown->firstImageUrl($plugin->readme_markdown, $plugin->rawContentBaseUrl())
                    : null,
            ])->all(),
        ]);
    }
}
