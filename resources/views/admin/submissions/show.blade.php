<x-admin-layout :title="'Submission #'.$submission->id">
    <div class="flex items-center justify-between gap-3">
        <div>
            <a href="{{ route('admin.submissions.index') }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">← Submissions</a>
            <h1 class="mt-1 text-2xl font-bold tracking-tight">Submission #{{ $submission->id }}</h1>
        </div>
        <span class="rounded-full px-3 py-1 text-xs font-medium {{ $submission->status->value === 'pending' ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-200' : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300' }}">
            {{ ucfirst($submission->status->value) }}
        </span>
    </div>

    <dl class="mt-6 grid gap-4 rounded-lg border border-gray-200 bg-white p-5 text-sm dark:border-gray-800 dark:bg-[#161615] sm:grid-cols-2">
        <div>
            <dt class="text-gray-500 dark:text-gray-400">Repository</dt>
            <dd class="mt-1 font-medium"><a href="{{ $submission->repository_url }}" target="_blank" rel="noopener noreferrer" class="hover:underline">{{ $submission->repository_url }}</a></dd>
        </div>
        <div>
            <dt class="text-gray-500 dark:text-gray-400">Submitted</dt>
            <dd class="mt-1">{{ $submission->submitted_at->format('M j, Y H:i') }}</dd>
        </div>
        @if ($submission->reviewed_at)
            <div>
                <dt class="text-gray-500 dark:text-gray-400">Reviewed</dt>
                <dd class="mt-1">{{ $submission->reviewed_at->format('M j, Y H:i') }}</dd>
            </div>
        @endif
        @if ($submission->plugin)
            <div>
                <dt class="text-gray-500 dark:text-gray-400">Imported plugin</dt>
                <dd class="mt-1"><a href="{{ route('admin.plugins.edit', $submission->plugin) }}" class="text-blue-600 hover:underline dark:text-blue-400">{{ $submission->plugin->name }}</a></dd>
            </div>
        @endif
        @if ($submission->failure_reason)
            <div class="sm:col-span-2">
                <dt class="text-gray-500 dark:text-gray-400">Failure reason</dt>
                <dd class="mt-1 rounded-lg bg-red-50 p-3 font-mono text-xs text-red-800 dark:bg-red-950 dark:text-red-200">{{ $submission->failure_reason }}</dd>
            </div>
        @endif
    </dl>

    @if ($submission->status->value === 'pending')
        <div class="mt-6 flex items-center gap-3">
            <form method="POST" action="{{ route('admin.submissions.approve', $submission) }}">
                @csrf
                <button type="submit" class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">Approve &amp; publish</button>
            </form>
            <form method="POST" action="{{ route('admin.submissions.reject', $submission) }}" class="flex items-center gap-2">
                @csrf
                <input type="text" name="reason" placeholder="Reason (optional)" class="w-56 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm outline-none focus:border-red-400 focus:ring-1 focus:ring-red-400 dark:border-gray-700 dark:bg-gray-900">
                <button type="submit" class="rounded-md border border-red-300 px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50 dark:border-red-800 dark:text-red-300 dark:hover:bg-red-950">Reject</button>
            </form>
        </div>
    @endif
</x-admin-layout>