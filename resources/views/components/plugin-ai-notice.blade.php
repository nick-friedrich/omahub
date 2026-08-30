@props([
    'plugin',
    'review' => null,
])

@php
    use App\Enums\AiReviewStatus;

    $succeeded = $review !== null && $review->status === AiReviewStatus::Succeeded;
    $failed = $review !== null && $review->status === AiReviewStatus::Failed;
    $risk = $succeeded ? $review->risk_level?->value : null;
    $recommendation = $succeeded ? $review->recommendation?->value : null;

    $isStale = $succeeded
        && $plugin->latest_commit_sha !== null
        && $review->commit_sha !== $plugin->latest_commit_sha;

    $verdict = 'No obvious issues detected';
    if ($succeeded) {
        $verdict = match ($recommendation) {
            'avoid' => 'Potentially dangerous behavior flagged',
            'review' => 'Review recommended',
            default => 'No obvious issues detected',
        };
    }

    $commitClause = $review !== null && $review->commit_sha
        ? ' at commit '.substr($review->commit_sha, 0, 7)
        : '';

    $palette = match (true) {
        ! $succeeded => [
            'panel' => 'mt-4 border-gray-200 border-l-4 border-l-gray-400 bg-gray-50/70 dark:border-gray-800 dark:border-l-gray-600 dark:bg-gray-950/20',
            'verdict' => 'text-gray-700 dark:text-gray-300',
            'icon' => 'text-gray-500 dark:text-gray-400',
        ],
        in_array($recommendation, ['avoid', 'review'], true) => [
            'panel' => 'mt-4 border-amber-300 border-l-4 border-l-amber-400 bg-amber-50/80 dark:border-amber-800 dark:border-l-amber-500 dark:bg-amber-950/30',
            'verdict' => 'text-amber-800 dark:text-amber-300',
            'icon' => 'text-amber-600 dark:text-amber-400',
        ],
        default => [
            'panel' => 'mt-4 border-emerald-200 border-l-4 border-l-emerald-400 bg-emerald-50/70 dark:border-emerald-900 dark:border-l-emerald-500 dark:bg-emerald-950/20',
            'verdict' => 'text-emerald-800 dark:text-emerald-300',
            'icon' => 'text-emerald-600 dark:text-emerald-400',
        ],
    };

    $badgeColor = match ($risk) {
        'critical', 'high' => 'bg-red-600 text-white dark:bg-red-500 dark:text-white',
        'medium' => 'bg-amber-500 text-[#1b1b18] dark:bg-amber-400 dark:text-[#1b1b18]',
        'low' => 'bg-yellow-500 text-[#1b1b18] dark:bg-yellow-400 dark:text-[#1b1b18]',
        default => 'bg-emerald-600 text-white dark:bg-emerald-500 dark:text-white',
    };

    $recColor = match ($recommendation) {
        'avoid' => 'bg-red-600 text-white dark:bg-red-500 dark:text-white',
        'review' => 'bg-amber-500 text-[#1b1b18] dark:bg-amber-400 dark:text-[#1b1b18]',
        default => 'bg-emerald-600 text-white dark:bg-emerald-500 dark:text-white',
    };
@endphp

@unless ($succeeded)
    <div role="status" class="flex gap-3 rounded-lg border p-4 {{ $palette['panel'] }}" aria-live="polite">
        <span class="shrink-0 {{ $palette['icon'] }}" aria-hidden="true">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z" />
            </svg>
        </span>
        <div class="text-sm {{ $failed ? 'text-red-800 dark:text-red-200' : 'text-gray-700 dark:text-gray-300' }}">
            <p class="font-semibold">{{ $failed ? 'AI review did not complete' : 'AI advisory review' }}</p>
            <p class="mt-1 leading-relaxed">
                @if ($failed)
                    The automated AI review{{ $commitClause }} could not be finished. Treat it as unavailable — the deterministic scan above is still the primary automated check.
                @else
                    This plugin has not been given an AI review yet. It is an optional, advisory second opinion that runs on top of the deterministic scan above.
                @endif
            </p>
            <p class="mt-2 text-xs opacity-70">AI advisory only — not a security guarantee.</p>
        </div>
    </div>
@else
    <details class="group rounded-lg border {{ $palette['panel'] }}">
        <summary class="flex cursor-pointer list-none select-none items-center gap-3 p-4 [&::-webkit-details-marker]:hidden [&::marker]:content-none">
            <svg class="h-5 w-5 shrink-0 {{ $palette['icon'] }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z" />
            </svg>
            <div class="min-w-0 grow">
                <p class="font-semibold {{ $recommendation === 'avoid' ? 'text-red-800 dark:text-red-300' : 'text-gray-900 dark:text-white' }}">AI advisory review</p>
                <p class="mt-0.5 truncate text-xs {{ $palette['verdict'] }}">{{ $verdict }}</p>
                <p class="mt-0.5 truncate text-[11px] text-gray-400 dark:text-gray-500">
                    Language-model assessment{{ $review->model ? ' · '.$review->model : '' }} — advisory only
                </p>
            </div>
            @if ($isStale)
                <span class="hidden shrink-0 rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-800 sm:inline-flex dark:bg-amber-900/50 dark:text-amber-200">
                    Newer commit {{ substr($plugin->latest_commit_sha, 0, 7) }} not yet reviewed
                </span>
            @endif
            @if ($risk)
                <span class="shrink-0 rounded-full px-2.5 py-0.5 text-xs font-medium {{ $badgeColor }}">{{ ucfirst($risk) }}</span>
            @endif
            @if ($recommendation)
                <span class="hidden shrink-0 rounded-full px-2.5 py-0.5 text-xs font-medium {{ $recColor }} sm:inline-flex">Recommends {{ $recommendation }}</span>
            @endif
            <svg class="h-4 w-4 shrink-0 text-gray-400 transition-transform duration-200 group-open:rotate-180 dark:text-gray-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" />
            </svg>
        </summary>

        <div class="border-t border-black/10 px-4 pb-4 pt-3 dark:border-white/10">
            <dl class="flex flex-wrap gap-x-6 gap-y-2 text-xs">
                @if ($risk)
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">AI risk level</dt>
                        <dd class="mt-0.5 font-medium">{{ ucfirst($risk) }}</dd>
                    </div>
                @endif
                @if ($recommendation)
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Recommendation</dt>
                        <dd class="mt-0.5 font-medium capitalize">{{ $recommendation }}</dd>
                    </div>
                @endif
                @if ($review->model)
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Model</dt>
                        <dd class="mt-0.5 font-mono">{{ $review->model }}</dd>
                    </div>
                @endif
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Analyzed commit</dt>
                    <dd class="mt-0.5 font-mono" title="{{ $review->commit_sha }}">{{ substr($review->commit_sha, 0, 7) }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Reviewed</dt>
                    <dd class="mt-0.5">{{ $review->finished_at?->diffForHumans() ?: '—' }}</dd>
                </div>
            </dl>

            @if ($review->summary)
                <p class="mt-3 leading-relaxed text-gray-700 dark:text-gray-300">{{ $review->summary }}</p>
            @endif

            @if (! empty($review->concerns))
                <ul class="mt-2 divide-y divide-black/5 dark:divide-white/5">
                    @foreach ($review->concerns as $concern)
                        <li class="flex gap-2 py-2 text-gray-700 dark:text-gray-300">
                            <span class="shrink-0 text-amber-500" aria-hidden="true">•</span>
                            <span>{{ $concern }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif

            <details class="mt-3 text-xs">
                <summary class="cursor-pointer list-none text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 [&::-webkit-details-marker]:hidden [&::marker]:content-none">
                    How this check works
                </summary>
                <div class="mt-2 space-y-1.5 rounded-lg bg-black/5 p-3 leading-relaxed text-gray-600 dark:bg-white/5 dark:text-gray-400">
                    <p>
                        This review combines the deterministic scan (the rule-based results above) with an
                        independent look at the plugin's code by a language model. The model reads a trimmed sample
                        of the repository's files, the manifest, and the README, then gives a plain-language risk
                        level and a recommendation: <span class="font-medium">install</span> (no notable danger),
                        <span class="font-medium">review</span> (look closer first), or
                        <span class="font-medium">avoid</span> (clearly dangerous).
                    </p>
                    <p>
                        It runs on the same analyzed commit as the deterministic scan and is strictly advisory — it
                        is not a security guarantee and never blocks a plugin by itself. A human moderator still
                        reviews plugins before they are listed.
                    </p>
                </div>
            </details>

            <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                AI advisory only — automated analysis, not a security guarantee.
            </p>
        </div>
    </details>
@endunless
