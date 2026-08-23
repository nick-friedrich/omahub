<x-admin-layout :title="'Plugins'">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold tracking-tight">Plugins</h1>
        <nav class="flex items-center gap-1 text-sm">
            <a href="{{ route('admin.plugins.index', array_filter(['risk' => $currentRisk, 'q' => $search])) }}" class="rounded-md px-2 py-1 {{ $currentStatus === null ? 'bg-gray-900 font-medium text-white dark:bg-white dark:text-gray-900' : 'hover:bg-gray-100 dark:hover:bg-gray-900' }}">All</a>
            @foreach ($statuses as $status)
                <a href="{{ route('admin.plugins.index', array_filter(['status' => $status->value, 'risk' => $currentRisk, 'q' => $search])) }}" class="rounded-md px-2 py-1 {{ $currentStatus === $status->value ? 'bg-gray-900 font-medium text-white dark:bg-white dark:text-gray-900' : 'hover:bg-gray-100 dark:hover:bg-gray-900' }}">{{ ucfirst($status->value) }}</a>
            @endforeach
        </nav>
    </div>

    <div class="mt-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <form method="GET" action="{{ route('admin.plugins.index') }}" role="search" class="flex max-w-md gap-2">
            @if ($currentStatus !== null)
                <input type="hidden" name="status" value="{{ $currentStatus }}">
            @endif
            @if ($currentRisk !== null)
                <input type="hidden" name="risk" value="{{ $currentRisk }}">
            @endif
            <input
                type="search"
                name="q"
                value="{{ $search }}"
                placeholder="Search by name, owner, repo, URL…"
                aria-label="Search plugins"
                class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-400 dark:border-gray-700 dark:bg-gray-900"
            >
            <button type="submit" class="rounded-md bg-omarchy px-4 py-2 text-sm font-medium text-black transition hover:brightness-95">Search</button>
            @if ($search !== '')
                <a href="{{ route('admin.plugins.index', array_filter(['status' => $currentStatus, 'risk' => $currentRisk])) }}" class="inline-flex items-center rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-900">Clear</a>
            @endif
        </form>

        <nav class="flex flex-wrap items-center gap-1 text-sm" aria-label="Filter by risk">
            <span class="mr-1 text-xs font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">Risk</span>
            <a href="{{ route('admin.plugins.index', array_filter(['status' => $currentStatus, 'q' => $search])) }}" class="rounded-md px-2 py-1 {{ $currentRisk === null ? 'bg-gray-900 font-medium text-white dark:bg-white dark:text-gray-900' : 'hover:bg-gray-100 dark:hover:bg-gray-900' }}">All</a>
            @foreach ($riskLevels as $level)
                <a href="{{ route('admin.plugins.index', array_filter(['status' => $currentStatus, 'q' => $search, 'risk' => $level->value])) }}" class="rounded-md px-2 py-1 {{ $currentRisk === $level->value ? 'bg-gray-900 font-medium text-white dark:bg-white dark:text-gray-900' : 'hover:bg-gray-100 dark:hover:bg-gray-900' }}">{{ $level->label() }}</a>
            @endforeach
            <a href="{{ route('admin.plugins.index', array_filter(['status' => $currentStatus, 'q' => $search, 'risk' => 'unscanned'])) }}" class="rounded-md px-2 py-1 {{ $currentRisk === 'unscanned' ? 'bg-gray-900 font-medium text-white dark:bg-white dark:text-gray-900' : 'hover:bg-gray-100 dark:hover:bg-gray-900' }}">Unscanned</a>
        </nav>
    </div>

    @if ($plugins->isEmpty())
        <p class="mt-6 rounded-lg border border-dashed border-gray-300 py-16 text-center text-gray-500 dark:border-gray-700">
            {{ $search !== '' ? "No plugins match “{$search}”." : 'No plugins in this view.' }}
        </p>
    @else
        <div class="mt-6 overflow-hidden rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-[#161615]">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-200 dark:border-gray-800">
                    <tr class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        <th class="px-4 py-3 font-medium">Name</th>
                        <th class="px-4 py-3 font-medium">Repository</th>
                        <th class="px-4 py-3 font-medium">Risk</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 font-medium">Stars</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                    @foreach ($plugins as $plugin)
                        @php
                            $scan = $plugin->latestSecurityScan;
                            $succeeded = $scan?->status === App\Enums\SecurityScanStatus::Succeeded;
                            $risk = $succeeded ? ($scan->risk_level ?? 'none') : null;

                            [$riskBadgeClass, $riskLabel] = match (true) {
                                $scan?->status === App\Enums\SecurityScanStatus::Running => ['bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-200', 'Scanning…'],
                                ! $succeeded => ['bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400', 'Not scanned'],
                                $risk === 'high' || $risk === 'critical' => ['bg-red-600 text-white dark:bg-red-500 dark:text-white', ucfirst((string) $risk)],
                                $risk === 'medium' => ['bg-amber-500 text-[#1b1b18] dark:bg-amber-400 dark:text-[#1b1b18]', 'Medium'],
                                $risk === 'low' => ['bg-yellow-500 text-[#1b1b18] dark:bg-yellow-400 dark:text-[#1b1b18]', 'Low'],
                                default => ['bg-emerald-600 text-white dark:bg-emerald-500 dark:text-white', 'None'],
                            };

                            $riskTitle = $succeeded
                                ? 'Latest scan: '.ucfirst((string) $risk).' · commit '.substr((string) $scan->commit_sha, 0, 7)
                                : ($scan ? 'Latest scan did not complete' : 'Not yet security-reviewed');
                        @endphp
                        <tr>
                            <td class="px-4 py-3">
                                <a href="{{ route('admin.plugins.edit', $plugin) }}" class="font-medium hover:underline">{{ $plugin->name }}</a>
                                <div class="text-xs text-gray-500">{{ $plugin->repository_owner }}/{{ $plugin->repository_name }}</div>
                            </td>
                            <td class="max-w-[12rem] truncate px-4 py-3 text-gray-500">{{ $plugin->repository_url }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $riskBadgeClass }}" title="{{ $riskTitle }}">{{ $riskLabel }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $plugin->status->value === 'published' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-200' : ($plugin->status->value === 'pending' ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-200' : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300') }}">
                                    {{ ucfirst($plugin->status->value) }}
                                </span>
                                @if ($plugin->isRepositoryRemoved())
                                    <span class="ml-1 rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700 dark:bg-red-900/50 dark:text-red-300">Repo deleted</span>
                                @endif
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
