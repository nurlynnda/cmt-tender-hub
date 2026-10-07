@php
    use App\Support\Money;
    $tone = [
        'draft' => 'bg-subtle text-muted', 'sent' => 'bg-info-bg text-info-ink', 'expired' => 'bg-warn-bg text-warn-ink',
        'accepted' => 'bg-good-bg text-good-ink', 'rejected' => 'bg-bad-bg text-bad-ink', 'revised' => 'bg-subtle text-muted',
    ];
@endphp
<div class="space-y-4">
    <div>
        <h1 class="text-xl font-semibold">Quotations</h1>
        <p class="text-sm text-muted">Quick quotations outside the formal tender process</p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <input wire:model.live.debounce.300ms="search" placeholder="Search quotation no., customer or subject"
               class="min-w-64 flex-1 rounded-lg border border-line bg-surface px-3 py-2 text-sm" aria-label="Search">
        @foreach (['all' => 'All', 'draft' => 'Draft', 'sent' => 'Sent', 'expired' => 'Expired', 'accepted' => 'Accepted', 'rejected' => 'Rejected', 'revised' => 'Revised'] as $key => $label)
            <button type="button" wire:click="$set('status', '{{ $key }}')" @class([
                'rounded-full border px-3 py-1 text-xs',
                'border-chip bg-chip text-chip-ink' => $status === $key,
                'border-line hover:bg-hover' => $status !== $key,
            ])>{{ $label }}</button>
        @endforeach
        <label class="flex items-center gap-1 text-sm"><input type="checkbox" wire:model.live="mine"> Mine</label>
        <button type="button" wire:click="create" class="ml-auto rounded-lg bg-chip px-3 py-2 text-sm font-medium text-chip-ink hover:bg-chip-hover">+ New Quotation</button>
    </div>

    <div class="relative overflow-x-auto rounded-xl border border-line bg-surface">
        <table class="w-full min-w-[900px] text-sm">
            <thead class="bg-subtle text-left text-xs uppercase text-muted">
                <tr>
                    <th class="px-3 py-2">Quotation No.</th><th class="px-3 py-2">Date</th><th class="px-3 py-2">Customer</th>
                    <th class="px-3 py-2">Subject</th><th class="px-3 py-2">Prepared by</th><th class="px-3 py-2 text-right">Amount</th>
                    <th class="px-3 py-2">Valid until</th><th class="px-3 py-2">Status</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($quotations as $q)
                <tr wire:key="q-{{ $q->id }}" class="border-t border-line hover:bg-hover">
                    <td class="px-3 py-2 font-mono text-xs"><a href="{{ route('quotations.show', $q) }}" class="underline">{{ $q->number }}</a></td>
                    <td class="whitespace-nowrap px-3 py-2">{{ $q->quote_date->format('d M Y') }}</td>
                    <td class="px-3 py-2">{{ $q->customer_name ?: '—' }}</td>
                    <td class="px-3 py-2 text-muted">{{ $q->subject ?: '—' }}</td>
                    <td class="px-3 py-2">{{ $q->preparer->name }}</td>
                    <td class="whitespace-nowrap px-3 py-2 text-right">{{ Money::format($q->totals()['total_sen']) }}</td>
                    <td @class(['whitespace-nowrap px-3 py-2', 'text-bad-ink' => $q->isExpired()])>{{ $q->validUntil()->format('d M Y') }}</td>
                    <td class="px-3 py-2"><span class="rounded-full px-2 py-0.5 text-xs {{ $tone[$q->displayStatus()] }}">{{ $q->displayLabel() }}</span></td>
                </tr>
            @empty
                <tr><td colspan="8" class="px-3 py-10 text-center text-muted">No quotations match.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $quotations->links('pagination.pager') }}
</div>
