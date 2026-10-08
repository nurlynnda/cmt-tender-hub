@php
    use App\Enums\TenderStatus;
    use App\Support\Money;
    $status = $tender->status;
    $total = $documents->count();
    $closing = $tender->closingState();
    $pill = 'rounded-full bg-hover px-2.5 py-0.5 text-[11.5px] font-semibold text-ink-2';
@endphp
<div class="space-y-4">
    <a href="{{ route('tenders.index', $status->slug()) }}" class="text-[13px] font-semibold text-muted hover:text-ink">← Back to list</a>

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

    @if ($costingProblem)
        <div class="rounded-lg bg-bad-bg p-3 text-sm text-bad-ink" role="alert">
            {{ $costingProblem }}
            @if ($tab !== 'costing')
                <button type="button" wire:click="$set('tab', 'costing')" class="ml-1 font-medium underline">Go to Costing</button>
            @endif
        </div>
    @endif

    <header class="space-y-4 rounded-[20px] border border-line bg-surface p-5">
        <div class="flex flex-wrap items-center gap-2">
            <span class="font-extrabold tracking-tight">{{ $tender->wo_number }}</span>
            <x-status-pill :status="$status" />
            <span class="{{ $pill }}">{{ $tender->mode->label() }}</span>
            <span class="{{ $pill }}">Docs {{ $doneCount }}/{{ $total }}</span>
            <div class="ml-auto flex flex-wrap gap-2">
                @if ($canEdit && $status === TenderStatus::InProgress)
                    <button wire:click="openModal('drop')" class="btn btn-outline">Drop tender</button>
                    <button wire:click="openModal('cancel')" class="btn btn-outline">Cancel Tender</button>
                    <button wire:click="openModal('done')" class="btn btn-dark">Mark Done</button>
                @endif
                @if ($canEdit && $status === TenderStatus::Done)
                    <button wire:click="openModal('lost')" class="btn btn-outline">Mark Lost</button>
                    <button wire:click="openModal('awarded')" class="btn btn-primary">Mark Awarded</button>
                @endif
                @if ($canReopen && $status !== TenderStatus::InProgress)
                    <button wire:click="openModal('reopen')" class="btn btn-outline">Reopen</button>
                @endif
            </div>
        </div>
        <h1 class="text-xl font-extrabold leading-snug tracking-tight">{{ $tender->title }}</h1>

        {{-- Key facts (prototype) --}}
        <div data-facts class="grid grid-cols-2 gap-x-6 gap-y-3 border-t border-line pt-4 sm:grid-cols-4">
            <x-fact label="Assigned PIC"><span class="flex items-center gap-2"><x-avatar :user="$tender->pic" /> {{ $tender->pic->name }}</span></x-fact>
            <x-fact label="Opportunity Owner">{{ $tender->owner?->name ?? '—' }}</x-fact>
            <x-fact label="Category">{{ $tender->category->value }}</x-fact>
            <x-fact label="Tender Code">{{ $tender->tender_code }}</x-fact>
            <x-fact label="Agency" class="col-span-2 [&>div:last-child]:whitespace-normal">
                {{ $tender->client }}
                @if ($tender->ministry && $tender->ministry !== $tender->client)
                    <span class="block text-[11.5px] font-normal text-muted-2">{{ $tender->ministry }}</span>
                @endif
            </x-fact>
            <x-fact label="Estimated value">{{ Money::format($tender->estimated_value_sen) }}</x-fact>
            <x-fact label="Closing date"><span @class(['text-warn-ink' => $closing === 'soon', 'text-bad-ink' => $closing === 'overdue'])>{{ $tender->closing_date->format('d M Y') }}</span></x-fact>
            <x-fact label="WO date">{{ $tender->wo_date->format('d M Y') }}</x-fact>
        </div>
    </header>

    @php
        $tabs = ['overview' => 'Overview', 'costing' => 'Costing'] + ($hasPd ? ['pd' => 'PD'] : []) + ['documents' => 'Documents', 'activity' => 'Activity'];
    @endphp
    <x-tabs>
        @foreach ($tabs as $key => $label)
            <x-tab :active="$tab === $key" wire:click="$set('tab', '{{ $key }}')">{{ $label }}</x-tab>
        @endforeach
    </x-tabs>

    @if ($tab === 'pd' && $hasPd)
        {{-- PD edits save immediately, so it only needs mounting while shown --}}
        <livewire:project-pd :project="$tender->project" wire:key="pd-{{ $tender->id }}" />
    @elseif ($tab !== 'costing')
        @include('livewire.tender-detail.'.(in_array($tab, ['overview', 'documents', 'activity'], true) ? $tab : 'overview'))
    @endif
    {{-- Always mounted, so unsaved costing edits survive switching tabs --}}
    <div @class(['hidden' => $tab !== 'costing'])>
        <livewire:tender-costing :tender="$tender" :version="$version" :can-edit="$canEdit" wire:key="costing-{{ $tender->id }}" />
    </div>
    @include('livewire.tender-detail.modals')
</div>
