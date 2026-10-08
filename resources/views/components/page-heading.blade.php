@props(['title', 'subtitle' => null])
<header class="flex flex-wrap items-center justify-between gap-4">
    <div class="min-w-0 flex-[1_1_240px]">
        <h1 class="text-2xl font-extrabold leading-tight tracking-tight">{{ $title }}</h1>
        @if ($subtitle) <p class="mt-1 text-[13.5px] text-muted-2">{{ $subtitle }}</p> @endif
    </div>
    @isset($actions) <div class="flex shrink-0 flex-wrap items-center gap-2">{{ $actions }}</div> @endisset
</header>
