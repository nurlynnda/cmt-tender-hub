<div class="relative" x-data="{ open: false }" wire:poll.60s data-unread="{{ $unread }}">
    <button type="button" @click="open = !open" class="relative grid h-9 w-9 place-items-center rounded-[11px] text-ink-2 hover:bg-hover" aria-label="Notifications">
        <x-icon name="bell" class="h-[19px] w-[19px]" />
        @if ($unread)
            <span class="absolute right-px top-0.5 grid h-[17px] min-w-[17px] place-items-center rounded-full border-2 border-surface bg-[#E0483B] px-1 text-[10px] font-bold text-white">{{ $unread }}</span>
        @endif
    </button>
    <div x-show="open" x-cloak @click.outside="open = false"
         class="absolute right-0 z-30 mt-2 w-80 rounded-xl border border-line bg-surface shadow-lg">
        <div class="flex items-center justify-between border-b border-line px-3 py-2 text-sm">
            <span class="font-medium">Notifications</span>
            @if ($unread) <button wire:click="markAllRead" class="text-xs text-muted hover:text-ink">Mark all read</button> @endif
        </div>
        <ul class="max-h-96 overflow-y-auto">
            @forelse ($items as $n)
                <li>
                    <button wire:click="open('{{ $n->id }}')" @class(['block w-full px-3 py-2 text-left text-sm hover:bg-hover', 'font-medium' => ! $n->read_at])>
                        {{ $n->data['message'] }}
                        <span class="block text-xs font-normal text-muted">{{ $n->created_at->diffForHumans() }}</span>
                    </button>
                </li>
            @empty
                <li class="px-3 py-6 text-center text-sm text-muted">You're all caught up.</li>
            @endforelse
        </ul>
    </div>
</div>
