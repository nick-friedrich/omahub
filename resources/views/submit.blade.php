<x-layout :title="'Submit a plugin'">
    <div class="mx-auto max-w-2xl py-6">
        <h1 class="text-2xl font-bold tracking-tight">Submit a plugin</h1>
        <p class="mt-2 text-gray-600 dark:text-gray-300">
            Plugins are imported from public GitHub repositories. To qualify, a repository must contain a
            <code class="rounded bg-gray-100 px-1.5 py-0.5 text-sm dark:bg-gray-800">manifest.json</code>
            at its root, and every entry point listed in it must exist in the repository.
        </p>
        <p class="mt-2 text-gray-600 dark:text-gray-300">
            You'll need to be signed in with your GitHub account to submit — this keeps the registry spam-free. Sign in using the button in the header.
        </p>

        @if (session('status') === 'pending')
            <div class="mt-6 rounded-lg border border-emerald-300 bg-emerald-50 p-4 text-sm text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-100" role="alert">
                <p class="font-semibold">Thanks — your plugin was received.</p>
                <p class="mt-1">It has been imported and recorded. A maintainer will review it, and once approved it will appear in the registry.</p>
            </div>
        @elseif (session('status') === 'import_failed')
            <div class="mt-6 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-100" role="alert">
                <p class="font-semibold">Your submission was recorded, but the import did not complete.</p>
                <p class="mt-1">We could not read a valid plugin manifest from that repository right now. Your submission is still queued for review, so a maintainer can investigate.</p>
            </div>
        @elseif (session('status') === 'received')
            <div class="mt-6 rounded-lg border border-emerald-300 bg-emerald-50 p-4 text-sm text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-100" role="alert">
                <p class="font-semibold">Thanks for your message.</p>
            </div>
        @endif

        <form method="POST" action="{{ route('submit.store') }}" class="mt-6 rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-[#161615]">
            @csrf

            {{-- Honeypot field for bots. Kept off-screen and unfilled by real visitors. --}}
            <div class="hidden" aria-hidden="true">
                <label for="website">Leave this field empty</label>
                <input type="text" id="website" name="website" value="" tabindex="-1" autocomplete="off">
            </div>

            <label for="repository_url" class="block text-sm font-medium">
                Repository URL
            </label>
            <input
                id="repository_url"
                name="repository_url"
                type="url"
                required
                placeholder="https://github.com/owner/plugin-name"
                value="{{ old('repository_url') }}"
                class="mt-2 w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-400 dark:border-gray-700 dark:bg-gray-900"
            >

            @error('repository_url')
                <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror

            <button
                type="submit"
                class="mt-4 rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
            >
                Submit for review
            </button>

            <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                Submissions are imported from GitHub immediately and stay pending until a maintainer approves them. Logging in with GitHub is required, and rate limiting applies.
            </p>
        </form>

        <h2 class="mt-8 text-sm font-semibold">What happens next</h2>
        <ol class="mt-4 space-y-4">
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
                    Submit the repository URL above. The registry imports the latest commit and stores its metadata for review.
                </p>
            </li>
            <li class="flex gap-3">
                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-gray-200 text-xs font-semibold dark:bg-gray-800">3</span>
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    Once a maintainer approves it, the plugin is published and appears on the
                    <a href="{{ route('plugins.index') }}" class="underline underline-offset-4 hover:text-gray-900 dark:hover:text-white">plugin list</a>.
                </p>
            </li>
        </ol>
    </div>
</x-layout>