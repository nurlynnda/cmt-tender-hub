@php
    use App\Support\Money;
    $th = 'px-3.5 py-3 text-left text-[10.5px] font-bold uppercase tracking-[0.5px] text-muted';
    $td = 'px-3.5 py-3 align-top';
    $statuses = ['all' => 'All', 'draft' => 'Draft', 'sent' => 'Sent', 'expired' => 'Expired', 'accepted' => 'Accepted', 'rejected' => 'Rejected', 'revised' => 'Revised'];
@endphp
<div class="space-y-4">
    <x-page-heading title="Quotations" subtitle="Quick quotations outside the formal tender process">
        <x-slot:actions>
            <button type="button" wire:click="create" class="btn btn-primary"><x-icon name="plus" class="h-[15px] w-[15px]" /> New Quotation</button>
        </x-slot:actions>
    </x-page-heading>

    {{-- Search + My quotations; the status buttons below do the filtering, so there's no Filters panel here --}}
    <div class="space-y-2">
        <div class="flex flex-wrap items-center gap-2 rounded-2xl border border-line bg-surface p-2">
            <label class="flex min-w-[200px] flex-1 items-center gap-2 px-2">
                <x-icon name="search" class="h-4 w-4 text-muted-2" />
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search quotation no., customer or subject" aria-label="Search"
                       class="min-w-0 flex-1 bg-transparent py-1.5 text-sm outline-none placeholder:text-muted-2">
            </label>
            <button type="button" wire:click="$toggle('mine')" aria-pressed="{{ $mine ? 'true' : 'false' }}" @class([
                'flex items-center gap-1.5 rounded-[10px] border px-3 py-1.5 text-[12.5px] font-semibold',
                'border-accent bg-accent text-accent-ink' => $mine, 'border-line-2 text-ink-2 hover:bg-hover' => ! $mine,
            ])><x-icon name="user" class="h-[15px] w-[15px]" /> My quotations</button>
        </div>
        <div class="flex flex-wrap gap-2">
            @foreach ($statuses as $k => $label)
                <button type="button" wire:click="$set('status', '{{ $k }}')" @class(['flex items-center gap-1.5 rounded-[10px] border px-3 py-1.5 text-[12.5px] font-semibold',
                    'border-chip bg-chip text-chip-ink' => $status === $k, 'border-line-2 text-ink-2 hover:bg-hover' => $status !== $k])>
                    {{ $label }} <span class="rounded-md bg-hover px-1.5 text-[10.5px] text-muted" data-status-count="{{ $k }}">{{ $counts[$k] }}</span>
                </button>
            @endforeach
        </div>
    </div>

    {{-- Desktop / tablet table --}}
    <x-data-table resizable="quotations" min-width="900px" class="hidden md:block">
        <thead class="bg-subtle">
            <tr>
                <th class="{{ $th }}">Quotation No.</th>
                <th class="{{ $th }}" aria-sort="{{ $sortDir === 'date_asc' ? 'ascending' : 'descending' }}">
                    <button type="button" wire:click="toggleDateSort" title="Sort by date" class="uppercase hover:text-ink">Date <span class="text-ink">{{ $sortDir === 'date_asc' ? '↑' : '↓' }}</span></button>
                </th>
                <th class="{{ $th }}">Customer</th><th class="{{ $th }}">Subject</th><th class="{{ $th }}">Prepared By</th>
                <th class="{{ $th }} text-right">Amount</th><th class="{{ $th }}">Valid Until</th><th class="{{ $th }}">Status</th>
            </tr>
        </thead>
        <tbody>
        @forelse ($quotations as $q)
            <tr wire:key="q-{{ $q->id }}" onclick="window.location='{{ route('quotations.show', $q) }}'" class="cursor-pointer border-t border-line hover:bg-subtle">
                <td class="{{ $td }} whitespace-nowrap"><a href="{{ route('quotations.show', $q) }}" class="font-bold hover:underline">{{ $q->number }}</a></td>
                <td class="{{ $td }} whitespace-nowrap">{{ $q->quote_date->format('d M Y') }}</td>
                <td class="{{ $td }}">{{ $q->customer_name ?: '—' }}</td>
                <td class="{{ $td }} text-muted">{{ $q->subject ?: '—' }}</td>
                <td class="{{ $td }}"><div class="flex items-center gap-2"><x-avatar :user="$q->preparer" /> <span>{{ $q->preparer->name }}</span></div></td>
                <td class="{{ $td }} whitespace-nowrap text-right">{{ Money::format($q->totals()['total_sen']) }}</td>
                <td @class([$td, 'whitespace-nowrap', 'font-semibold text-bad-ink' => $q->isExpired()])>{{ $q->validUntil()->format('d M Y') }}</td>
                <td class="{{ $td }}"><x-status-pill :status="$q->displayStatus()" /></td>
            </tr>
        @empty
            <tr><td colspan="8" class="px-3 py-10 text-center text-muted">No quotations match your search.</td></tr>
        @endforelse
        </tbody>
    </x-data-table>

    {{-- Phone cards --}}
    <div class="space-y-2.5 md:hidden">
        @forelse ($quotations as $q)
            <a href="{{ route('quotations.show', $q) }}" wire:key="q-card-{{ $q->id }}" class="block min-w-0 rounded-2xl border border-line bg-surface p-4">
                <div class="flex items-center justify-between gap-2 text-[12px]">
                    <span class="font-bold">{{ $q->number }}</span>
                    <x-status-pill :status="$q->displayStatus()" />
                </div>
                <p class="mt-1.5 truncate text-[13.5px] font-semibold">{{ $q->customer_name ?: '—' }}</p>
                <p class="mt-0.5 line-clamp-2 text-[12px] text-muted">{{ $q->subject ?: '—' }}</p>
                <div class="mt-2.5 flex items-center justify-between text-[12.5px]">
                    <span @class(['text-muted' => ! $q->isExpired(), 'font-semibold text-bad-ink' => $q->isExpired()])>Valid until {{ $q->validUntil()->format('d M Y') }}</span>
                    <span class="font-semibold">{{ Money::format($q->totals()['total_sen']) }}</span>
                </div>
            </a>
        @empty
            <p class="rounded-2xl border border-line bg-surface p-8 text-center text-muted">No quotations match your search.</p>
        @endforelse
    </div>

    <footer class="flex flex-wrap items-center justify-between gap-2 text-[12.5px] text-muted">
        <span>@if ($quotations->total()) Showing {{ $quotations->firstItem() }}–{{ $quotations->lastItem() }} of {{ $quotations->total() }} quotations @endif</span>
        {{ $quotations->links('pagination.pager') }}
    </footer>
</div>
