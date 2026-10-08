@props(['title', 'subtitle' => null, 'close' => 'closeModal', 'wide' => false, 'dismissible' => true, 'cancelLabel' => 'Cancel'])
{{-- A pop-up over a dimmed page. When dismissible, clicking outside or pressing Escape also closes it;
     forms people spend time filling in (Register Tender) pass :dismissible="false" so nothing is lost by accident. --}}
<div class="fixed inset-0 z-50 flex items-end justify-center bg-black/40 p-3 sm:items-center sm:p-4"
     @if ($dismissible) x-data @keydown.escape.window="$wire.{{ $close }}()" @endif>
    <div class="absolute inset-0" @if ($dismissible) wire:click="{{ $close }}" @endif></div>
    <div role="dialog" aria-modal="true" aria-label="{{ $title }}"
         @class(['relative max-h-[92dvh] w-full space-y-4 overflow-y-auto rounded-[20px] bg-surface p-6 shadow-xl', 'max-w-lg' => ! $wide, 'max-w-3xl' => $wide])>
        <div>
            <h2 class="text-lg font-extrabold tracking-tight">{{ $title }}</h2>
            @if ($subtitle) <p class="mt-1 text-[13px] text-muted">{{ $subtitle }}</p> @endif
        </div>
        {{ $slot }}
        @isset($actions)
            <div class="flex flex-wrap justify-between gap-2 pt-1">
                <button type="button" wire:click="{{ $close }}" class="btn btn-outline">{{ $cancelLabel }}</button>
                <div class="flex flex-wrap gap-2">{{ $actions }}</div>
            </div>
        @endisset
    </div>
</div>
