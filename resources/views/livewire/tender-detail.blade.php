@php
    use App\Enums\TenderStatus;
    $status = $tender->status;
    $total = $documents->count();
    $pct = $total ? (int) round($doneCount * 100 / $total) : 0;
    $btn = 'rounded-lg px-3 py-1.5 text-sm font-medium';
@endphp
<div class="space-y-4">
    <a href="{{ route('tenders.index', $status->slug()) }}" class="text-sm text-muted hover:text-ink">← Back to list</a>

    @if ($conflict)
        <div class="flex items-center justify-between rounded-lg bg-warn-bg p-3 text-sm text-warn-ink" role="alert">
            <span>{{ $conflict }}</span>
            <a href="{{ route('tenders.show', $tender) }}" class="font-medium underline">Reload</a>
        </div>
    @endif

    @if ($pendingDocuments)
        <div class="rounded-lg bg-bad-bg p-3 text-sm text-bad-ink" role="alert">
            {{ count($pendingDocuments) }} document(s) still not ticked: {{ implode(', ', $pendingDocuments) }}.
            Tick every document before marking this tender as Done.
        </div>
    @endif

    <header class="rounded-xl border border-line bg-surface p-4">
        <div class="flex flex-wrap items-center gap-2">
            <span class="font-semibold">{{ $tender->wo_number }}</span>
            <x-status-badge :status="$status" />
            <span class="rounded-full bg-subtle px-2 py-0.5 text-xs">{{ $tender->mode->label() }}</span>
            <span class="rounded-full bg-subtle px-2 py-0.5 text-xs">Docs {{ $pct }}%</span>
            <div class="ml-auto flex flex-wrap gap-2">
                @if ($canEdit && $status === TenderStatus::InProgress)
                    <button wire:click="openModal('cancel')" class="{{ $btn }} border border-line hover:bg-hover">Cancel Tender</button>
                    <button wire:click="openModal('done')" class="{{ $btn }} bg-chip text-chip-ink hover:bg-chip-hover">Mark Done</button>
                @endif
                @if ($canEdit && $status === TenderStatus::Done)
                    <button wire:click="openModal('lost')" class="{{ $btn }} border border-line hover:bg-hover">Mark Lost</button>
                    <button wire:click="openModal('awarded')" class="{{ $btn }} bg-accent text-accent-ink">Mark Awarded</button>
                @endif
                @if ($canReopen && $status !== TenderStatus::InProgress)
                    <button wire:click="openModal('reopen')" class="{{ $btn }} border border-line hover:bg-hover">Reopen</button>
                @endif
            </div>
        </div>
        <h1 class="mt-3 text-lg font-semibold">{{ $tender->title }}</h1>
    </header>

    <nav class="flex gap-1 border-b border-line text-sm">
        @foreach (['overview' => 'Overview', 'costing' => 'Costing', 'documents' => 'Documents', 'activity' => 'Activity'] as $key => $label)
            <button type="button" wire:click="$set('tab', '{{ $key }}')" @class([
                'px-3 py-2 -mb-px border-b-2',
                'border-ink font-medium' => $tab === $key,
                'border-transparent text-muted hover:text-ink' => $tab !== $key,
            ])>{{ $label }}</button>
        @endforeach
    </nav>

    @if ($tab !== 'costing')
        @include('livewire.tender-detail.'.(in_array($tab, ['overview', 'documents', 'activity'], true) ? $tab : 'overview'))
    @endif
    {{-- Always mounted, so unsaved costing edits survive switching tabs --}}
    <div @class(['hidden' => $tab !== 'costing'])>
        <livewire:tender-costing :tender="$tender" :version="$version" :can-edit="$canEdit" wire:key="costing-{{ $tender->id }}" />
    </div>
    @include('livewire.tender-detail.modals')
</div>
