<div class="space-y-4">
    <a href="{{ route('quotations.show', $quotation) }}" class="text-[13px] font-semibold text-muted hover:text-ink">← Back to {{ $quotation->number }}</a>
    <header class="space-y-1 rounded-[20px] border border-line bg-surface p-5">
        <p class="text-[10.5px] font-bold uppercase tracking-[0.5px] text-muted">Project from quotation</p>
        <h1 class="text-xl font-extrabold leading-snug tracking-tight">{{ $quotation->number }} — {{ $quotation->subject }}</h1>
        <p class="text-[13px] text-muted-2">{{ $quotation->customer_name }}</p>
    </header>
    <livewire:project-pd :project="$project" wire:key="pd-q-{{ $quotation->id }}" />
</div>
