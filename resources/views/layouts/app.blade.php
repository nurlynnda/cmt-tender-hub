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
@php $folded = request()->cookie('sidebar') === 'folded'; @endphp
<body class="min-h-screen bg-body font-sans text-ink antialiased"
      x-data="{ drawer: false, folded: @js($folded),
                toggleSidebar() { this.folded = ! this.folded; document.cookie = 'sidebar=' + (this.folded ? 'folded' : 'open') + ';path=/;max-age=31536000;samesite=lax' } }">
<div class="flex min-h-screen">
    {{-- Desktop sidebar (folds to icons; remembered in the "sidebar" cookie) --}}
    <aside data-sidebar="{{ $folded ? 'folded' : 'open' }}" :data-sidebar="folded ? 'folded' : 'open'"
           class="sticky top-0 hidden h-screen shrink-0 border-r border-line bg-surface transition-[width] duration-200 lg:block {{ $folded ? 'is-folded w-[72px]' : 'w-60' }}"
           :class="{ 'is-folded': folded, 'w-[72px]': folded, 'w-60': ! folded }">
        @include('layouts.partials.sidebar', ['desktop' => true])
    </aside>

    {{-- Phone/tablet drawer: never folded --}}
    <div x-show="drawer" x-cloak data-drawer class="fixed inset-0 z-40 lg:hidden">
        <div class="absolute inset-0 bg-black/40" @click="drawer = false"></div>
        <aside class="absolute inset-y-0 left-0 w-64 bg-surface shadow-xl">
            @include('layouts.partials.sidebar')
        </aside>
    </div>

    <main class="flex min-w-0 flex-1 flex-col bg-canvas">
        <nav class="sticky top-0 z-30 flex items-center gap-3.5 border-b border-line bg-surface px-5 py-2.5">
            <button type="button" class="flex items-center gap-2 lg:hidden" @click="drawer = true" aria-label="Open menu">
                <span class="grid h-10 w-10 place-items-center rounded-[11px] text-ink-2 hover:bg-hover">
                    <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                </span>
                <span class="text-base font-extrabold tracking-tight">TenderHub</span>
            </button>
            <div class="ml-auto flex items-center gap-3">
                <button type="button" aria-label="Toggle theme" title="Toggle theme" class="grid h-9 w-9 place-items-center rounded-[11px] text-ink-2 hover:bg-hover"
                        @click="const d = document.documentElement.classList.toggle('dark'); localStorage.theme = d ? 'dark' : 'light'">
                    <x-icon name="moon" class="h-[19px] w-[19px]" />
                </button>
                <livewire:notification-bell />
            </div>
        </nav>
        <div class="p-4 lg:p-7">
            {{ $slot }}
        </div>
    </main>
</div>
</body>
</html>
