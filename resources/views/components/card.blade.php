@props(['title' => null, 'subtitle' => null, 'icon' => null])
<section {{ $attributes->merge(['class' => 'min-w-0 rounded-[20px] border border-line bg-surface p-5']) }}>
    @if ($title)
        <div class="mb-4 flex items-center gap-3">
            @if ($icon) <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-accent-tint text-good-ink"><x-icon :name="$icon" class="h-[17px] w-[17px]" /></span> @endif
            <div class="min-w-0 flex-1"><h2 class="font-bold">{{ $title }}</h2>@if ($subtitle)<p class="text-xs text-muted">{{ $subtitle }}</p>@endif</div>
            @isset($actions) <div class="flex shrink-0 flex-wrap gap-2">{{ $actions }}</div> @endisset
        </div>
    @endif
    {{ $slot }}
</section>
