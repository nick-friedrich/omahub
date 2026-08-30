<x-layout
    :title="$plugin->name"
    :description="$plugin->description"
    :image="$previewImage"
    :image-alt="$previewImage ? $plugin->name.' plugin preview' : 'Omahub — a community registry for Omarchy plugins'"
>
    <a href="{{ route('plugins.index') }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
        ← All plugins
    </a>

    @if ($removed)
        <div class="mt-4 rounded-lg border border-l-4 border-amber-300 border-l-amber-400 bg-amber-50 p-4 dark:border-amber-700 dark:border-l-amber-500 dark:bg-amber-950/40">
            <p class="font-semibold text-amber-800 dark:text-amber-200">Repository no longer available</p>
            <p class="mt-1 text-sm leading-relaxed text-amber-800/90 dark:text-amber-200/90">
                The GitHub repository for this plugin has been deleted or made private, so it cannot be installed and is no
                longer listed in the registry. This page is kept for reference.
            </p>
        </div>
    @endif

    <header class="mt-4 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="flex items-start gap-4">
            @if ($plugin->icon_url)
                <img src="{{ $plugin->icon_url }}" alt="" class="h-14 w-14 rounded-lg object-cover">
            @else
                <span class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-gray-200 text-xl font-semibold dark:bg-gray-800" title="{{ $plugin->author_name ?? $plugin->repository_owner }}">
                    {{ strtoupper(substr($plugin->author_name ?? $plugin->repository_owner, 0, 1)) }}
                </span>
            @endif

            <div>
                <h1 class="text-2xl font-bold tracking-tight">{{ $plugin->name }}</h1>
                <p class="mt-1 text-gray-600 dark:text-gray-300">
                    by
                    @if ($plugin->author_url)
                        <a href="{{ $plugin->author_url }}" rel="noopener noreferrer" target="_blank" class="underline underline-offset-4 hover:text-gray-900 dark:hover:text-white">{{ $plugin->author_name ?? $plugin->repository_owner }}</a>
                    @else
                        {{ $plugin->author_name ?? $plugin->repository_owner }}
                    @endif
                </p>
                <p class="mt-2 max-w-2xl text-gray-600 dark:text-gray-300">{{ $plugin->description }}</p>
            </div>
        </div>

        <div class="flex shrink-0 items-center gap-2">
            <a
                href="{{ $plugin->repository_url }}"
                rel="noopener noreferrer"
                target="_blank"
                class="inline-flex items-center gap-2 rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium hover:bg-gray-100 dark:border-gray-700 dark:hover:bg-gray-900"
            >
                <svg class="h-4 w-4" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27s1.36.09 2 .27c1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.01 8.01 0 0 0 16 8c0-4.42-3.58-8-8-8Z" clip-rule="evenodd" />
                </svg>
                GitHub
            </a>
            @if ($plugin->homepage_url)
                <a
                    href="{{ $plugin->homepage_url }}"
                    rel="noopener noreferrer"
                    target="_blank"
                    class="inline-flex items-center rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium hover:bg-gray-100 dark:border-gray-700 dark:hover:bg-gray-900"
                >
                    Homepage
                </a>
            @endif
        </div>
    </header>

    <x-plugin-security-notice :plugin="$plugin" :scan="$latestScan" />

    <x-plugin-ai-notice :plugin="$plugin" :review="$latestAiReview" />

    @unless ($removed)
        <x-install-command command="omarchy plugin add {{ $plugin->repository_url }} --enable" />
    @endunless

    <div class="mt-6 flex flex-wrap gap-2 text-xs">
        @foreach ($plugin->categories as $category)
            <a href="{{ route('plugins.index', ['category' => $category->slug]) }}" class="rounded-full bg-gray-100 px-3 py-1 font-medium hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700">
                {{ $category->name }}
            </a>
        @endforeach
        @foreach ($plugin->tags as $tag)
            <a href="{{ route('plugins.index', ['tag' => $tag->slug]) }}" class="rounded-full border border-gray-300 px-3 py-1 text-gray-600 hover:bg-gray-100 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-900">
                #{{ $tag->name }}
            </a>
        @endforeach
    </div>

    <div class="mt-8 grid grid-cols-1 gap-8 lg:grid-cols-[minmax(0,1fr)_16rem]">
        <article class="prose prose-gray max-w-none dark:prose-invert">
            @if ($readme !== null)
                {!! $readme !!}
            @else
                <p class="text-gray-500 dark:text-gray-400">No README is available for this plugin.</p>
            @endif
        </article>

        <aside class="h-fit space-y-6 rounded-lg border border-gray-200 bg-white p-5 text-sm dark:border-gray-800 dark:bg-[#161615]">
            <dl class="space-y-3">
                <div class="flex items-center justify-between">
                    <dt class="text-gray-500 dark:text-gray-400">Stars</dt>
                    <dd class="font-medium">{{ Illuminate\Support\Number::abbreviate($plugin->stars_count) }}</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-gray-500 dark:text-gray-400">Forks</dt>
                    <dd class="font-medium">{{ Illuminate\Support\Number::abbreviate($plugin->forks_count) }}</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-gray-500 dark:text-gray-400">Open issues</dt>
                    <dd class="font-medium">{{ $plugin->open_issues_count }}</dd>
                </div>
                @if ($plugin->latest_version)
                    <div class="flex items-center justify-between">
                        <dt class="text-gray-500 dark:text-gray-400">Version</dt>
                        <dd class="font-medium">v{{ $plugin->latest_version }}</dd>
                    </div>
                @endif
                @if ($plugin->license)
                    <div class="flex items-center justify-between">
                        <dt class="text-gray-500 dark:text-gray-400">License</dt>
                        <dd class="font-medium">{{ $plugin->license }}</dd>
                    </div>
                @endif
                @if ($plugin->default_branch)
                    <div class="flex items-center justify-between">
                        <dt class="text-gray-500 dark:text-gray-400">Default branch</dt>
                        <dd class="font-mono font-medium">{{ $plugin->default_branch }}</dd>
                    </div>
                @endif
            </dl>

            <div class="border-t border-gray-100 pt-4 dark:border-gray-800">
                <p class="text-xs leading-relaxed text-gray-500 dark:text-gray-400">
                    Last pushed
                    @if ($plugin->last_pushed_at)
                        {{ $plugin->last_pushed_at->diffForHumans() }}.
                    @else
                        unknown.
                    @endif
                    Last indexed
                    @if ($plugin->last_indexed_at)
                        {{ $plugin->last_indexed_at->diffForHumans() }}.
                    @else
                        never.
                    @endif
                    @if ($refreshing)
                        <span
                            class="ml-1 inline-flex items-center gap-1 font-medium text-gray-700 dark:text-gray-300"
                            role="status"
                            aria-live="polite"
                            data-plugin-refresh-status
                            data-refresh-url="{{ route('plugins.refresh-status', $plugin) }}"
                            data-indexed-at="{{ $plugin->last_indexed_at?->toISOString() }}"
                        >
                            <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-current" aria-hidden="true"></span>
                            <span data-refresh-label>Checking GitHub…</span>
                        </span>
                    @endif
                </p>
                @if ($plugin->latest_commit_sha)
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        @ {{ substr($plugin->latest_commit_sha, 0, 7) }}
                    </p>
                @endif
            </div>
        </aside>
    </div>
</x-layout>
