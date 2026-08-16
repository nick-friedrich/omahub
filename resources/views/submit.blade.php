<x-layout :title="'Submit a plugin'">
    <div class="mx-auto max-w-2xl py-6">
        <h1 class="text-2xl font-bold tracking-tight">Submit a plugin</h1>
        <p class="mt-2 text-gray-600 dark:text-gray-300">
            Plugins are imported from public GitHub repositories. To qualify, a repository must contain a
            <code class="rounded bg-gray-100 px-1.5 py-0.5 text-sm dark:bg-gray-800">manifest.json</code>
            at its root, and every entry point listed in it must exist in the repository.
        </p>

        <ol class="mt-6 space-y-4">
            <li class="flex gap-3">
                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-gray-200 text-xs font-semibold dark:bg-gray-800">1</span>
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    Make sure your plugin repository has a valid
                    <code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs dark:bg-gray-800">manifest.json</code>
                    with at least an <code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs dark:bg-gray-800">id</code>, a
                    <code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs dark:bg-gray-800">name</code>, and
                    <code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs dark:bg-gray-800">entryPoints</code>.
                </p>
            </li>
            <li class="flex gap-3">
                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-gray-200 text-xs font-semibold dark:bg-gray-800">2</span>
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    Open an issue or pull request on the registry, or contact a maintainer with the repository URL, e.g.
                    <code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs dark:bg-gray-800">https://github.com/owner/plugin-name</code>.
                </p>
            </li>
            <li class="flex gap-3">
                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-gray-200 text-xs font-semibold dark:bg-gray-800">3</span>
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    Once reviewed, the plugin is published to the registry and appears on the
                    <a href="{{ route('plugins.index') }}" class="underline underline-offset-4 hover:text-gray-900 dark:hover:text-white">plugin list</a>.
                </p>
            </li>
        </ol>

        <div class="mt-8 rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-[#161615]">
            <h2 class="text-sm font-semibold">For registry maintainers</h2>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                Import or update a plugin from the command line:
            </p>
            <pre class="mt-3 overflow-x-auto rounded-md bg-gray-100 p-3 text-xs dark:bg-gray-900"><code>php artisan plugins:import https://github.com/owner/plugin</code></pre>
        </div>
    </div>
</x-layout>