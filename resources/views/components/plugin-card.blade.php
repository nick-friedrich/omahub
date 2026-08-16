@props([
    'plugin',
])

@php
    $iconUrl = $plugin->icon_url;
    $author = $plugin->author_name ?? $plugin->repository_owner;
    $pushedAt = $plugin->last_pushed_at;
@endphp

<a
    href="{{ route('plugins.show', $plugin->slug) }}"
    class="group flex flex-col rounded-lg border border-gray-200 bg-white p-5 shadow-sm transition hover:border-gray-300 hover:shadow-md dark:border-gray-800 dark:bg-[#161615] dark:hover:border-gray-700"
>
    <div class="flex items-start gap-3">
        @if ($iconUrl)
            <img src="{{ $iconUrl }}" alt="" class="h-10 w-10 rounded-md object-cover" loading="lazy">
        @else
            <span class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-md bg-ink dark:bg-black" title="{{ $author }}" aria-hidden="true">
                <img src="{{ asset('images/omarchy-mark-lime.png') }}" alt="" class="h-5 w-5" loading="lazy">
            </span>
        @endif

        <div class="min-w-0">
            <h3 class="truncate font-semibold group-hover:underline">{{ $plugin->name }}</h3>
            <p class="text-xs text-gray-500 dark:text-gray-400">
                by {{ $author }}
            </p>
        </div>
    </div>

    <p class="mt-3 line-clamp-2 text-sm text-gray-600 dark:text-gray-300">{{ $plugin->description }}</p>

    <div class="mt-auto flex items-center gap-3 pt-4 text-xs text-gray-500 dark:text-gray-400">
        @if ($plugin->stars_count > 0)
            <span class="inline-flex items-center gap-1" title="{{ $plugin->stars_count }} stars">
                <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M10.868 2.884c-.321-.772-1.415-.772-1.736 0l-1.83 4.401-4.753.381c-.833.067-1.171 1.107-.536 1.651l3.62 3.102-1.106 4.637c-.194.813.691 1.456 1.405 1.02L10 15.591l4.069 2.485c.713.436 1.598-.207 1.404-1.02l-1.106-4.637 3.62-3.102c.635-.544.297-1.584-.536-1.65l-4.752-.382-1.831-4.401Z" clip-rule="evenodd" />
                </svg>
                {{ Illuminate\Support\Number::abbreviate($plugin->stars_count) }}
            </span>
        @endif

        @if ($plugin->latest_version)
            <span class="rounded bg-gray-100 px-1.5 py-0.5 text-xs font-medium dark:bg-gray-800">v{{ $plugin->latest_version }}</span>
        @endif

        @if ($pushedAt)
            <span class="ml-auto text-xs">Updated {{ $pushedAt->diffForHumans() }}</span>
        @endif
    </div>
</a>