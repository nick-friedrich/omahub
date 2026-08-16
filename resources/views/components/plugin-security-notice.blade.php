@props([
    'plugin',
    'scanned' => false,
])

@if (!$scanned)
    <div role="alert" class="mt-6 flex gap-3 rounded-lg border border-amber-300 bg-amber-50 p-4 dark:border-amber-700 dark:bg-amber-950/40">
        <span class="shrink-0 text-amber-600 dark:text-amber-400" aria-hidden="true">
            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495ZM10 5a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 5Zm0 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd" />
            </svg>
        </span>
        <div class="text-sm text-amber-800 dark:text-amber-200">
            <p class="font-semibold">Not yet security-reviewed</p>
            <p class="mt-1 leading-relaxed">
                This plugin was imported from a public repository and approved for listing, but has
                not been checked for potentially dangerous behavior. Review it before use.
            </p>
        </div>
    </div>
@endif