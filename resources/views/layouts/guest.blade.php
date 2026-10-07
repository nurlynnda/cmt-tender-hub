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
<body class="grid min-h-screen place-items-center bg-canvas p-4 font-sans text-ink antialiased">
    <main data-auth-card class="w-full max-w-sm rounded-[20px] border border-line bg-surface p-7 shadow-[0_6px_18px_var(--shadow-soft)]">
        <div class="mb-6 flex items-center gap-2.5">
            <span class="grid h-[34px] w-[34px] place-items-center rounded-[10px] bg-chip text-[15px] font-extrabold text-accent">T</span>
            <span class="text-lg font-extrabold tracking-tight">TenderHub</span>
            <span class="ml-auto text-[11px] font-semibold text-muted-2">CMT</span>
        </div>
        {{ $slot }}
    </main>
</body>
</html>
