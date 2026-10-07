@php use App\Support\Money; @endphp
<section class="rounded-xl border border-line bg-surface p-4">
    @if ($editing)
        <form wire:submit="save" class="space-y-4">
            @include('livewire.partials.tender-fields')
            <div class="flex justify-end gap-2">
                <button type="button" wire:click="cancelEdit" class="rounded-lg px-4 py-2 text-sm hover:bg-hover">Cancel</button>
                <button type="submit" class="rounded-lg bg-chip px-4 py-2 text-sm font-medium text-chip-ink">Save changes</button>
            </div>
        </form>
    @else
        @if ($canEdit && ! $tender->isLocked())
            <div class="mb-3 flex justify-end">
                <button wire:click="startEdit" class="rounded-lg border border-line px-3 py-1.5 text-sm hover:bg-hover">Edit details</button>
            </div>
        @endif
        <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2 lg:grid-cols-3">
            @foreach ([
                'Assigned PIC' => $tender->pic->name,
                'Opportunity owner' => $tender->owner?->name ?? '—',
                'Category' => $tender->category->value,
                'Tender code' => $tender->tender_code,
                'Ministry' => $tender->ministry ?? '—',
                'Agency' => $tender->client,
                'Estimated value' => Money::format($tender->estimated_value_sen),
                'Type' => $tender->type->label(),
                'Mode' => $tender->mode->label(),
                'WO date' => $tender->wo_date->format('d M Y'),
                'Publish date' => $tender->publish_date?->format('d M Y') ?? '—',
                'Closing date' => $tender->closing_date->format('d M Y'),
                'Briefing' => $tender->has_briefing ? 'Yes — '.$tender->briefing_date?->format('d M Y') : 'No',
                'Submitted price' => Money::format($tender->submitted_price_sen),
                'Winning price' => Money::format($tender->winning_price_sen),
                'Lost / cancel reason' => $tender->lost_reason ?? '—',
                'Drop reason' => $tender->drop_reason ?? '—',
            ] as $label => $value)
                <div>
                    <dt class="text-xs uppercase tracking-wide text-muted">{{ $label }}</dt>
                    <dd>{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
        <div class="mt-4">
            <p class="text-xs uppercase tracking-wide text-muted">Scope of work</p>
            <p class="mt-1 whitespace-pre-line text-sm">{{ $tender->scope ?: '—' }}</p>
        </div>
        @if ($tender->collectedTender)
            <p class="mt-4 text-sm text-muted">
                Collected from {{ implode(', ', $tender->collectedTender->sourceNames()) }} —
                <a href="{{ route('find-tenders.show', $tender->collectedTender) }}" class="text-info-ink underline">view original</a>
            </p>
        @endif
    @endif
</section>
