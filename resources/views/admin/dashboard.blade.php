<x-admin-layout :title="'Dashboard'">
    <h1 class="text-2xl font-bold tracking-tight">Dashboard</h1>

    <div class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
        @foreach (['Submissions pending' => $submissionCounts['pending'], 'Upcoming plugins' => $pluginCounts['pending'], 'Published plugins' => $pluginCounts['published'], 'Archived plugins' => $pluginCounts['archived']] as $label => $count)
            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-[#161615]">
                <p class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ $label }}</p>
                <p class="mt-1 text-3xl font-bold">{{ $count }}</p>
            </div>
        @endforeach
    </div>

    <section class="mt-10">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold">Awaiting review</h2>
            <a href="{{ route('admin.submissions.index') }}" class="text-sm text-blue-600 hover:underline dark:text-blue-400">View all →</a>
        </div>

        @if ($pendingSubmissions->isEmpty())
            <p class="mt-4 rounded-lg border border-dashed border-gray-300 py-10 text-center text-sm text-gray-500 dark:border-gray-700">
                No submissions are waiting for review.
            </p>
        @else
            <ul class="mt-4 divide-y divide-gray-200 rounded-lg border border-gray-200 bg-white dark:divide-gray-800 dark:border-gray-800 dark:bg-[#161615]">
                @foreach ($pendingSubmissions as $submission)
                    <li class="flex flex-wrap items-center justify-between gap-3 p-4">
                        <div class="min-w-0">
                            <a href="{{ route('admin.submissions.show', $submission) }}" class="font-medium hover:underline">{{ $submission->repository_url }}</a>
                            <p class="text-sm text-gray-500">
                                {{ $submission->submitted_at->diffForHumans() }}
                                @if ($submission->plugin)
                                    · imported as <span class="font-medium text-gray-700 dark:text-gray-300">{{ $submission->plugin->name }}</span>
                                @else
                                    · import failed
                                @endif
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <form method="POST" action="{{ route('admin.submissions.approve', $submission) }}">
                                @csrf
                                <button type="submit" class="rounded-md bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-emerald-700">Approve</button>
                            </form>
                            <form method="POST" action="{{ route('admin.submissions.reject', $submission) }}">
                                @csrf
                                <button type="submit" class="rounded-md border border-red-300 px-3 py-1.5 text-sm font-medium text-red-700 hover:bg-red-50 dark:border-red-800 dark:text-red-300 dark:hover:bg-red-950">Reject</button>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section class="mt-10 grid gap-6 lg:grid-cols-2">
        <div class="rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-[#161615]">
            <h2 class="text-sm font-semibold">Submissions</h2>
            @foreach ([['pending', $submissionCounts['pending']], ['approved', $submissionCounts['approved']], ['rejected', $submissionCounts['rejected']], ['failed', $submissionCounts['failed']]] as [$key, $count])
                <div class="mt-3 flex items-center justify-between text-sm">
                    <span class="text-gray-600 dark:text-gray-300">{{ ucfirst($key) }}</span>
                    <span class="font-medium">{{ $count }}</span>
                </div>
            @endforeach
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-[#161615]">
            <h2 class="text-sm font-semibold">Plugins</h2>
            @foreach ([['published', $pluginCounts['published']], ['pending', $pluginCounts['pending']], ['archived', $pluginCounts['archived']], ['rejected', $pluginCounts['rejected']]] as [$key, $count])
                <div class="mt-3 flex items-center justify-between text-sm">
                    <span class="text-gray-600 dark:text-gray-300">{{ ucfirst($key) }}</span>
                    <span class="font-medium">{{ $count }}</span>
                </div>
            @endforeach
        </div>
    </section>
</x-admin-layout>