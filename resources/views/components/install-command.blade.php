@props([
    'command',   // the full command line to display and copy, e.g. "omarchy plugin add <url> --enable"
    'label' => 'Install',
])

<div class="install-command mt-6 rounded-lg border border-gray-200 dark:border-gray-800">
    <div class="flex items-center justify-between rounded-t-lg border-b border-gray-800 bg-[#0a0a0a] px-4 py-2 dark:border-gray-800">
        <span class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ $label }}</span>
        <button
            type="button"
            data-copy-command
            class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-xs font-medium text-gray-300 hover:bg-gray-800 hover:text-white"
        >
            <svg data-copy-icon class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path d="M7 3.5A1.5 1.5 0 0 1 8.5 2h6A1.5 1.5 0 0 1 16 3.5v7.5a1.5 1.5 0 0 1-1.5 1.5H12V7A2.5 2.5 0 0 0 9.5 4.5H7v-1Z" />
                <path d="M4.5 7h6A1.5 1.5 0 0 1 12 8.5v8A1.5 1.5 0 0 1 10.5 18h-6A1.5 1.5 0 0 1 3 16.5v-8A1.5 1.5 0 0 1 4.5 7Z" />
            </svg>
            <svg data-copy-success-icon class="hidden h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
            </svg>
            <span data-copy-label>Copy</span>
        </button>
    </div>
    <code class="block overflow-x-auto bg-[#161615] px-4 py-3 text-sm text-gray-200">
        $ {{ $command }}
    </code>
</div>