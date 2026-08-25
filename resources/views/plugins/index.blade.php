<x-layout :title="'Plugins'">
    <div class="flex flex-col gap-8 lg:flex-row">
        {{-- Sidebar: categories --}}
        <aside class="w-full shrink-0 lg:w-52">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Categories</h2>
            <ul class="mt-3 space-y-1 text-sm">
                <li>
                    <a
                        href="{{ route('plugins.index') }}"
                        class="flex items-center justify-between rounded-md px-2 py-1.5 {{ $activeCategory === null ? 'bg-gray-100 font-medium dark:bg-gray-800' : 'hover:bg-gray-100 dark:hover:bg-gray-900' }}"
                    >
                        <span>All</span>
                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ $totalPlugins }}</span>
                    </a>
                </li>
                @foreach ($categories as $category)
                    <li>
                        <a
                            href="{{ route('plugins.index', ['category' => $category->slug]) }}"
                                    class="flex items-center justify-between rounded-md px-2 py-1.5 {{ $activeCategory === $category->slug ? 'bg-gray-100 font-medium dark:bg-gray-800' : 'hover:bg-gray-100 dark:hover:bg-gray-900' }}"
                        >
                            <span>{{ $category->name }}</span>
                            <span class="text-xs text-gray-500 dark:text-gray-400">{{ $category->plugins_count }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </aside>

        {{-- Main column --}}
        <div class="min-w-0 flex-1">
            <div class="mb-4 flex flex-wrap items-baseline gap-x-3 gap-y-1">
                <h1 class="text-xl font-semibold">
                    {{ $activeCategory ? $categories->firstWhere('slug', $activeCategory)?->name ?? 'Plugins' : ($activeTag ? 'Tag: '.$activeTag : 'All plugins') }}
                </h1>
                <span class="text-sm text-gray-500 dark:text-gray-400">{{ $plugins->total() }} plugin{{ $plugins->total() === 1 ? '' : 's' }}</span>
            </div>

            @if ($plugins->isEmpty())
                <div class="rounded-lg border border-dashed border-gray-300 py-16 text-center dark:border-gray-700">
                    <p class="text-gray-500 dark:text-gray-400">No plugins match these filters.</p>
                </div>
            @else
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($plugins as $plugin)
                        <x-plugin-card :plugin="$plugin" :image-url="$previewImages[$plugin->getKey()]" show-preview />
                    @endforeach
                </div>

                <div class="mt-8">
                    {{ $plugins->links() }}
                </div>
            @endif
        </div>
    </div>
</x-layout>
