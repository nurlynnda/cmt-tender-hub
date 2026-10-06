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
<body class="min-h-screen bg-body text-ink antialiased flex items-center justify-center p-4">
    <main class="w-full max-w-sm rounded-2xl bg-surface border border-line p-8 shadow-sm">
        <div class="mb-6 flex items-center gap-2">
            <span class="grid h-9 w-9 place-items-center rounded-lg bg-accent font-bold text-accent-ink">T</span>
            <span class="text-lg font-semibold">CMT Tender Hub</span>
        </div>
        {{ $slot }}
    </main>
</body>
</html>
