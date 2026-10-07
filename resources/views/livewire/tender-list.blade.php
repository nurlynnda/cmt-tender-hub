@php
    use App\Enums\TenderStatus;
    use App\Support\Money;
    $isOpen = $status === TenderStatus::InProgress;
@endphp
<div class="space-y-4">
    <header class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold">{{ $status->listTitle() }}</h1>
            <p class="text-sm text-muted">{{ $status->listSubtitle() }}</p>
        </div>
        @if ($isOpen)
            <button type="button" wire:click="$dispatch('open-register-tender')"
                    class="rounded-lg bg-chip px-4 py-2 text-sm font-medium text-chip-ink hover:bg-chip-hover">
                Register Tender
            </button>
        @endif
    </header>

    <section class="flex flex-wrap items-center gap-2 rounded-xl border border-line bg-surface p-3 text-sm">
        <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search WO, code, title, client"
               class="min-w-48 flex-1 rounded-lg border border-line bg-surface px-3 py-1.5">
        <label class="flex items-center gap-1.5"><input type="checkbox" wire:model.live="mine"> My tenders</label>
        <select wire:model.live="mode" class="rounded-lg border border-line bg-surface px-2 py-1.5" aria-label="Mode">
            <option value="">All modes</option>
            @foreach ($modes as $m) <option value="{{ $m->value }}">{{ $m->label() }}</option> @endforeach
        </select>
        <select wire:model.live="pic" class="rounded-lg border border-line bg-surface px-2 py-1.5" aria-label="PIC">
            <option value="">All PICs</option>
            @foreach ($people as $p) <option value="{{ $p->id }}">{{ $p->name }}</option> @endforeach
        </select>
        <select wire:model.live="category" class="rounded-lg border border-line bg-surface px-2 py-1.5" aria-label="Category">
            <option value="">All categories</option>
            @foreach ($categories as $c) <option value="{{ $c->value }}">{{ $c->value }}</option> @endforeach
        </select>
        <label class="flex items-center gap-1">Closing <input type="date" wire:model.live="from" class="rounded-lg border border-line bg-surface px-2 py-1"></label>
        <label class="flex items-center gap-1">to <input type="date" wire:model.live="to" class="rounded-lg border border-line bg-surface px-2 py-1"></label>
        <button type="button" wire:click="clearFilters" class="text-muted hover:text-ink">Clear</button>
    </section>
    @if ($wo_from !== '' && $wo_to !== '')
        <p class="text-sm text-muted">
            Registered {{ \Carbon\CarbonImmutable::parse($wo_from)->format('d M Y') }} – {{ \Carbon\CarbonImmutable::parse($wo_to)->format('d M Y') }}
            (from the Dashboard / Status) · <button type="button" wire:click="$set('wo_from', ''); $set('wo_to', '')" class="underline">show all dates</button>
        </p>
    @endif

    <div class="overflow-x-auto rounded-xl border border-line bg-surface">
        <table class="w-full min-w-[900px] text-sm">
            <thead class="bg-subtle text-left text-xs uppercase tracking-wide text-muted">
                <tr>
                    <th class="px-3 py-2">WO Number</th>
                    <th class="px-3 py-2">Tender</th>
                    <th class="px-3 py-2">Agency</th>
                    <th class="px-3 py-2">Assigned To</th>
                    <th class="px-3 py-2">Deadline</th>
                    @if ($isOpen) <th class="px-3 py-2">Briefing</th> @endif
                    <th class="px-3 py-2 text-right">Est. Value</th>
                    @if ($status === TenderStatus::Done)
                        <th class="px-3 py-2 text-right">Submit Price</th>
                        <th class="px-3 py-2 text-right">Company Variant</th>
                        <th class="px-3 py-2 text-right" title="Margin from the costing">Gross</th>
                    @elseif ($status === TenderStatus::Lost)
                        <th class="px-3 py-2 text-right">Submitted Price</th>
                        <th class="px-3 py-2 text-right">Win Price</th>
                        <th class="px-3 py-2 text-right">Win Variant</th>
                    @elseif ($status === TenderStatus::Awarded)
                        <th class="px-3 py-2 text-right">Submit Price</th>
                        <th class="px-3 py-2 text-right" title="Actual gross profit from the PD">Actual GP</th>
                    @else
                        <th class="px-3 py-2 text-right">Documents</th>
                    @endif
                </tr>
            </thead>
            <tbody>
            @forelse ($tenders as $t)
                @php $closing = $t->closingState(); @endphp
                <tr wire:key="tender-{{ $t->id }}" data-closing="{{ $closing }}"
                    onclick="window.location='{{ route('tenders.show', $t) }}'"
                    @class([
                        'cursor-pointer border-t border-line align-top hover:bg-hover',
                        'bg-warn-bg/50' => $closing === 'soon',
                        'bg-bad-bg/60' => $closing === 'overdue',
                    ])>
                    <td class="px-3 py-2 whitespace-nowrap">
                        <a href="{{ route('tenders.show', $t) }}" class="font-medium hover:underline">{{ $t->wo_number }}</a>
                        <div class="text-xs text-muted">{{ $t->wo_date->format('d M Y') }}</div>
                    </td>
                    <td class="max-w-md px-3 py-2">
                        <div class="text-xs text-muted">{{ $t->tender_code }}</div>
                        <div class="line-clamp-2">{{ $t->title }}</div>
                        @if ($t->was_cancelled) <span class="mt-1 inline-block rounded bg-bad-bg px-1.5 text-xs text-bad-ink">Cancelled</span> @endif
                    </td>
                    <td class="px-3 py-2">{{ $t->client }}</td>
                    <td class="px-3 py-2">
                        <div class="flex items-center gap-2"><x-avatar :user="$t->pic" /> <span>{{ $t->pic->name }}</span></div>
                    </td>
                    <td @class(['px-3 py-2 whitespace-nowrap', 'font-semibold text-bad-ink' => $closing === 'overdue'])>
                        {{ $t->closing_date->format('d M Y') }}
                    </td>
                    @if ($isOpen)
                        <td class="px-3 py-2">
                            <span @class(['rounded-full px-2 py-0.5 text-xs', 'bg-good-bg text-good-ink' => $t->has_briefing, 'bg-bad-bg text-bad-ink' => ! $t->has_briefing])>
                                {{ $t->has_briefing ? 'Yes' : 'No' }}
                            </span>
                        </td>
                    @endif
                    <td class="px-3 py-2 text-right whitespace-nowrap">{{ Money::format($t->estimated_value_sen) }}</td>
                    @if ($status === TenderStatus::Done)
                        <td class="px-3 py-2 text-right whitespace-nowrap">{{ Money::format($t->submitted_price_sen) }}</td>
                        <td class="px-3 py-2 text-right">{{ $t->companyVariant() ?? '—' }}</td>
                        <td class="px-3 py-2 text-right">{{ ($g = $t->costingSummary()) ? \App\Support\Percent::format($g['margin_bp']) : '—' }}</td>
                    @elseif ($status === TenderStatus::Lost)
                        <td class="px-3 py-2 text-right whitespace-nowrap">{{ Money::format($t->submitted_price_sen) }}</td>
                        <td class="px-3 py-2 text-right whitespace-nowrap">{{ Money::format($t->winning_price_sen) }}</td>
                        <td class="px-3 py-2 text-right">{{ $t->winVariant() ?? '—' }}</td>
                    @elseif ($status === TenderStatus::Awarded)
                        @php $pd = $t->project?->summary(); @endphp
                        <td class="px-3 py-2 text-right whitespace-nowrap">{{ Money::format($t->submitted_price_sen) }}</td>
                        <td class="px-3 py-2 text-right whitespace-nowrap">
                            @if ($pd)
                                {{-- No percentage until the customer has been invoiced: 0% would read as "no profit" --}}
                                @if ($pd['pnl']['actual']['revenue'] > 0)
                                    <span @class(['text-bad-ink' => $pd['below_margin']])>{{ \App\Support\Percent::format($pd['pnl']['actual']['gp_bp']) }}</span>
                                @else
                                    <span class="text-muted" title="Nothing invoiced to the customer yet">—</span>
                                @endif
                                @if (! $t->project->isOpen()) <span class="ml-1 rounded-full bg-subtle px-2 py-0.5 text-xs">Closed</span> @endif
                            @else
                                —
                            @endif
                        </td>
                    @else
                        <td class="px-3 py-2 text-right">{{ $t->documentPercent() }}%</td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="10" class="px-3 py-10 text-center text-muted">No tenders match.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <footer class="flex items-center justify-between text-sm text-muted">
        <span>
            @if ($tenders->total())
                Showing {{ $tenders->firstItem() }}–{{ $tenders->lastItem() }} of {{ $tenders->total() }}
            @endif
        </span>
        {{ $tenders->links('pagination.pager') }}
    </footer>

    @if ($isOpen)
        <livewire:register-tender-modal />
    @endif
</div>
