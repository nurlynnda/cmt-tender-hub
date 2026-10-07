@props(['title', 'subtitle' => null, 'close' => 'closeModal', 'wide' => false])
{{-- A pop-up over a dimmed page. Clicking outside or pressing Escape calls the component's close action. --}}
<div class="fixed inset-0 z-50 flex items-end justify-center bg-black/40 p-3 sm:items-center sm:p-4" x-data @keydown.escape.window="$wire.{{ $close }}()">
    <div class="absolute inset-0" wire:click="{{ $close }}"></div>
    <div role="dialog" aria-modal="true" aria-label="{{ $title }}"
         @class(['relative max-h-[92vh] w-full space-y-4 overflow-y-auto rounded-[20px] bg-surface p-6 shadow-xl', 'max-w-lg' => ! $wide, 'max-w-3xl' => $wide])>
        <div>
            <h2 class="text-lg font-extrabold tracking-tight">{{ $title }}</h2>
            @if ($subtitle) <p class="mt-1 text-[13px] text-muted">{{ $subtitle }}</p> @endif
        </div>
        {{ $slot }}
        @isset($actions)
            <div class="flex flex-wrap justify-between gap-2 pt-1">
                <button type="button" wire:click="{{ $close }}" class="btn btn-outline">Cancel</button>
                <div class="flex flex-wrap gap-2">{{ $actions }}</div>
            </div>
        @endisset
    </div>
</div>
