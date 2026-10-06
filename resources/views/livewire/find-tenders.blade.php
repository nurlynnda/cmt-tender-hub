@php
    use App\Collector\SourceName;
    use App\Support\Money;
    $types = ['quotation' => 'Quotation', 'tender' => 'Tender', 'requisition' => 'Requisition'];
    $field = 'rounded-lg border border-line bg-surface px-2 py-1.5';
@endphp
<div class="space-y-4" @if ($running) wire:poll.15s @endif>
    <header class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold">Find Tenders</h1>
            <p class="text-sm text-muted">Government tenders collected from MyProcurement, SPAN and LLM</p>
        </div>
        @can('collect-now')
            <button type="button" wire:click="collectNow" class="rounded-lg bg-chip px-4 py-2 text-sm font-medium text-chip-ink hover:bg-chip-hover">Collect now</button>
        @endcan
    </header>

    <section class="rounded-xl border border-line bg-surface px-4 py-2 text-sm" aria-label="Collection status">
        @if ($running)
            <span class="font-medium">Collecting now…</span> started {{ $running->started_at->setTimezone('Asia/Kuala_Lumpur')->format('g:i a') }}
        @elseif ($lastRun)
            Last collected {{ $lastRun->finished_at->setTimezone('Asia/Kuala_Lumpur')->format('d M Y, g:i a') }} —
            @foreach (($lastRun->results ?? []) as $src => $r)
                @if (is_array($r))
                    @if ($r['error'])
                        <span class="text-bad-ink">{{ SourceName::label($src) }} failed: {{ $r['error'] }}</span>
                    @else
                        {{ SourceName::label($src) }} {{ number_format($r['count']) }}
                    @endif
                    @if (! $loop->last) · @endif
                @else
                    <span class="text-bad-ink">{{ $r }}</span>
                @endif
            @endforeach
        @else
            Not collected yet.
        @endif
        @if ($notice) <p class="mt-1 text-info-ink">{{ $notice }}</p> @endif
    </section>

    <section class="flex flex-wrap items-center gap-2 rounded-xl border border-line bg-surface p-3 text-sm">
        <input type="search" wire:model.live.debounce.400ms="search" placeholder="Search title, reference, agency" class="{{ $field }} min-w-48 flex-1">
        <select wire:model.live="status" class="{{ $field }}" aria-label="Status">
            <option value="open">Open</option><option value="closed">Closed</option><option value="all">All</option>
        </select>
        <select wire:model.live="source" class="{{ $field }}" aria-label="Source">
            <option value="">All sources</option>
            @foreach ($sources as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach
        </select>
        <select wire:model.live="type" class="{{ $field }}" aria-label="Type">
            <option value="">All types</option>
            @foreach ($types as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach
        </select>
        <input wire:model.live.debounce.400ms="ministry" list="ministry-options" placeholder="Ministry" class="{{ $field }} w-56">
        <datalist id="ministry-options">@foreach ($ministries as $m) <option value="{{ $m }}"></option> @endforeach</datalist>
        <input wire:model.live.debounce.400ms="codes" placeholder="Field codes, e.g. 210103, E05" class="{{ $field }} w-52">
        <label class="flex items-center gap-1">Closing <input type="date" wire:model.live="from" class="{{ $field }}"></label>
        <label class="flex items-center gap-1">to <input type="date" wire:model.live="to" class="{{ $field }}"></label>
        <button type="button" wire:click="clearFilters" class="text-muted hover:text-ink">Clear</button>
    </section>

    <div class="overflow-x-auto rounded-xl border border-line bg-surface">
        <table class="w-full min-w-[960px] text-sm">
            <thead class="bg-subtle text-left text-xs uppercase tracking-wide text-muted">
                <tr>
                    <th class="px-3 py-2">Reference</th><th class="px-3 py-2">Title</th><th class="px-3 py-2">Ministry / Agency</th>
                    <th class="px-3 py-2">Type</th><th class="px-3 py-2">Advertised</th><th class="px-3 py-2">Closing</th>
                    <th class="px-3 py-2 text-right">Indicative price</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($tenders as $t)
                @php $days = $t->status === 'open' ? $t->daysLeft() : null; $wo = $t->pipelineTenders->first(); @endphp
                <tr wire:key="ct-{{ $t->id }}" class="cursor-pointer border-t border-line align-top hover:bg-hover"
                    onclick="window.location='{{ route('find-tenders.show', $t) }}'">
                    <td class="px-3 py-2">
                        <a href="{{ route('find-tenders.show', $t) }}" class="font-medium hover:underline">{{ $t->reference_no ?: '—' }}</a>
                        <div class="mt-1 flex flex-wrap gap-1">
                            @foreach ($t->sources as $s) <span class="rounded bg-subtle px-1.5 text-xs text-muted">{{ SourceName::label($s->source) }}</span> @endforeach
                        </div>
                        @if ($wo) <span class="mt-1 inline-block rounded bg-good-bg px-1.5 text-xs text-good-ink">Registered as WO {{ $wo->wo_number }}</span> @endif
                    </td>
                    <td class="max-w-md px-3 py-2"><div class="line-clamp-3">{{ $t->title }}</div></td>
                    <td class="px-3 py-2">{{ $t->ministry ?? '—' }}<div class="text-xs text-muted">{{ $t->agency }}</div></td>
                    <td class="px-3 py-2">{{ $types[$t->procurement_type] ?? '—' }}</td>
                    <td class="px-3 py-2 whitespace-nowrap">{{ $t->advertised_date?->format('d M Y') ?? '—' }}</td>
                    <td class="px-3 py-2 whitespace-nowrap">
                        {{ $t->closing_date?->format('d M Y') ?? '—' }}
                        @if ($days !== null)
                            <div @class(['text-xs', 'text-bad-ink' => $days <= 3, 'text-muted' => $days > 3])>
                                {{ $days === 0 ? 'Closes today' : ($days === 1 ? '1 day left' : "{$days} days left") }}
                            </div>
                        @endif
                    </td>
                    <td class="px-3 py-2 text-right whitespace-nowrap">{{ Money::format($t->indicative_price_sen) }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-3 py-10 text-center text-muted">No tenders match.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <footer class="flex items-center justify-between text-sm text-muted">
        <span>@if ($tenders->total()) Showing {{ number_format($tenders->firstItem()) }}–{{ number_format($tenders->lastItem()) }} of {{ number_format($tenders->total()) }} @endif</span>
        {{ $tenders->links('pagination.pager') }}
    </footer>
</div>
