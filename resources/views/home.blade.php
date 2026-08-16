<x-layout :title="'Browse plugins'">
    <section class="py-10 text-center">
        <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">Discover Omarchy plugins</h1>
        <p class="mx-auto mt-3 max-w-xl text-gray-600 dark:text-gray-300">
            A community registry of plugins for Omarchy, curated from GitHub repos with a
            <code class="rounded bg-gray-100 px-1.5 py-0.5 text-sm dark:bg-gray-800">manifest.json</code>.
        </p>

        @if (session('status') === 'github_auth_unconfigured')
            <p class="mx-auto mt-4 max-w-xl rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-100">
                GitHub sign in isn't configured yet. Set <code>GITHUB_CLIENT_ID</code>, <code>GITHUB_CLIENT_SECRET</code> in your <code>.env</code>.
            </p>
        @endif

        <form action="{{ route('search') }}" method="GET" class="mx-auto mt-6 flex max-w-md gap-2">
            <input
                type="search"
                name="q"
                placeholder="Search plugins…"
                aria-label="Search plugins"
                class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-400 dark:border-gray-700 dark:bg-gray-900"
            >
            <button type="submit" class="rounded-md bg-omarchy px-4 py-2 text-sm font-medium text-black transition hover:brightness-95">
                Search
            </button>
        </form>
    </section>

    @if ($recentlyUpdated->isEmpty())
        <div class="rounded-lg border border-dashed border-gray-300 py-16 text-center dark:border-gray-700">
            <p class="text-gray-500 dark:text-gray-400">No plugins have been published yet. Check back soon!</p>
        </div>
    @else
        <section>
            <div class="mb-4 flex items-baseline justify-between">
                <h2 class="text-lg font-semibold">Recently updated</h2>
                <a href="{{ route('plugins.index') }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">View all →</a>
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($recentlyUpdated as $plugin)
                    <x-plugin-card :plugin="$plugin" />
                @endforeach
            </div>
        </section>

        <section class="mt-10">
            <div class="mb-4 flex items-baseline justify-between">
                <h2 class="text-lg font-semibold">Newest</h2>
                <a href="{{ route('plugins.index') }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">View all →</a>
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($newest as $plugin)
                    <x-plugin-card :plugin="$plugin" />
                @endforeach
            </div>
        </section>

        <section class="mt-10">
            <div class="mb-4 flex items-baseline justify-between">
                <h2 class="text-lg font-semibold">Popular</h2>
                <a href="{{ route('plugins.index') }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">View all →</a>
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($popular as $plugin)
                    <x-plugin-card :plugin="$plugin" />
                @endforeach
            </div>
        </section>
    @endif
</x-layout>