@php
    $editable = $canEdit && ! $tender->isLocked();
    $total = $topLevelCount;
    $subtasks = $documents->whereNotNull('parent_id')->groupBy('parent_id');
    $pct = $total ? (int) round($doneCount * 100 / $total) : 0;
@endphp
<x-card title="Documents" :subtitle="$tender->pic ? 'Assigned to '.$tender->pic->name.' (PIC)' : 'No PIC yet — anyone can edit this tender'" icon="check">
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

    {{-- Each document is a task for the PIC; a task can have subtasks and is done once all of them are ticked --}}
    <ul class="divide-y divide-line">
        @foreach ($documents->whereNull('parent_id') as $doc)
            @php $kids = $subtasks->get($doc->id, collect()); @endphp
            <li wire:key="doc-{{ $doc->id }}" class="py-2.5 text-[13.5px]" x-data="{ adding: false }">
                <div class="flex items-center gap-3">
                    <input type="checkbox" @checked($doc->is_done) @disabled(! $editable) class="h-4 w-4 shrink-0 accent-[var(--accent-solid)]"
                           wire:click="toggleDocument({{ $doc->id }})" aria-label="Tick {{ $doc->name }}">
                    <div class="min-w-0 flex-1">
                        <p @class(['font-medium', 'line-through text-muted' => $doc->is_done])>{{ $doc->name }}</p>
                        <p class="text-xs text-muted">
                            @if ($kids->isNotEmpty())
                                {{ $kids->where('is_done', true)->count() }} of {{ $kids->count() }} subtasks done
                            @elseif ($doc->is_done)
                                Ticked by {{ $doc->doneBy?->name ?? 'someone' }} · {{ $doc->done_at?->setTimezone('Asia/Kuala_Lumpur')->format('d M Y') }}
                            @else
                                Tick when prepared
                            @endif
                        </p>
                    </div>
                    @if ($editable)
                        <button type="button" @click="adding = ! adding" class="text-xs font-semibold text-muted hover:text-ink">+ Subtask</button>
                        <button wire:click="removeDocument({{ $doc->id }})" wire:confirm="Remove {{ $doc->name }}{{ $kids->isNotEmpty() ? ' and its subtasks' : '' }} from the checklist?"
                                class="text-xs font-semibold text-muted hover:text-bad-ink">Remove</button>
                    @endif
                </div>

                @if ($kids->isNotEmpty())
                    <ul class="ml-7 mt-2 space-y-1.5 border-l border-line pl-4">
                        @foreach ($kids as $kid)
                            <li wire:key="doc-{{ $kid->id }}" class="flex items-center gap-3">
                                <input type="checkbox" @checked($kid->is_done) @disabled(! $editable) class="h-4 w-4 shrink-0 accent-[var(--accent-solid)]"
                                       wire:click="toggleDocument({{ $kid->id }})" aria-label="Tick {{ $kid->name }}">
                                <p @class(['min-w-0 flex-1 text-[13px]', 'line-through text-muted' => $kid->is_done])>{{ $kid->name }}</p>
                                @if ($editable)
                                    <button wire:click="removeDocument({{ $kid->id }})" wire:confirm="Remove {{ $kid->name }} from the checklist?"
                                            class="text-xs font-semibold text-muted hover:text-bad-ink">Remove</button>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if ($editable)
                    <form x-show="adding" x-cloak wire:submit="addSubtask({{ $doc->id }})" class="ml-7 mt-2 flex gap-2 pl-4">
                        <input wire:model="newSubtasks.{{ $doc->id }}" placeholder="Add a subtask to {{ $doc->name }}" aria-label="New subtask for {{ $doc->name }}"
                               class="w-full min-w-0 flex-1 rounded-[9px] border border-line-2 bg-surface px-2.5 py-1.5 text-[13px]">
                        <button class="btn btn-outline">Add</button>
                    </form>
                    @error("newSubtasks.{$doc->id}") <p class="ml-7 mt-1 pl-4 text-xs text-bad-ink">{{ $message }}</p> @enderror
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
