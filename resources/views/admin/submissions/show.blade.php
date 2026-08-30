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

    <div class="mt-6 rounded-lg border border-gray-200 bg-white p-5 text-sm dark:border-gray-800 dark:bg-[#161615]">
        <h2 class="text-sm font-semibold">Security &amp; AI review</h2>
        <p class="mt-1 text-xs text-gray-500">Deterministic scan and advisory AI review for the imported plugin. Triggered manually — both are advisory only.</p>

        @if ($submission->plugin)
            {{-- Deterministic scan --}}
            <div class="mt-4 flex items-start justify-between gap-3 border-t border-gray-100 pt-4 dark:border-gray-800">
                <div class="min-w-0">
                    <p class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Deterministic scan</p>
                    @if ($latestScan && $latestScan->status->value !== 'failed')
                        @php
                            $risk = $latestScan->risk_level ?? 'none';
                            $riskColor = match ($risk) {
                                'critical', 'high' => 'bg-red-600 text-white dark:bg-red-500 dark:text-white',
                                'medium' => 'bg-amber-500 text-[#1b1b18] dark:bg-amber-400 dark:text-[#1b1b18]',
                                'low' => 'bg-yellow-500 text-[#1b1b18] dark:bg-yellow-400 dark:text-[#1b1b18]',
                                default => 'bg-emerald-600 text-white dark:bg-emerald-500 dark:text-white',
                            };
                        @endphp
                        <div class="mt-1.5 flex flex-wrap items-center gap-2">
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $riskColor }}">{{ ucfirst($risk) }}</span>
                            <span class="font-mono text-xs text-gray-500 dark:text-gray-400">{{ $latestScan->commit_sha }}</span>
                            <span class="text-xs text-gray-500 dark:text-gray-400">{{ $latestScan->findings()->count() }} finding(s)</span>
                            @if ($latestScan->finished_at)<span class="text-xs text-gray-400">— {{ $latestScan->finished_at->format('M j, Y H:i') }}</span>@endif
                        </div>
                    @else
                        <p class="mt-1 text-amber-800 dark:text-amber-200">Not scanned yet, or the last scan failed. Run it to check for potentially dangerous behavior.</p>
                    @endif
                    <p class="mt-2 text-xs text-gray-400">Deterministic, rule-based analysis only — not a security guarantee.</p>
                </div>
                <form method="POST" action="{{ route('admin.submissions.scan', $submission) }}">
                    @csrf
                    <button type="submit" class="shrink-0 rounded-md bg-amber-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-amber-700">@if ($latestScan) Rescan @else Run scan @endif</button>
                </form>
            </div>

            {{-- AI review --}}
            <div class="mt-4 flex items-start justify-between gap-3 border-t border-gray-100 pt-4 dark:border-gray-800">
                <div class="min-w-0">
                    <p class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">AI advisory review</p>
                    @if ($latestAiReview && $latestAiReview->status->value === 'succeeded')
                        @php
                            $risk = $latestAiReview->risk_level?->value ?? 'none';
                            $rec = $latestAiReview->recommendation?->value ?? null;
                            $riskColor = match ($risk) {
                                'critical', 'high' => 'bg-red-600 text-white dark:bg-red-500 dark:text-white',
                                'medium' => 'bg-amber-500 text-[#1b1b18] dark:bg-amber-400 dark:text-[#1b1b18]',
                                'low' => 'bg-yellow-500 text-[#1b1b18] dark:bg-yellow-400 dark:text-[#1b1b18]',
                                default => 'bg-emerald-600 text-white dark:bg-emerald-500 dark:text-white',
                            };
                        @endphp
                        <div class="mt-1.5 flex flex-wrap items-center gap-2">
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $riskColor }}">{{ ucfirst($risk) }}</span>
                            @if ($rec)<span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300">{{ ucfirst($rec) }}</span>@endif
                            <span class="font-mono text-xs text-gray-500 dark:text-gray-400">{{ $latestAiReview->model ?? '—' }}</span>
                            @if ($latestAiReview->finished_at)<span class="text-xs text-gray-400">— {{ $latestAiReview->finished_at->format('M j, Y H:i') }}</span>@endif
                        </div>
                        @if ($latestAiReview->summary)
                            <p class="mt-2 text-gray-700 dark:text-gray-300">{{ $latestAiReview->summary }}</p>
                        @endif
                        @if (!empty($latestAiReview->concerns))
                            <ul class="mt-1.5 space-y-1">
                                @foreach ($latestAiReview->concerns as $concern)
                                    <li class="flex gap-2 text-gray-600 dark:text-gray-400"><span class="text-gray-400">•</span><span>{{ $concern }}</span></li>
                                @endforeach
                            </ul>
                        @endif
                    @elseif ($latestAiReview && $latestAiReview->status->value === 'failed')
                        <p class="mt-1 text-red-700 dark:text-red-300">The last AI review attempt failed. Re-run to try again.</p>
                    @else
                        <p class="mt-1 text-amber-800 dark:text-amber-200">No AI review yet. Runs on top of the deterministic scan and a sample of the repository contents.</p>
                    @endif
                    <p class="mt-2 text-xs text-gray-400">AI advisory only — never a substitute for the deterministic scan or human review.</p>
                </div>
                <form method="POST" action="{{ route('admin.submissions.ai-review', $submission) }}">
                    @csrf
                    <button type="submit" class="shrink-0 rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">@if ($latestAiReview) Re-run AI review @else Run AI review @endif</button>
                </form>
            </div>
        @else
            <p class="mt-3 rounded-lg bg-amber-50 p-3 text-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                This submission has no imported plugin, so it cannot be scanned or AI-reviewed.
            </p>
        @endif
    </div>

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