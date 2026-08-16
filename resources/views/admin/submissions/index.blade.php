<x-admin-layout :title="'Submissions'">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold tracking-tight">Submissions</h1>
        <nav class="flex items-center gap-1 text-sm">
            <a href="{{ route('admin.submissions.index') }}" class="rounded-md px-2 py-1 {{ $currentStatus === null ? 'bg-gray-900 font-medium text-white dark:bg-white dark:text-gray-900' : 'hover:bg-gray-100 dark:hover:bg-gray-900' }}">All</a>
            @foreach ($statuses as $status)
                <a href="{{ route('admin.submissions.index', ['status' => $status->value]) }}" class="rounded-md px-2 py-1 {{ $currentStatus === $status->value ? 'bg-gray-900 font-medium text-white dark:bg-white dark:text-gray-900' : 'hover:bg-gray-100 dark:hover:bg-gray-900' }}">{{ ucfirst($status->value) }}</a>
            @endforeach
        </nav>
    </div>

    @if ($submissions->isEmpty())
        <p class="mt-6 rounded-lg border border-dashed border-gray-300 py-16 text-center text-gray-500 dark:border-gray-700">No submissions in this view.</p>
    @else
        <div class="mt-6 overflow-hidden rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-[#161615]">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-200 dark:border-gray-800">
                    <tr class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        <th class="px-4 py-3 font-medium">#</th>
                        <th class="px-4 py-3 font-medium">Repository</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 font-medium">Plugin</th>
                        <th class="px-4 py-3 font-medium">Submitted</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                    @foreach ($submissions as $submission)
                        <tr>
                            <td class="px-4 py-3 text-gray-500">{{ $submission->id }}</td>
                            <td class="max-w-xs truncate px-4 py-3">
                                <a href="{{ route('admin.submissions.show', $submission) }}" class="hover:underline">{{ $submission->repository_url }}</a>
                            </td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $submission->status->value === 'pending' ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-200' : ($submission->status->value === 'failed' ? 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-200' : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300') }}">
                                    {{ ucfirst($submission->status->value) }}
                                </span>
                            </td>
                            <td class="px-4 py-3">{{ $submission->plugin?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $submission->submitted_at->diffForHumans() }}</td>
                            <td class="px-4 py-3 text-right">
                                @if ($submission->status->value === 'pending')
                                    <div class="flex items-center justify-end gap-2">
                                        <form method="POST" action="{{ route('admin.submissions.approve', $submission) }}">
                                            @csrf
                                            <button type="submit" class="rounded-md bg-emerald-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-emerald-700">Approve</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.submissions.reject', $submission) }}">
                                            @csrf
                                            <button type="submit" class="rounded-md border border-red-300 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50 dark:border-red-800 dark:text-red-300 dark:hover:bg-red-950">Reject</button>
                                        </form>
                                    </div>
                                @endif
                                @if ($submission->status->value === 'failed')
                                    <a href="{{ route('admin.submissions.show', $submission) }}" class="text-xs text-blue-600 hover:underline dark:text-blue-400">Details</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-6">{{ $submissions->links() }}</div>
    @endif
</x-admin-layout>