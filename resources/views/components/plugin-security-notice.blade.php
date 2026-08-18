@props([
    'plugin',
    'scan' => null,
])

@php
    $succeeded = $scan !== null && $scan->status === App\Enums\SecurityScanStatus::Succeeded;
    $risk = $succeeded ? $scan->risk_level : null;
    $findings = $succeeded ? ($scan->findings ?? collect()) : collect();
    $count = $findings->count();

    $isStale = $succeeded
        && $plugin->latest_commit_sha !== null
        && $scan->commit_sha !== $plugin->latest_commit_sha;

    $verdict = 'No obvious issues detected';
    if ($succeeded && $findings->isNotEmpty()) {
        $verdict = match ($risk) {
            'high', 'critical' => 'Potentially dangerous behavior detected',
            default => 'Review recommended',
        };
    }

    $docOnly = $succeeded && $findings->isNotEmpty()
        && $findings->every(fn ($finding) => $finding->isDocumentation());

    $commitClause = $scan !== null && $scan->commit_sha
        ? ' at commit '.substr($scan->commit_sha, 0, 7)
        : '';

    $palette = match (true) {
        $findings->isEmpty() => [
            'panel' => 'border-emerald-200 border-l-4 border-l-emerald-400 bg-emerald-50/70 dark:border-emerald-900 dark:border-l-emerald-500 dark:bg-emerald-950/20',
            'verdict' => 'text-emerald-800 dark:text-emerald-300',
            'icon' => 'text-emerald-600 dark:text-emerald-400',
        ],
        in_array($risk, ['high', 'critical'], true) => [
            'panel' => 'border-red-300 border-l-4 border-l-red-500 bg-red-50/80 dark:border-red-900 dark:border-l-red-500 dark:bg-red-950/30',
            'verdict' => 'text-red-800 dark:text-red-300',
            'icon' => 'text-red-600 dark:text-red-400',
        ],
        default => [
            'panel' => 'border-amber-300 border-l-4 border-l-amber-400 bg-amber-50/80 dark:border-amber-800 dark:border-l-amber-500 dark:bg-amber-950/30',
            'verdict' => 'text-amber-800 dark:text-amber-300',
            'icon' => 'text-amber-600 dark:text-amber-400',
        ],
    };

    $badgeColor = match ($risk) {
        'high', 'critical' => 'bg-red-600 text-white dark:bg-red-500 dark:text-white',
        'medium' => 'bg-amber-500 text-[#1b1b18] dark:bg-amber-400 dark:text-[#1b1b18]',
        'low' => 'bg-yellow-500 text-[#1b1b18] dark:bg-yellow-400 dark:text-[#1b1b18]',
        default => 'bg-emerald-600 text-white dark:bg-emerald-500 dark:text-white',
    };

    $riskTextColor = match ($risk) {
        'high', 'critical' => 'text-red-800 dark:text-red-300',
        'medium' => 'text-amber-800 dark:text-amber-300',
        'low' => 'text-yellow-700 dark:text-yellow-300',
        default => 'text-emerald-800 dark:text-emerald-300',
    };
@endphp

@if (! $succeeded)
    <div role="alert" class="mt-6 flex gap-3 rounded-lg border border-l-4 border-amber-300 border-l-amber-400 bg-amber-50 p-4 dark:border-amber-700 dark:border-l-amber-500 dark:bg-amber-950/40">
        <span class="shrink-0 text-amber-600 dark:text-amber-400" aria-hidden="true">
            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495ZM10 5a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 5Zm0 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd" />
            </svg>
        </span>
        <div class="text-sm text-amber-800 dark:text-amber-200">
            @if ($scan === null)
                <p class="font-semibold">Not yet security-reviewed</p>
                <p class="mt-1 leading-relaxed">
                    This plugin was imported from a public repository and approved for listing, but has
                    not been checked for potentially dangerous behavior. Review it before use.
                </p>
            @else
                <p class="font-semibold">Security scan did not complete</p>
                <p class="mt-1 leading-relaxed">
                    The automated analysis{{ $commitClause }} could not be finished. No conclusion should be drawn from it.
                </p>
            @endif
            <p class="mt-2 text-xs opacity-70">Automated analysis only — not a security guarantee.</p>
        </div>
    </div>
@else
    <details
        class="group mt-6 rounded-lg border text-sm {{ $palette['panel'] }}"
    >
        <summary class="flex cursor-pointer list-none select-none items-center gap-3 p-4 [&::-webkit-details-marker]:hidden [&::marker]:content-none">
            <svg class="h-5 w-5 shrink-0 {{ $palette['icon'] }}" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M9.66 1.22a.5.5 0 0 1 .68 0 9.5 9.5 0 0 0 6.53 1.62c.28.03.46.29.42.56a8.5 8.5 0 0 1-.9 3.16c-.9 1.93-2.3 3.46-3.86 4.63-.44.33-.9.63-1.37.9L9.66 19.3c-.27-.09-.55-.3-.37-.99l.5-3.06c.1-.5 2.16-5.03 2.87-6 .44-.6-.47-1.06-.81-1.47l-2.2-2.63-2.2 2.63c-.34.41-1.25.87-.8 1.47.7.97 2.75 5.5 2.86 6l.5 3.06c.16.67-.1.88-.37.99l-3.53-8.09a17.6 17.6 0 0 1-1.38-.9C4.3 9.8 2.9 8.28 2 6.35c-.54-1.15-.83-2.16-.9-3.16-.04-.27.14-.53.42-.56A8.5 8.5 0 0 0 8.05.95 12.7 12.7 0 0 0 9.66 1.22ZM10.66 10.5l.43.3c.06.04.25.16.5.4l.39.4 1.5 4.1a27 27 0 0 0-.6-.21l-2.3-.62-.22-4.37Z" clip-rule="evenodd" />
            </svg>
            <div class="min-w-0 grow">
                <p class="font-semibold text-gray-900 dark:text-white">Security review</p>
                <p class="mt-0.5 truncate text-xs {{ $palette['verdict'] }}">
                    {{ $verdict }}@if ($count > 0) · {{ $count }} finding{{ $count === 1 ? '' : 's' }}@endif
                </p>
            </div>
            @if ($isStale)
                <span class="hidden shrink-0 rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-800 sm:inline-flex dark:bg-amber-900/50 dark:text-amber-200">
                    Newer commit {{ substr($plugin->latest_commit_sha, 0, 7) }} not yet reviewed
                </span>
            @endif
            <span class="shrink-0 rounded-full px-2.5 py-0.5 text-xs font-medium {{ $badgeColor }}">{{ ucfirst((string) ($risk ?? 'none')) }}</span>
            <svg class="h-4 w-4 shrink-0 text-gray-400 transition-transform duration-200 group-open:rotate-180 dark:text-gray-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" />
            </svg>
        </summary>

        <div class="border-t border-black/10 px-4 pb-4 pt-3 dark:border-white/10">
            <dl class="flex flex-wrap gap-x-6 gap-y-2 text-xs">
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Risk level</dt>
                    <dd class="mt-0.5 font-medium {{ $riskTextColor }}">{{ ucfirst((string) ($risk ?? 'none')) }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Analyzed commit</dt>
                    <dd class="mt-0.5 font-mono" title="{{ $scan->commit_sha }}">{{ substr($scan->commit_sha, 0, 7) }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Scanned</dt>
                    <dd class="mt-0.5">{{ $scan->finished_at?->diffForHumans() ?: '—' }}</dd>
                </div>
            </dl>

            @if ($findings->isEmpty())
                <p class="mt-3 text-gray-700 dark:text-gray-300">
                    No potentially dangerous behavior detected in the analyzed commit.
                </p>
            @else
                @if ($docOnly)
                    <p class="mt-3 text-xs text-gray-600 dark:text-gray-400">
                        Flagged patterns appear only in documentation files (README / docs) — descriptive
                        examples, not executable code.
                    </p>
                @endif
                <ul class="mt-2 divide-y divide-black/10 dark:divide-white/10">
                    @foreach ($findings as $finding)
                        <li class="py-3">
                            <div class="flex flex-wrap items-center gap-2 text-xs">
                                @if ($finding->isDocumentation())
                                    <span class="rounded-full bg-amber-100 px-2 py-0.5 font-medium text-amber-800 dark:bg-amber-900/50 dark:text-amber-200">docs</span>
                                @else
                                    <span class="rounded-full bg-gray-100 px-2 py-0.5 font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300">{{ $finding->severity }}</span>
                                @endif
                                <span class="rounded-md bg-white/70 px-1.5 py-0.5 font-mono text-xs font-semibold text-[#1b1b18] dark:bg-white/10 dark:text-[#EDEDEC]">{{ $finding->rule }}</span>
                                <a
                                    href="{{ $plugin->githubBlobUrl($finding->repositoryPath(), $scan->commit_sha, $finding->line) }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="inline-flex items-center gap-1 font-mono text-gray-500 underline-offset-2 hover:text-gray-900 hover:underline dark:text-gray-400 dark:hover:text-white"
                                    title="Open file at this commit on GitHub"
                                >
                                    {{ $finding->displayPath() }}{{ $finding->line ? ':'.$finding->line : '' }}
                                </a>
                            </div>
                            <p class="mt-1 leading-relaxed text-gray-700 dark:text-gray-300">{{ $finding->description }}</p>
                            @if ($finding->snippet)
                                <pre class="mt-2 overflow-x-auto rounded-md bg-gray-100 p-2 font-mono text-xs leading-relaxed text-gray-800 dark:bg-gray-900 dark:text-gray-300">{{ $finding->snippet }}</pre>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif

            <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                Automated analysis only — not a security guarantee.
            </p>
        </div>
    </details>
@endif