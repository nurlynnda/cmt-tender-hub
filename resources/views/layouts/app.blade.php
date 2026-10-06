<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'CMT Tender Hub' }} · CMT Tender Hub</title>
    <script>
        if (localStorage.theme === 'dark' || (!localStorage.theme && matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-body font-sans text-ink antialiased" x-data="{ drawer: false }">
<div class="flex min-h-screen">
    {{-- Desktop sidebar --}}
    <aside class="hidden w-60 shrink-0 border-r border-line bg-surface md:block">
        @include('layouts.partials.sidebar')
    </aside>

    {{-- Phone drawer --}}
    <div x-show="drawer" x-cloak class="fixed inset-0 z-40 md:hidden">
        <div class="absolute inset-0 bg-black/40" @click="drawer = false"></div>
        <aside class="absolute inset-y-0 left-0 w-64 bg-surface shadow-xl">
            @include('layouts.partials.sidebar')
        </aside>
    </div>

    <main class="min-w-0 flex-1 bg-canvas">
        <nav class="flex items-center justify-between border-b border-line bg-surface px-4 py-3">
            <button type="button" class="rounded-lg p-2 hover:bg-hover md:hidden" @click="drawer = true" aria-label="Open menu">
                <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none"><path d="M4 6h16M4 12h16M4 18h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
            </button>
            <div class="ml-auto flex items-center gap-2">
                <button type="button" aria-label="Toggle theme" class="rounded-lg p-2 hover:bg-hover"
                        @click="const d = document.documentElement.classList.toggle('dark'); localStorage.theme = d ? 'dark' : 'light'">
                    <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>
                </button>
                <livewire:notification-bell />
            </div>
        </nav>
        <div class="p-4 md:p-6">
            {{ $slot }}
        </div>
    </main>
</div>
</body>
</html>
