@php use App\Support\Money; @endphp
@if ($editing)
    <x-card title="Edit details" icon="tenders">
        <form wire:submit="save" class="space-y-4">
            @include('livewire.partials.tender-fields')
            <div class="flex justify-end gap-2">
                <button type="button" wire:click="cancelEdit" class="btn btn-outline">Cancel</button>
                <button type="submit" class="btn btn-dark">Save changes</button>
            </div>
        </form>
    </x-card>
@else
    <div class="grid gap-4 lg:grid-cols-2">
        <x-card title="Scope of Work" icon="tenders">
            <x-slot:actions>
                @if ($canEdit && ! $tender->isLocked())
                    <button wire:click="startEdit" class="btn btn-outline">Edit details</button>
                @endif
            </x-slot:actions>
            @if ($tender->scope)
                <p class="whitespace-pre-line text-[13.5px]">{{ $tender->scope }}</p>
            @else
                <p class="text-[13.5px] text-muted">No scope written yet.</p>
            @endif
            @if ($tender->collectedTender)
                <p class="mt-4 text-[13px] text-muted">
                    Collected from {{ implode(', ', $tender->collectedTender->sourceNames()) }} —
                    <a href="{{ route('find-tenders.show', $tender->collectedTender) }}" class="font-semibold text-info-ink underline">view original</a>
                </p>
            @endif
        </x-card>

        <x-card title="Registration Details" icon="quotation">
            <div class="grid grid-cols-2 gap-x-6 gap-y-3">
                <x-fact label="WO Number">{{ $tender->wo_number }}</x-fact>
                <x-fact label="WO Date">{{ $tender->wo_date->format('d M Y') }}</x-fact>
                <x-fact label="Mode">{{ $tender->mode->label() }}</x-fact>
                <x-fact label="Type">{{ $tender->type->label() }}</x-fact>
                <x-fact label="Publish Date">{{ $tender->publish_date?->format('d M Y') ?? '—' }}</x-fact>
                <x-fact label="Briefing">{{ $tender->has_briefing ? 'Yes — '.$tender->briefing_date?->format('d M Y') : 'No' }}</x-fact>
                <x-fact label="Tender Code">{{ $tender->tender_code }}</x-fact>
                <x-fact label="Ministry">{{ $tender->ministry ?? '—' }}</x-fact>
                <x-fact label="Submitted price">{{ Money::format($tender->submitted_price_sen) }}</x-fact>
                <x-fact label="Winning price">{{ Money::format($tender->winning_price_sen) }}</x-fact>
                @if ($tender->lost_reason)
                    <x-fact label="Lost / cancel reason" data-reason="lost" class="col-span-2 [&>div:last-child]:whitespace-normal">{{ $tender->lost_reason }}</x-fact>
                @endif
                @if ($tender->drop_reason)
                    <x-fact label="Drop reason" data-reason="drop" class="col-span-2 [&>div:last-child]:whitespace-normal">{{ $tender->drop_reason }}</x-fact>
                @endif
            </div>
        </x-card>
    </div>
@endif
