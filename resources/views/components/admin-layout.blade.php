<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @if (isset($title))
            <title>{{ $title }} · {{ config('app.name', 'Omahub') }} Admin</title>
        @else
            <title>{{ config('app.name', 'Omahub') }} Admin</title>
        @endif

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    </head>
    <body class="min-h-screen bg-[#FDFDFC] text-[#1b1b18] antialiased dark:bg-[#0a0a0a] dark:text-[#EDEDEC]">
        <header class="sticky top-0 z-40 border-b border-gray-200 bg-[#FDFDFC]/90 backdrop-blur dark:border-[#3E3E3A] dark:bg-[#0a0a0a]/90">
            <div class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-4 sm:px-6">
                <div class="flex items-center gap-3">
                    <a href="{{ route('admin.dashboard') }}" class="flex shrink-0 items-center" aria-label="Omahub home">
                        <img src="{{ asset('wordmark.png') }}" alt="Omahub" class="h-9 w-auto sm:h-10">
                    </a>
                    <span class="rounded-md bg-gray-900 px-1.5 py-0.5 text-xs font-semibold uppercase tracking-wide text-white dark:bg-white dark:text-gray-900">Admin</span>
                </div>

                <nav class="flex items-center gap-1 text-sm sm:gap-2">
                    <a href="{{ route('admin.dashboard') }}" class="rounded-md px-2 py-1.5 hover:bg-gray-100 dark:hover:bg-gray-900">Dashboard</a>
                    <a href="{{ route('admin.submissions.index') }}" class="rounded-md px-2 py-1.5 hover:bg-gray-100 dark:hover:bg-gray-900">Submissions</a>
                    <a href="{{ route('admin.plugins.index') }}" class="rounded-md px-2 py-1.5 hover:bg-gray-100 dark:hover:bg-gray-900">Plugins</a>
                </nav>

                <div class="flex items-center gap-3">
                    <a href="{{ route('home') }}" target="_blank" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">View site ↗</a>
                    <form method="POST" action="{{ route('auth.logout') }}">
                        @csrf
                        <button type="submit" class="rounded-md px-2 py-1.5 text-sm text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-900">Sign out</button>
                    </form>
                </div>
            </div>
        </header>

        @if (session('status'))
            <div class="mx-auto mt-6 max-w-6xl rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-100" role="alert">
                {{ session('status') }}
            </div>
        @elseif (session('error'))
            <div class="mx-auto mt-6 max-w-6xl rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-900 dark:border-red-900 dark:bg-red-950 dark:text-red-100" role="alert">
                {{ session('error') }}
            </div>
        @endif

        <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
            {{ $slot }}
        </main>
    </body>
</html>