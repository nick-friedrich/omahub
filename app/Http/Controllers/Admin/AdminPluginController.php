<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PluginStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePluginRequest;
use App\Models\Category;
use App\Models\Plugin;
use App\Models\Tag;
use App\Services\Plugins\GitHubRepositoryImporter;
use App\Services\Security\SecurityScanner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class AdminPluginController extends Controller
{
    public function __construct(
        private readonly GitHubRepositoryImporter $importer,
        private readonly SecurityScanner $scanner,
    ) {}

    public function index(Request $request): View
    {
        $status = $request->query('status');

        $query = Plugin::query()->with(['categories', 'tags']);

        if (in_array($status, array_column(PluginStatus::cases(), 'value'), true)) {
            $query->where('status', PluginStatus::from($status));
        }

        return view('admin.plugins.index', [
            'plugins' => $query->orderBy('name')->paginate(20)->withQueryString(),
            'currentStatus' => $status,
            'statuses' => PluginStatus::cases(),
        ]);
    }

    public function edit(Plugin $plugin): View
    {
        return view('admin.plugins.edit', [
            'plugin' => $plugin->load(['categories', 'tags']),
            'latestScan' => $plugin->securityScans()->with('findings')->orderByDesc('id')->first(),
            'categories' => Category::query()->orderBy('name')->get(),
            'tags' => Tag::query()->orderBy('name')->get(),
        ]);
    }

    public function update(UpdatePluginRequest $request, Plugin $plugin): RedirectResponse
    {
        $plugin->update([
            'name' => $request->validated('name'),
            'description' => blank($request->validated('description')) ? null : $request->validated('description'),
            'author_name' => blank($request->validated('author_name')) ? null : $request->validated('author_name'),
            'author_url' => blank($request->validated('author_url')) ? null : $request->validated('author_url'),
            'license' => blank($request->validated('license')) ? null : $request->validated('license'),
            'homepage_url' => blank($request->validated('homepage_url')) ? null : $request->validated('homepage_url'),
        ]);

        $plugin->categories()->sync($request->input('categories', []));
        $plugin->tags()->sync($request->input('tags', []));

        return Redirect::route('admin.plugins.index')
            ->with('status', "Updated “{$plugin->name}”.");
    }

    public function refresh(Plugin $plugin): RedirectResponse
    {
        try {
            $fresh = $this->importer->import($this->repositoryUrl($plugin));
        } catch (\Throwable $exception) {
            return Redirect::back()->with('error', "Refresh failed: {$exception->getMessage()}");
        }

        return Redirect::back()->with('status', "Refreshed “{$fresh->name}” from GitHub.");
    }

    public function scan(Plugin $plugin): RedirectResponse
    {
        try {
            $scan = $this->scanner->scan($plugin);
        } catch (\Throwable $exception) {
            return Redirect::back()->with('error', "Scan failed: {$exception->getMessage()}");
        }

        $findings = $scan->findings()->count();
        $summary = $findings === 0
            ? "No obvious issues detected (commit {$scan->commit_sha})."
            : "Found {$findings} finding(s), risk level “{$scan->risk_level}” (commit {$scan->commit_sha}).";

        return Redirect::back()->with('status', "Scan complete. {$summary}");
    }

    public function status(Request $request, Plugin $plugin): RedirectResponse
    {
        $target = $request->input('status');

        if (! in_array($target, array_column(PluginStatus::cases(), 'value'), true)) {
            return $this->backWithError('Invalid status.');
        }

        $targetEnum = PluginStatus::from($target);

        $plugin->update([
            'status' => $targetEnum,
            'published_at' => $targetEnum === PluginStatus::Published
                ? ($plugin->published_at ?? now())
                : $plugin->published_at,
        ]);

        $label = strtolower($targetEnum->value);

        return Redirect::back()->with('status', "“{$plugin->name}” is now {$label}.");
    }

    public function destroy(Plugin $plugin): RedirectResponse
    {
        $name = $plugin->name;
        $plugin->delete();

        return Redirect::route('admin.plugins.index')->with('status', "Deleted “{$name}”.");
    }

    private function repositoryUrl(Plugin $plugin): string
    {
        return "https://github.com/{$plugin->repository_owner}/{$plugin->repository_name}";
    }

    private function backWithError(string $message): RedirectResponse
    {
        return Redirect::back()->with('error', $message);
    }
}
