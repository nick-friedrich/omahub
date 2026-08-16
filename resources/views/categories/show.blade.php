<x-layout :title="$category->name">
    <a href="{{ route('plugins.index') }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
        ← All plugins
    </a>

    <div class="mb-6 mt-4">
        <h1 class="text-2xl font-bold tracking-tight">{{ $category->name }}</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            {{ $plugins->total() }} plugin{{ $plugins->total() === 1 ? '' : 's' }}
        </p>
    </div>

    @if ($plugins->isEmpty())
        <div class="rounded-lg border border-dashed border-gray-300 py-16 text-center dark:border-gray-700">
            <p class="text-gray-500 dark:text-gray-400">No published plugins in this category yet.</p>
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
</x-layout>