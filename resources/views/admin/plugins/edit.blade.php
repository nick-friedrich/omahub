<x-admin-layout :title="'Edit '.$plugin->name">
    <a href="{{ route('admin.plugins.index') }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">← Plugins</a>
    <h1 class="mt-1 text-2xl font-bold tracking-tight">Edit “{{ $plugin->name }}”</h1>

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