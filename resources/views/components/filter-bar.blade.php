@props(['count' => 0, 'placeholder' => 'Search', 'debounce' => 300, 'mine' => null, 'mineLabel' => 'My tenders', 'mineModel' => 'mine'])
{{-- Search + optional "My tenders" toggle (label and property adjustable) + a Filters button that folds out the "Filter by column" panel (open when any filter is on) --}}
<div x-data="{ open: {{ $count > 0 ? 'true' : 'false' }} }" class="space-y-2">
    <div class="flex flex-wrap items-center gap-2 rounded-2xl border border-line bg-surface p-2">
        <label class="flex min-w-[200px] flex-1 items-center gap-2 px-2">
            <x-icon name="search" class="h-4 w-4 text-muted-2" />
            <input type="search" wire:model.live.debounce.{{ $debounce }}ms="search" placeholder="{{ $placeholder }}"
                   class="min-w-0 flex-1 bg-transparent py-1.5 text-sm outline-none placeholder:text-muted-2">
        </label>
        @if (! is_null($mine))
            <button type="button" wire:click="$toggle('{{ $mineModel }}')" aria-pressed="{{ $mine ? 'true' : 'false' }}" @class([
                'flex items-center gap-1.5 rounded-[10px] border px-3 py-1.5 text-[12.5px] font-semibold',
                'border-accent bg-accent text-accent-ink' => $mine,
                'border-line-2 text-ink-2 hover:bg-hover' => ! $mine,
            ])><x-icon name="user" class="h-[15px] w-[15px]" /> {{ $mineLabel }}</button>
        @endif
        <button type="button" @click="open = ! open" :aria-expanded="open"
                class="flex items-center gap-1.5 rounded-[10px] border border-line-2 px-3 py-1.5 text-[12.5px] font-semibold text-ink-2 hover:bg-hover">
            <x-icon name="filter" class="h-[15px] w-[15px]" /> Filters
            @if ($count > 0) <span data-filter-count="{{ $count }}" class="rounded-md bg-accent px-1.5 text-[10.5px] font-bold text-accent-ink">{{ $count }}</span> @endif
        </button>
    </div>
    <div x-show="open" x-cloak class="rounded-2xl border border-line bg-surface p-4">
        <div class="mb-3 flex items-center justify-between">
            <p class="text-[11px] font-bold uppercase tracking-[0.5px] text-muted">Filter by column</p>
            <button type="button" wire:click="clearFilters" class="text-xs font-semibold text-muted hover:text-bad-ink">Clear all</button>
        </div>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-6">{{ $slot }}</div>
    </div>
</div>
