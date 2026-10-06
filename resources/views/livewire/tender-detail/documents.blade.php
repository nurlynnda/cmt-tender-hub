@php $editable = $canEdit && ! $tender->isLocked(); @endphp
<section class="space-y-3 rounded-xl border border-line bg-surface p-4">
    <div class="flex items-center justify-between text-sm">
        <span>Assigned to {{ $tender->pic->name }} (PIC)</span>
        <span class="font-medium">{{ $doneCount }} / {{ $documents->count() }} done</span>
    </div>
    <ul class="divide-y divide-line">
        @foreach ($documents as $doc)
            <li wire:key="doc-{{ $doc->id }}" class="flex items-center gap-3 py-2 text-sm">
                <input type="checkbox" @checked($doc->is_done) @disabled(! $editable)
                       wire:click="toggleDocument({{ $doc->id }})" aria-label="Tick {{ $doc->name }}">
                <div class="flex-1">
                    <p @class(['line-through text-muted' => $doc->is_done])>{{ $doc->name }}</p>
                    <p class="text-xs text-muted">
                        {{ $doc->is_done ? 'Ticked by '.($doc->doneBy?->name ?? 'someone').' · '.$doc->done_at?->setTimezone('Asia/Kuala_Lumpur')->format('d M Y') : 'Tick when prepared' }}
                    </p>
                </div>
                @if ($editable)
                    <button wire:click="removeDocument({{ $doc->id }})" wire:confirm="Remove {{ $doc->name }} from the checklist?"
                            class="text-xs text-muted hover:text-bad-ink">Remove</button>
                @endif
            </li>
        @endforeach
    </ul>
    @if ($editable)
        <form wire:submit="addDocument" class="flex gap-2">
            <input wire:model="newDocument" placeholder="Add a document to the checklist"
                   class="flex-1 rounded-lg border border-line bg-surface px-3 py-1.5 text-sm">
            <button class="rounded-lg border border-line px-3 py-1.5 text-sm hover:bg-hover">+ Add</button>
        </form>
        @error('newDocument') <p class="text-xs text-bad-ink">{{ $message }}</p> @enderror
    @endif
</section>
