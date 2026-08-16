<x-layout :title="'Search: '.($query !== '' ? $query : 'all')">
    <h1 class="text-xl font-semibold">Search</h1>
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
        {{ $query !== '' ? "Results for “{$query}”" : 'All plugins' }} — {{ $plugins->total() }} found
    </p>

    <form action="{{ route('search') }}" method="GET" class="mt-4 flex max-w-md gap-2">
        <input
            type="search"
            name="q"
            value="{{ $query }}"
            placeholder="Search plugins…"
            aria-label="Search plugins"
            class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-400 dark:border-gray-700 dark:bg-gray-900"
        >
        <button type="submit" class="rounded-md bg-omarchy px-4 py-2 text-sm font-medium text-black transition hover:brightness-95">
            Search
        </button>
    </form>

    <div class="mt-6">
        @if ($plugins->isEmpty())
            <div class="rounded-lg border border-dashed border-gray-300 py-16 text-center dark:border-gray-700">
                <p class="text-gray-500 dark:text-gray-400">No plugins match “{{ $query }}”.</p>
            </div>
        @else
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($plugins as $plugin)
                    <x-plugin-card :plugin="$plugin" />
                @endforeach
            </div>

            <div class="mt-8">
                {{ $plugins->links() }}
            </div>
        @endif
    </div>
</x-layout>