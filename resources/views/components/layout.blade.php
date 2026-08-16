<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @if (isset($title))
            <title>{{ $title }} · {{ config('app.name', 'Omahub') }}</title>
        @else
            <title>{{ config('app.name', 'Omahub') }}</title>
        @endif

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-[#FDFDFC] text-[#1b1b18] antialiased dark:bg-[#0a0a0a] dark:text-[#EDEDEC]">
        <header class="sticky top-0 z-40 border-b border-gray-200 bg-[#FDFDFC]/90 backdrop-blur dark:border-[#3E3E3A] dark:bg-[#0a0a0a]/90">
            <div class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-4 sm:px-6">
                <a href="{{ route('home') }}" class="text-lg font-semibold tracking-tight">
                    Omahub
                </a>

                <nav class="flex items-center gap-1 text-sm sm:gap-2">
                    <a href="{{ route('home') }}" class="rounded-md px-2 py-1.5 hover:bg-gray-100 dark:hover:bg-gray-900">Home</a>
                    <a href="{{ route('plugins.index') }}" class="rounded-md px-2 py-1.5 hover:bg-gray-100 dark:hover:bg-gray-900">Plugins</a>
                    <a href="{{ route('submit') }}" class="rounded-md px-2 py-1.5 hover:bg-gray-100 dark:hover:bg-gray-900">Submit</a>
                </nav>

                <div class="flex items-center gap-3">
                    <form action="{{ route('search') }}" method="GET" class="hidden justify-end lg:flex">
                        <input
                            type="search"
                            name="q"
                            value="{{ request('q') }}"
                            placeholder="Search plugins…"
                            aria-label="Search plugins"
                            class="w-48 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-400 dark:border-gray-700 dark:bg-gray-900"
                        >
                    </form>

                    @auth
                        <div class="flex items-center gap-2">
                            @if (auth()->user()->isAdmin())
                                <a href="{{ route('admin.dashboard') }}" class="rounded-md px-2 py-1.5 text-sm font-medium text-blue-600 hover:bg-gray-100 dark:text-blue-400 dark:hover:bg-gray-900">Admin</a>
                            @endif
                            <div class="flex items-center gap-2" title="{{ auth()->user()->name }}">
                                @if (auth()->user()->avatar_url)
                                    <img src="{{ auth()->user()->avatar_url }}" alt="{{ auth()->user()->name }}" referrerpolicy="no-referrer" class="h-7 w-7 rounded-full">
                                @else
                                    <span class="flex h-7 w-7 items-center justify-center rounded-full bg-gray-200 text-xs font-semibold uppercase dark:bg-gray-800">{{ strtoupper(substr(auth()->user()->github_username, 0, 1)) }}</span>
                                @endif
                                <form method="POST" action="{{ route('auth.logout') }}">
                                    @csrf
                                    <button type="submit" class="rounded-md px-2 py-1.5 text-sm text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-900">Sign out</button>
                                </form>
                            </div>
                        </div>
                    @else
                        <a href="{{ route('auth.github.redirect') }}" class="inline-flex items-center gap-2 rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium hover:bg-gray-100 dark:border-gray-700 dark:hover:bg-gray-900">
                            <svg class="h-4 w-4" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27s1.36.09 2 .27c1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.01 8.01 0 0 0 16 8c0-4.42-3.58-8-8-8Z" clip-rule="evenodd" />
                            </svg>
                            Sign in with GitHub
                        </a>
                    @endauth
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
            {{ $slot }}
        </main>

        <footer class="border-t border-gray-200 py-8 text-center text-sm text-gray-500 dark:border-gray-800 dark:text-gray-400">
            <p>
                <a href="{{ route('plugins.index') }}" class="hover:text-gray-700 dark:hover:text-gray-200">Omahub</a>
                — a community registry for Omarchy plugins.
            </p>
        </footer>
    </body>
</html>