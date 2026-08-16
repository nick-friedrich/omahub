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

                <form action="{{ route('search') }}" method="GET" class="hidden flex-1 justify-end md:flex">
                    <input
                        type="search"
                        name="q"
                        value="{{ request('q') }}"
                        placeholder="Search plugins…"
                        aria-label="Search plugins"
                        class="w-56 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-400 dark:border-gray-700 dark:bg-gray-900"
                    >
                </form>
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