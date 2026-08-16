<x-admin-layout :title="'Plugins'">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold tracking-tight">Plugins</h1>
        <nav class="flex items-center gap-1 text-sm">
            <a href="{{ route('admin.plugins.index') }}" class="rounded-md px-2 py-1 {{ $currentStatus === null ? 'bg-gray-900 font-medium text-white dark:bg-white dark:text-gray-900' : 'hover:bg-gray-100 dark:hover:bg-gray-900' }}">All</a>
            @foreach ($statuses as $status)
                <a href="{{ route('admin.plugins.index', ['status' => $status->value]) }}" class="rounded-md px-2 py-1 {{ $currentStatus === $status->value ? 'bg-gray-900 font-medium text-white dark:bg-white dark:text-gray-900' : 'hover:bg-gray-100 dark:hover:bg-gray-900' }}">{{ ucfirst($status->value) }}</a>
            @endforeach
        </nav>
    </div>

    @if ($plugins->isEmpty())
        <p class="mt-6 rounded-lg border border-dashed border-gray-300 py-16 text-center text-gray-500 dark:border-gray-700">No plugins in this view.</p>
    @else
        <div class="mt-6 overflow-hidden rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-[#161615]">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-200 dark:border-gray-800">
                    <tr class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        <th class="px-4 py-3 font-medium">Name</th>
                        <th class="px-4 py-3 font-medium">Repository</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 font-medium">Stars</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                    @foreach ($plugins as $plugin)
                        <tr>
                            <td class="px-4 py-3">
                                <a href="{{ route('admin.plugins.edit', $plugin) }}" class="font-medium hover:underline">{{ $plugin->name }}</a>
                                <div class="text-xs text-gray-500">{{ $plugin->repository_owner }}/{{ $plugin->repository_name }}</div>
                            </td>
                            <td class="max-w-[12rem] truncate px-4 py-3 text-gray-500">{{ $plugin->repository_url }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $plugin->status->value === 'published' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-200' : ($plugin->status->value === 'pending' ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-200' : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300') }}">
                                    {{ ucfirst($plugin->status->value) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-500">{{ Illuminate\Support\Number::abbreviate($plugin->stars_count) }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('admin.plugins.edit', $plugin) }}" class="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-medium hover:bg-gray-100 dark:border-gray-700 dark:hover:bg-gray-900">Edit</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-6">{{ $plugins->links() }}</div>
    @endif
</x-admin-layout>