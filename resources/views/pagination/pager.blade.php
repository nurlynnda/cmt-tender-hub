@if ($paginator->hasPages())
    @php
        $page = $paginator->getPageName();
        $btn = 'grid h-8 min-w-8 place-items-center rounded-[9px] border px-2.5 text-[12.5px] font-semibold disabled:opacity-40';
    @endphp
    <nav class="flex items-center gap-1" aria-label="Pages">
        <button type="button" wire:click="previousPage('{{ $page }}')" @disabled($paginator->onFirstPage()) class="{{ $btn }} border-line-2 text-ink-2 hover:bg-hover">Prev</button>
        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="px-1 text-muted">…</span>
            @endif
            @if (is_array($element))
                @foreach ($element as $number => $url)
                    <button type="button" wire:key="page-{{ $number }}" wire:click="gotoPage({{ $number }}, '{{ $page }}')"
                            @if ($number === $paginator->currentPage()) aria-current="page" @endif
                            @class([$btn, 'border-chip bg-chip text-chip-ink' => $number === $paginator->currentPage(), 'border-line-2 text-ink-2 hover:bg-hover' => $number !== $paginator->currentPage()])>{{ $number }}</button>
                @endforeach
            @endif
        @endforeach
        <button type="button" wire:click="nextPage('{{ $page }}')" @disabled(! $paginator->hasMorePages()) class="{{ $btn }} border-line-2 text-ink-2 hover:bg-hover">Next</button>
    </nav>
@endif
