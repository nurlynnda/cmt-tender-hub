@if ($paginator->hasPages())
    @php
        $page = $paginator->getPageName();
        $btn = 'min-w-8 rounded-lg border border-line px-2.5 py-1 text-sm disabled:opacity-40';
    @endphp
    <nav class="flex items-center gap-1" aria-label="Pages">
        <button type="button" wire:click="previousPage('{{ $page }}')" @disabled($paginator->onFirstPage()) class="{{ $btn }} hover:bg-hover">Prev</button>
        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="px-1 text-muted">…</span>
            @endif
            @if (is_array($element))
                @foreach ($element as $number => $url)
                    <button type="button" wire:key="page-{{ $number }}" wire:click="gotoPage({{ $number }}, '{{ $page }}')"
                            @if ($number === $paginator->currentPage()) aria-current="page" @endif
                            @class([$btn, 'bg-chip text-chip-ink' => $number === $paginator->currentPage(), 'hover:bg-hover' => $number !== $paginator->currentPage()])>{{ $number }}</button>
                @endforeach
            @endif
        @endforeach
        <button type="button" wire:click="nextPage('{{ $page }}')" @disabled(! $paginator->hasMorePages()) class="{{ $btn }} hover:bg-hover">Next</button>
    </nav>
@endif
