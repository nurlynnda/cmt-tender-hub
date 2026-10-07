<div class="space-y-4">
    <a href="{{ route('quotations.show', $quotation) }}" class="text-sm text-muted hover:text-ink">← Back to {{ $quotation->number }}</a>
    <header class="rounded-xl border border-line bg-surface p-4">
        <p class="text-xs uppercase text-muted">Project from quotation</p>
        <h1 class="text-lg font-semibold">{{ $quotation->number }} — {{ $quotation->subject }}</h1>
        <p class="text-sm text-muted">{{ $quotation->customer_name }}</p>
    </header>
    <livewire:project-pd :project="$project" wire:key="pd-q-{{ $quotation->id }}" />
</div>
