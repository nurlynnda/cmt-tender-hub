@php
    use App\Collector\SourceName;
    use App\Support\Money;
    $types = ['quotation' => 'Quotation', 'tender' => 'Tender', 'requisition' => 'Requisition'];
    $field = 'w-full rounded-[9px] border border-line-2 bg-surface px-2.5 py-1.5 text-[13px]';
    $th = 'px-3.5 py-3 text-left text-[10.5px] font-bold uppercase tracking-[0.5px] text-muted';
    $td = 'px-3.5 py-3 align-top';
    $awarded = $status === 'awarded';
    $oursPill = '<span data-ours class="ml-1 rounded-full bg-good-bg px-1.5 text-[10.5px] font-bold text-good-ink">Ours</span>';
@endphp
<div class="space-y-4" @if ($running) wire:poll.15s @endif>
    <x-page-heading title="Find Tenders" subtitle="Government tenders collected from MyProcurement, SPAN and LLM">
        @can('collect-now')
            <x-slot:actions>
                <button type="button" wire:click="collectNow" class="rounded-[11px] bg-chip px-4 py-2.5 text-[12.5px] font-bold text-chip-ink hover:bg-chip-hover">Collect now</button>
            </x-slot:actions>
        @endcan
    </x-page-heading>

    <section class="rounded-2xl border border-line bg-surface px-4 py-2.5 text-[13px]" aria-label="Collection status">
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

    <x-filter-bar :count="$this->filterCount()" placeholder="Search title, reference, agency" debounce="400"
                  :mine="$awarded ? $ours : null" mine-label="Our wins" mine-model="ours">
        <x-filter-field label="Status">
            <select wire:model.live="status" class="{{ $field }}"><option value="open">Open</option><option value="closed">Closed</option><option value="awarded">Awarded</option><option value="all">All</option></select>
        </x-filter-field>
        <x-filter-field label="Contractor"><input wire:model.live.debounce.400ms="contractor" placeholder="Any contractor" class="{{ $field }}"></x-filter-field>
        <x-filter-field label="Source">
            <select wire:model.live="source" class="{{ $field }}"><option value="">All sources</option>
                @foreach ($sources as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach</select>
        </x-filter-field>
        <x-filter-field label="Type">
            <select wire:model.live="type" class="{{ $field }}"><option value="">All types</option>
                @foreach ($types as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach</select>
        </x-filter-field>
        <x-filter-field label="Ministry">
            <input wire:model.live.debounce.400ms="ministry" list="ministry-options" placeholder="Any ministry" class="{{ $field }}">
            <datalist id="ministry-options">@foreach ($ministries as $m) <option value="{{ $m }}"></option> @endforeach</datalist>
        </x-filter-field>
        <x-filter-field label="Field codes"><input wire:model.live.debounce.400ms="codes" placeholder="e.g. 210103, E05" class="{{ $field }}"></x-filter-field>
        <x-filter-field label="Closing from"><input type="date" wire:model.live="from" class="{{ $field }}"></x-filter-field>
        <x-filter-field label="Closing to"><input type="date" wire:model.live="to" class="{{ $field }}"></x-filter-field>
    </x-filter-bar>

    {{-- Desktop / tablet table --}}
    <x-data-table resizable="find-tenders" min-width="960px" class="hidden md:block">
        <thead class="bg-subtle">
            <tr>
                <th class="{{ $th }}">Reference</th><th class="{{ $th }}">Title</th><th class="{{ $th }}">Ministry / Agency</th>
                @unless ($awarded) <th class="{{ $th }}">Type</th><th class="{{ $th }}">Advertised</th> @endunless
                <th class="{{ $th }}" aria-sort="{{ ['closing_asc' => 'ascending', 'closing_desc' => 'descending'][$sort] ?? 'none' }}">
                    <button type="button" wire:click="toggleClosingSort" title="Sort by closing date" class="uppercase hover:text-ink">Closing
                        <span class="text-ink">{{ ['closing_asc' => '↑', 'closing_desc' => '↓'][$sort] ?? '' }}</span></button>
                </th>
                @if ($awarded) <th class="{{ $th }}">Winner(s)</th><th class="{{ $th }} text-right">Price won</th> @endif
                <th class="{{ $th }} text-right">Indicative price</th>
            </tr>
        </thead>
        <tbody>
        @forelse ($tenders as $t)
            @php $days = $t->status === 'open' ? $t->daysLeft() : null; $wo = $t->pipelineTenders->first(); @endphp
            <tr wire:key="ct-{{ $t->id }}" class="cursor-pointer border-t border-line hover:bg-subtle"
                onclick="window.location='{{ route('find-tenders.show', $t) }}'">
                <td class="{{ $td }}">
                    <a href="{{ route('find-tenders.show', $t) }}" class="font-bold hover:underline">{{ $t->reference_no ?: '—' }}</a>
                    <div class="mt-1 flex flex-wrap gap-1">
                        @foreach ($t->sources as $s) <span class="rounded bg-subtle px-1.5 text-xs text-muted">{{ SourceName::label($s->source) }}</span> @endforeach
                    </div>
                    @if ($wo) <span class="mt-1 inline-block rounded bg-good-bg px-1.5 text-xs text-good-ink">Registered as WO {{ $wo->wo_number }}</span> @endif
                </td>
                <td class="{{ $td }} max-w-md"><div class="line-clamp-3">{{ $t->title }}</div></td>
                <td class="{{ $td }}">{{ $t->ministry ?? '—' }}<div class="text-xs text-muted">{{ $t->agency }}</div></td>
                @unless ($awarded)
                    <td class="{{ $td }}">{{ $types[$t->procurement_type] ?? '—' }}</td>
                    <td class="{{ $td }} whitespace-nowrap">{{ $t->advertised_date?->format('d M Y') ?? '—' }}</td>
                @endunless
                <td class="{{ $td }} whitespace-nowrap">
                    {{ $t->closing_date?->format('d M Y') ?? '—' }}
                    @if ($days !== null)
                        <div @class(['text-xs', 'text-bad-ink' => $days <= 3, 'text-muted' => $days > 3])>
                            {{ $days === 0 ? 'Closes today' : ($days === 1 ? '1 day left' : "{$days} days left") }}
                        </div>
                    @endif
                </td>
                @if ($awarded)
                    <td class="{{ $td }}">
                        @foreach ($t->winnerRows as $w)
                            <div @class(['mt-1' => ! $loop->first])>{{ $w->name }}@if (in_array($w->name_key, $ownKeys, true)){!! $oursPill !!}@endif</div>
                        @endforeach
                    </td>
                    <td class="{{ $td }} whitespace-nowrap text-right">
                        @foreach ($t->winnerRows as $w) <div @class(['mt-1' => ! $loop->first])>{{ Money::format($w->price_sen) }}</div> @endforeach
                    </td>
                @endif
                <td class="{{ $td }} whitespace-nowrap text-right">{{ Money::format($t->indicative_price_sen) }}</td>
            </tr>
        @empty
            <tr><td colspan="7" class="px-3 py-10 text-center text-muted">No tenders match.</td></tr>
        @endforelse
        </tbody>
    </x-data-table>

    {{-- Phone cards --}}
    <div class="space-y-2.5 md:hidden">
        @forelse ($tenders as $t)
            @php $days = $t->status === 'open' ? $t->daysLeft() : null; @endphp
            <a href="{{ route('find-tenders.show', $t) }}" wire:key="ct-card-{{ $t->id }}" class="block min-w-0 rounded-2xl border border-line bg-surface p-4">
                <div class="flex items-center justify-between gap-2 text-[12px]">
                    <span class="truncate font-bold">{{ $t->reference_no ?: '—' }}</span>
                    <span @class(['whitespace-nowrap', 'text-bad-ink' => $days !== null && $days <= 3, 'text-muted' => $days === null || $days > 3])>{{ $t->closing_date?->format('d M Y') ?? '—' }}</span>
                </div>
                <p class="mt-1.5 line-clamp-3 text-[13.5px] font-semibold">{{ $t->title }}</p>
                <p class="mt-0.5 truncate text-[11.5px] text-muted">{{ $t->ministry ?? '—' }} · {{ $types[$t->procurement_type] ?? '—' }}</p>
                @if ($awarded)
                    @foreach ($t->winnerRows as $w)
                        <p class="mt-1.5 text-[12px]"><span class="font-semibold">{{ $w->name }}</span>@if (in_array($w->name_key, $ownKeys, true)){!! $oursPill !!}@endif · {{ Money::format($w->price_sen) }}</p>
                    @endforeach
                @endif
                <p class="mt-2 text-right text-[12.5px] font-semibold">{{ Money::format($t->indicative_price_sen) }}</p>
            </a>
        @empty
            <p class="rounded-2xl border border-line bg-surface p-8 text-center text-muted">No tenders match.</p>
        @endforelse
    </div>

    <footer class="flex flex-wrap items-center justify-between gap-2 text-[12.5px] text-muted">
        <span>@if ($tenders->total()) Showing {{ number_format($tenders->firstItem()) }}–{{ number_format($tenders->lastItem()) }} of {{ number_format($tenders->total()) }} @endif</span>
        {{ $tenders->links('pagination.pager') }}
    </footer>
</div>
