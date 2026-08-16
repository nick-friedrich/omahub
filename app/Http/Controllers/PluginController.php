<?php

namespace App\Http\Controllers;

use App\Services\Markdown\MarkdownRenderer;
use App\Services\Plugins\PluginDirectory;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PluginController extends Controller
{
    public function index(Request $request, PluginDirectory $directory): View
    {
        return view('plugins.index', [
            'plugins' => $directory->browse(
                $request->query('category'),
                $request->query('tag'),
            ),
            'categories' => $directory->categoriesWithCounts(),
            'activeCategory' => $request->query('category') ?: null,
            'activeTag' => $request->query('tag') ?: null,
        ]);
    }

    public function show(string $slug, PluginDirectory $directory, MarkdownRenderer $markdown): View
    {
        $plugin = $directory->findBySlug($slug);

        abort_if($plugin === null, 404);

        return view('plugins.show', [
            'plugin' => $plugin,
            'readme' => $plugin->readme_markdown !== null
                ? $markdown->render($plugin->readme_markdown, $plugin->rawContentBaseUrl())
                : null,
        ]);
    }
}
