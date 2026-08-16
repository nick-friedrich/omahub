<x-layout :title="'Resources'">
    <section class="mx-auto max-w-3xl py-4">
        <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">Resources</h1>
        <p class="mt-3 max-w-xl text-gray-600 dark:text-gray-300">
            Helpful Omarchy links that aren't plugins — the official site and community projects worth knowing.
        </p>

        <div class="mt-8 grid grid-cols-1 gap-4 sm:grid-cols-2">
            @foreach ($resources as $resource)
                <a
                    href="{{ $resource['url'] }}"
                    target="_blank"
                    rel="noopener"
                    class="group flex flex-col rounded-lg border border-gray-200 bg-white p-5 shadow-sm transition hover:border-gray-300 hover:shadow-md dark:border-gray-800 dark:bg-[#161615] dark:hover:border-gray-700"
                >
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="font-semibold group-hover:underline">{{ $resource['title'] }}</h2>
                        <svg class="h-3.5 w-3.5 shrink-0 text-gray-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path d="M11 3a1 1 0 1 0 0 2h2.586l-6.293 6.293a1 1 0 1 0 1.414 1.414L15 6.414V9a1 1 0 1 0 2 0V4a1 1 0 0 0-1-1h-5Z" />
                            <path d="M5 5a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2v-3a1 1 0 1 0-2 0v3H5V7h3a1 1 0 0 0 0-2H5Z" />
                        </svg>
                    </div>
                    <p class="mt-1.5 text-sm text-gray-600 dark:text-gray-300">{{ $resource['description'] }}</p>
                    <span class="mt-auto pt-4 text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ $resource['label'] }}</span>
                </a>
            @endforeach
        </div>

        <p class="mt-8 text-sm text-gray-500 dark:text-gray-400">
            Looking for plugins instead? <a href="{{ route('plugins.index') }}" class="hover:text-gray-700 dark:hover:text-gray-200">Browse the registry →</a>
        </p>
    </section>
</x-layout>