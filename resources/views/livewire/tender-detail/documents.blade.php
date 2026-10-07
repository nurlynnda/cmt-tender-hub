@php
    $editable = $canEdit && ! $tender->isLocked();
    $total = $documents->count();
    $pct = $total ? (int) round($doneCount * 100 / $total) : 0;
@endphp
<x-card title="Documents" subtitle="Assigned to {{ $tender->pic->name }} (PIC)" icon="check">
    <x-slot:actions>
        @if ($editable)
            <button type="button" wire:click="openModal('bulk-docs')" class="btn btn-outline">Bulk Add</button>
        @endif
    </x-slot:actions>

    <div class="mb-4 flex items-center gap-3 text-[13px]">
        <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-hover"><div class="h-full bg-good-ink" style="width: {{ $pct }}%"></div></div>
        <span class="whitespace-nowrap font-semibold">{{ $doneCount }} / {{ $total }} done</span>
    </div>

    @if ($notice) <p class="mb-3 text-[13px] font-semibold text-good-ink">{{ $notice }}</p> @endif
    @if ($tender->isLocked())
        <p class="mb-3 rounded-xl bg-subtle px-3 py-2 text-[13px] text-muted">This tender is {{ $tender->status->label() }} — documents and costing are locked from further edits.</p>
    @endif

    <ul class="divide-y divide-line">
        @foreach ($documents as $doc)
            <li wire:key="doc-{{ $doc->id }}" class="flex items-center gap-3 py-2.5 text-[13.5px]">
                <input type="checkbox" @checked($doc->is_done) @disabled(! $editable) class="h-4 w-4 shrink-0 accent-[var(--accent-solid)]"
                       wire:click="toggleDocument({{ $doc->id }})" aria-label="Tick {{ $doc->name }}">
                <div class="min-w-0 flex-1">
                    <p @class(['font-medium', 'line-through text-muted' => $doc->is_done])>{{ $doc->name }}</p>
                    <p class="text-xs text-muted">
                        {{ $doc->is_done ? 'Ticked by '.($doc->doneBy?->name ?? 'someone').' · '.$doc->done_at?->setTimezone('Asia/Kuala_Lumpur')->format('d M Y') : 'Tick when prepared' }}
                    </p>
                </div>
                @if ($editable)
                    <button wire:click="removeDocument({{ $doc->id }})" wire:confirm="Remove {{ $doc->name }} from the checklist?"
                            class="text-xs font-semibold text-muted hover:text-bad-ink">Remove</button>
                @endif
            </li>
        @endforeach
    </ul>

    @if ($editable)
        <form wire:submit="addDocument" class="mt-3 flex gap-2">
            <input wire:model="newDocument" placeholder="Add a document to the checklist"
                   class="w-full min-w-0 flex-1 rounded-[9px] border border-line-2 bg-surface px-2.5 py-1.5 text-[13px]">
            <button class="btn btn-outline">+ Add Document</button>
        </form>
        @error('newDocument') <p class="mt-1 text-xs text-bad-ink">{{ $message }}</p> @enderror
    @endif
</x-card>
