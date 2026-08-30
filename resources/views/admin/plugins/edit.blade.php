<x-admin-layout :title="'Edit '.$plugin->name">
    <a href="{{ route('admin.plugins.index') }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">← Plugins</a>
    <h1 class="mt-1 text-2xl font-bold tracking-tight">Edit “{{ $plugin->name }}”</h1>

    @if ($plugin->isRepositoryRemoved())
        <div class="mt-4 rounded-lg border border-l-4 border-red-300 border-l-red-500 bg-red-50 p-4 dark:border-red-900 dark:border-l-red-500 dark:bg-red-950/30">
            <p class="font-semibold text-red-800 dark:text-red-300">Repository no longer available</p>
            <p class="mt-1 text-sm leading-relaxed text-red-800/90 dark:text-red-300/90">
                The GitHub repository was deleted or made private{{ $plugin->repository_removed_at ? ' on '.$plugin->repository_removed_at->format('M j, Y H:i') : '' }}.
                This plugin was unpublished automatically. Refresh to re-check, or it will restore automatically if the repository comes back.
            </p>
        </div>
    @endif

    @if ($plugin->isAiUnpublished())
        <div class="mt-4 rounded-lg border border-l-4 border-red-300 border-l-red-500 bg-red-50 p-4 dark:border-red-900 dark:border-l-red-500 dark:bg-red-950/30">
            <p class="font-semibold text-red-800 dark:text-red-300">Auto-unpublished by AI review</p>
            <p class="mt-1 text-sm leading-relaxed text-red-800/90 dark:text-red-300/90">
                An AI advisory review{{ $plugin->ai_unpublished_at ? ' on '.$plugin->ai_unpublished_at->format('M j, Y H:i') : '' }}
                rated the latest commit high/critical risk and recommended avoiding it. The plugin was unpublished automatically.
                Review the findings below and re-publish manually if you disagree.
            </p>
        </div>
    @endif

    <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_18rem]">
        <div class="space-y-6">
            <form method="POST" action="{{ route('admin.plugins.update', $plugin) }}" class="rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-[#161615]">
                @csrf
                @method('PUT')

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="name" class="block text-sm font-medium">Name</label>
                        <input id="name" name="name" value="{{ old('name', $plugin->name) }}" required class="mt-2 w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-400 dark:border-gray-700 dark:bg-gray-900">
                        @error('name')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="license" class="block text-sm font-medium">License</label>
                        <input id="license" name="license" value="{{ old('license', $plugin->license) }}" class="mt-2 w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-400 dark:border-gray-700 dark:bg-gray-900">
                        @error('license')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="author_name" class="block text-sm font-medium">Author name</label>
                        <input id="author_name" name="author_name" value="{{ old('author_name', $plugin->author_name) }}" class="mt-2 w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-400 dark:border-gray-700 dark:bg-gray-900">
                    </div>
                    <div>
                        <label for="author_url" class="block text-sm font-medium">Author URL</label>
                        <input id="author_url" name="author_url" type="url" value="{{ old('author_url', $plugin->author_url) }}" class="mt-2 w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-400 dark:border-gray-700 dark:bg-gray-900">
                        @error('author_url')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="homepage_url" class="block text-sm font-medium">Homepage URL</label>
                        <input id="homepage_url" name="homepage_url" type="url" value="{{ old('homepage_url', $plugin->homepage_url) }}" class="mt-2 w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-400 dark:border-gray-700 dark:bg-gray-900">
                        @error('homepage_url')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="mt-4">
                    <label for="description" class="block text-sm font-medium">Description</label>
                    <textarea id="description" name="description" rows="3" class="mt-2 w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-400 dark:border-gray-700 dark:bg-gray-900">{{ old('description', $plugin->description) }}</textarea>
                </div>

                <div class="mt-6">
                    <p class="text-sm font-medium">Categories</p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach ($categories as $category)
                            <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-md border border-gray-300 px-2.5 py-1 text-xs dark:border-gray-700">
                                <input type="checkbox" name="categories[]" value="{{ $category->id }}" @checked($plugin->categories->contains($category)) class="accent-blue-600">
                                {{ $category->name }}
                            </label>
                        @endforeach
                    </div>

                    <p class="mt-4 text-sm font-medium">Tags</p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach ($tags as $tag)
                            <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-md border border-gray-300 px-2.5 py-1 text-xs dark:border-gray-700">
                                <input type="checkbox" name="tags[]" value="{{ $tag->id }}" @checked($plugin->tags->contains($tag)) class="accent-blue-600">
                                #{{ $tag->name }}
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="mt-6 flex items-center gap-3">
                    <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save changes</button>
                </div>
            </form>

            <form method="POST" action="{{ route('admin.plugins.refresh', $plugin) }}" class="rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-[#161615]">
                @csrf
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-semibold">Re-import from GitHub</h2>
                        <p class="mt-1 text-xs text-gray-500">Refresh metadata and README from {{ $plugin->repository_owner }}/{{ $plugin->repository_name }}.</p>
                    </div>
                    <button type="submit" class="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium hover:bg-gray-100 dark:border-gray-700 dark:hover:bg-gray-900">Refresh</button>
                </div>
            </form>

            <div class="rounded-lg border border-gray-200 bg-white p-5 text-sm dark:border-gray-800 dark:bg-[#161615]">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-semibold">Security review</h2>
                        <p class="mt-1 text-xs text-gray-500">Deterministic, rule-based scan of the repository at its current commit.</p>
                    </div>
                    <form method="POST" action="{{ route('admin.plugins.scan', $plugin) }}">
                        @csrf
                        <button type="submit" class="rounded-md bg-amber-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-amber-700">
                            @if ($latestScan) Rescan @else Run scan @endif
                        </button>
                    </form>
                </div>

                @if (!$latestScan)
                    <p class="mt-4 rounded-lg bg-amber-50 p-3 text-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                        Not yet scanned. Run a scan to check this repository for potentially dangerous behavior.
                    </p>
                @else
                    @php
                        $risk = $latestScan->risk_level ?? 'none';
                        $riskColor = match ($risk) {
                            'critical', 'high' => 'bg-red-600 text-white dark:bg-red-500 dark:text-white',
                            'medium' => 'bg-amber-500 text-[#1b1b18] dark:bg-amber-400 dark:text-[#1b1b18]',
                            'low' => 'bg-yellow-500 text-[#1b1b18] dark:bg-yellow-400 dark:text-[#1b1b18]',
                            default => 'bg-emerald-600 text-white dark:bg-emerald-500 dark:text-white',
                        };
                    @endphp

                    <dl class="mt-4 flex flex-wrap gap-x-6 gap-y-2">
                        <div><dt class="text-gray-500 dark:text-gray-400">Risk</dt><dd class="mt-0.5"><span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $riskColor }}">{{ ucfirst($risk) }}</span></dd></div>
                        <div><dt class="text-gray-500 dark:text-gray-400">Commit</dt><dd class="mt-0.5 font-mono text-xs">{{ $latestScan->commit_sha }}</dd></div>
                        <div><dt class="text-gray-500 dark:text-gray-400">Scanned</dt><dd class="mt-0.5">{{ $latestScan->finished_at?->format('M j, Y H:i') ?: '—' }}</dd></div>
                    </dl>

                    @if ($latestScan->sortedFindings()->isEmpty())
                        <p class="mt-4 text-sm text-gray-700 dark:text-gray-300">No obvious issues detected.</p>
                    @else
                        <ul class="mt-4 space-y-2">
                            @foreach ($latestScan->sortedFindings() as $finding)
                                <li class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                                    <div class="flex flex-wrap items-center gap-2 text-xs">
                                        @if ($finding->isDocumentation())
                                            <span class="rounded-full bg-amber-100 px-2 py-0.5 font-medium text-amber-800 dark:bg-amber-900/50 dark:text-amber-200">docs</span>
                                        @else
                                            <span class="rounded-full bg-gray-100 px-2 py-0.5 font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300">{{ $finding->severity }}</span>
                                        @endif
                                        <span class="rounded-md bg-white/70 px-1.5 py-0.5 font-mono text-xs font-semibold text-[#1b1b18] dark:bg-white/10 dark:text-[#EDEDEC]">{{ $finding->rule }}</span>
                                        <a
                                            href="{{ $plugin->githubBlobUrl($finding->repositoryPath(), $latestScan->commit_sha, $finding->line) }}"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="font-mono text-gray-500 underline-offset-2 hover:text-gray-900 hover:underline dark:text-gray-400 dark:hover:text-white"
                                        >{{ $finding->displayPath() }}{{ $finding->line ? ':'.$finding->line : '' }}</a>
                                    </div>
                                    <p class="mt-1.5 text-gray-700 dark:text-gray-300">{{ $finding->description }}</p>
                                    @if ($finding->snippet)
                                        <pre class="mt-1.5 overflow-x-auto rounded-md bg-gray-50 p-2 font-mono text-xs text-gray-700 dark:bg-gray-900 dark:text-gray-300">{{ $finding->snippet }}</pre>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">
                        Automated analysis only — not a security guarantee.
                    </p>
                @endif
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-5 text-sm dark:border-gray-800 dark:bg-[#161615]">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-semibold">AI advisory review</h2>
                        <p class="mt-1 text-xs text-gray-500">Model assessment on top of the deterministic scan. Advisory only.</p>
                    </div>
                    <form method="POST" action="{{ route('admin.plugins.ai-review', $plugin) }}">
                        @csrf
                        <button type="submit" class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">
                            @if ($latestAiReview) Re-run AI review @else Run AI review @endif
                        </button>
                    </form>
                </div>

                @if (!$latestAiReview)
                    <p class="mt-4 rounded-lg bg-indigo-50 p-3 text-indigo-800 dark:bg-indigo-950/40 dark:text-indigo-200">
                        No AI review yet. This calls the configured model with the deterministic findings and a sample of the repository contents.
                    </p>
                @elseif ($latestAiReview->status->value === 'failed')
                    <p class="mt-4 rounded-lg bg-red-50 p-3 text-red-800 dark:bg-red-950/40 dark:text-red-200">
                        The last AI review attempt failed ({{ $latestAiReview->finished_at?->format('M j, Y H:i') ?: 'recently' }}). Re-run to try again.
                    </p>
                @elseif ($latestAiReview->status->value === 'running')
                    <p class="mt-4 rounded-lg bg-amber-50 p-3 text-amber-800 dark:bg-amber-950/40 dark:text-amber-200">AI review in progress…</p>
                @else
                    @php
                        $risk = $latestAiReview->risk_level?->value ?? 'none';
                        $riskColor = match ($risk) {
                            'critical', 'high' => 'bg-red-600 text-white dark:bg-red-500 dark:text-white',
                            'medium' => 'bg-amber-500 text-[#1b1b18] dark:bg-amber-400 dark:text-[#1b1b18]',
                            'low' => 'bg-yellow-500 text-[#1b1b18] dark:bg-yellow-400 dark:text-[#1b1b18]',
                            default => 'bg-emerald-600 text-white dark:bg-emerald-500 dark:text-white',
                        };
                        $rec = $latestAiReview->recommendation?->value ?? null;
                        $recColor = match ($rec) {
                            'avoid' => 'bg-red-600 text-white dark:bg-red-500 dark:text-white',
                            'review' => 'bg-amber-500 text-[#1b1b18] dark:bg-amber-400 dark:text-[#1b1b18]',
                            default => 'bg-emerald-600 text-white dark:bg-emerald-500 dark:text-white',
                        };
                    @endphp

                    <dl class="mt-4 flex flex-wrap gap-x-6 gap-y-2">
                        <div><dt class="text-gray-500 dark:text-gray-400">Risk</dt><dd class="mt-0.5"><span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $riskColor }}">{{ ucfirst($risk) }}</span></dd></div>
                        @if ($rec)
                            <div><dt class="text-gray-500 dark:text-gray-400">Recommendation</dt><dd class="mt-0.5"><span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $recColor }}">{{ ucfirst($rec) }}</span></dd></div>
                        @endif
                        <div><dt class="text-gray-500 dark:text-gray-400">Model</dt><dd class="mt-0.5 font-mono text-xs">{{ $latestAiReview->model ?? '—' }}</dd></div>
                        <div><dt class="text-gray-500 dark:text-gray-400">Commit</dt><dd class="mt-0.5 font-mono text-xs">{{ $latestAiReview->commit_sha }}</dd></div>
                        <div><dt class="text-gray-500 dark:text-gray-400">Reviewed</dt><dd class="mt-0.5">{{ $latestAiReview->finished_at?->format('M j, Y H:i') ?: '—' }}</dd></div>
                    </dl>

                    @if ($latestAiReview->summary)
                        <p class="mt-4 leading-relaxed text-gray-700 dark:text-gray-300">{{ $latestAiReview->summary }}</p>
                    @endif

                    @if (!empty($latestAiReview->concerns))
                        <ul class="mt-3 space-y-1.5">
                            @foreach ($latestAiReview->concerns as $concern)
                                <li class="flex gap-2 text-gray-700 dark:text-gray-300"><span class="text-gray-400">•</span><span>{{ $concern }}</span></li>
                            @endforeach
                        </ul>
                    @endif

                    <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">
                        AI advisory only — automated analysis, not a security guarantee. Never a substitute for the deterministic scan or human review.
                    </p>
                @endif
            </div>
        </div>

        <aside class="space-y-6">
            <div class="rounded-lg border border-gray-200 bg-white p-5 text-sm dark:border-gray-800 dark:bg-[#161615]">
                <h2 class="text-sm font-semibold">Status</h2>
                <form method="POST" action="{{ route('admin.plugins.status', $plugin) }}" class="mt-3 space-y-2">
                    @csrf
                    @foreach (['published' => 'Publish', 'archived' => 'Archive', 'rejected' => 'Reject', 'pending' => 'Set pending'] as $value => $label)
                        <button type="submit" name="status" value="{{ $value }}" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm font-medium hover:bg-gray-100 dark:border-gray-700 dark:hover:bg-gray-900">
                            {{ $label }}
                        </button>
                    @endforeach
                </form>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-5 text-sm dark:border-gray-800 dark:bg-[#161615]">
                <dl class="space-y-2">
                    <div class="flex justify-between"><dt class="text-gray-500">Status</dt><dd class="font-medium">{{ ucfirst($plugin->status->value) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Stars</dt><dd class="font-medium">{{ number_format($plugin->stars_count) }}</dd></div>
                    @if ($plugin->latest_version)<div class="flex justify-between"><dt class="text-gray-500">Version</dt><dd class="font-medium">{{ $plugin->latest_version }}</dd></div>@endif
                    <div class="flex justify-between"><dt class="text-gray-500">Last indexed</dt><dd class="font-medium">{{ $plugin->last_indexed_at?->diffForHumans() ?? 'never' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Last pushed</dt><dd class="font-medium">{{ $plugin->last_pushed_at?->diffForHumans() ?? '—' }}</dd></div>
                </dl>
            </div>

            <form method="POST" action="{{ route('admin.plugins.destroy', $plugin) }}" class="rounded-lg border border-red-200 p-5 dark:border-red-900/60" onsubmit="return confirm('Delete “{{ addslashes($plugin->name) }}”? This cannot be undone.');">
                @csrf
                @method('DELETE')
                <button type="submit" class="w-full rounded-md bg-red-600 px-3 py-2 text-sm font-medium text-white hover:bg-red-700">Delete plugin</button>
            </form>
        </aside>
    </div>
</x-admin-layout>