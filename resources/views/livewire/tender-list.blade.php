@php
    use App\Enums\TenderStatus;
    use App\Support\{Money, Percent};
    $isOpen = $status === TenderStatus::InProgress;
    $field = 'w-full rounded-[9px] border border-line-2 bg-surface px-2.5 py-1.5 text-[13px]';
    $th = 'px-3.5 py-3 text-left text-[10.5px] font-bold uppercase tracking-[0.5px] text-muted';
    $td = 'px-3.5 py-3 align-top';
    $arrow = ['deadline_asc' => '↑', 'deadline_desc' => '↓'][$sort] ?? '';
    $docs = fn ($t) => [(int) ($t->documents_done_count ?? 0), (int) ($t->documents_count ?? 0)];
    $deadlineClass = fn (?string $closing) => match ($closing) { 'soon' => 'font-semibold text-warn-ink', 'overdue' => 'font-semibold text-bad-ink', default => '' };
    // The list's own money columns: [label, value] pairs, used by the table and the phone cards
    $extra = fn ($t) => match ($status) {
        TenderStatus::Done => [['Submit Price', Money::format($t->submitted_price_sen)], ['Company Variant', $t->companyVariant() ?? '—'],
            ['Gross', ($g = $t->costingSummary()) ? Percent::format($g['margin_bp']) : '—']],
        TenderStatus::Lost => [['Submitted Price', Money::format($t->submitted_price_sen)], ['Win Price', Money::format($t->winning_price_sen)],
            ['Win Variant', $t->winVariant() ?? '—']],
        TenderStatus::Awarded => [['Submit Price', Money::format($t->submitted_price_sen)]],
        TenderStatus::Dropped => [['Reason', $t->drop_reason ?? '—']],
        default => [],
    };
    $textExtra = $status === TenderStatus::Dropped; // the Dropped reason is text: left-aligned and allowed to wrap
    // Header labels for $extra: must match its pairs, in order (headings need no model calls)
    $extraLabels = match ($status) {
        TenderStatus::Done => ['Submit Price', 'Company Variant', 'Gross'],
        TenderStatus::Lost => ['Submitted Price', 'Win Price', 'Win Variant'],
        TenderStatus::Awarded => ['Submit Price'],
        TenderStatus::Dropped => ['Reason'],
        default => [],
    };
    // Actual GP from the PD: no percentage until the customer has been invoiced (0% would read as "no profit")
    $actualGp = function ($t) {
        $pd = $t->project?->summary();
        if (! $pd) return ['—', false, null];
        $closed = $t->project->isOpen() ? null : 'Closed';
        if ($pd['pnl']['actual']['revenue'] <= 0) return ['—', false, $closed];
        return [Percent::format($pd['pnl']['actual']['gp_bp']), $pd['below_margin'], $closed];
    };
@endphp
<div class="space-y-4">
    <x-page-heading :title="$status->listTitle()" :subtitle="$status->listSubtitle()">
        @if ($isOpen)
            <x-slot:actions>
                <button type="button" wire:click="$dispatch('open-register-tender')"
                        class="flex items-center gap-1.5 rounded-[11px] bg-accent px-4 py-2.5 text-[12.5px] font-bold text-accent-ink hover:bg-accent-solid">
                    <x-icon name="plus" class="h-[15px] w-[15px]" /> Register Tender
                </button>
            </x-slot:actions>
        @endif
    </x-page-heading>

    <x-filter-bar :count="$this->filterCount()" placeholder="Search WO, code, title, agency" :mine="$mine">
        <x-filter-field label="PIC">
            <select wire:model.live="pic" class="{{ $field }}"><option value="">All PICs</option>
                @foreach ($people as $p) <option value="{{ $p->id }}">{{ $p->name }}</option> @endforeach</select>
        </x-filter-field>
        <x-filter-field label="Agency">
            <select wire:model.live="agency" class="{{ $field }}"><option value="">All agencies</option>
                @foreach ($agencies as $a) <option value="{{ $a }}">{{ $a }}</option> @endforeach</select>
        </x-filter-field>
        <x-filter-field label="Tender mode">
            <select wire:model.live="mode" class="{{ $field }}"><option value="">All modes</option>
                @foreach ($modes as $m) <option value="{{ $m->value }}">{{ $m->label() }}</option> @endforeach</select>
        </x-filter-field>
        <x-filter-field label="Category">
            <select wire:model.live="category" class="{{ $field }}"><option value="">All categories</option>
                @foreach ($categories as $c) <option value="{{ $c->value }}">{{ $c->value }}</option> @endforeach</select>
        </x-filter-field>
        <x-filter-field label="Deadline from"><input type="date" wire:model.live="from" class="{{ $field }}"></x-filter-field>
        <x-filter-field label="Deadline to"><input type="date" wire:model.live="to" class="{{ $field }}"></x-filter-field>
    </x-filter-bar>

    @if ($wo_from !== '' && $wo_to !== '')
        <p class="text-sm text-muted">
            Registered {{ \Carbon\CarbonImmutable::parse($wo_from)->format('d M Y') }} – {{ \Carbon\CarbonImmutable::parse($wo_to)->format('d M Y') }}
            (from the Dashboard / Status) · <button type="button" wire:click="$set('wo_from', ''); $set('wo_to', '')" class="underline">show all dates</button>
        </p>
    @endif

    {{-- Desktop / tablet table --}}
    <x-data-table :resizable="'list-'.$list" class="hidden md:block">
        <thead class="bg-subtle">
            <tr>
                <th class="{{ $th }}">WO Number</th>
                <th class="{{ $th }}">Tender</th>
                <th class="{{ $th }}">Agency</th>
                <th class="{{ $th }}">Assigned To</th>
                <th class="{{ $th }}" aria-sort="{{ ['deadline_asc' => 'ascending', 'deadline_desc' => 'descending'][$sort] ?? 'none' }}">
                    <button type="button" wire:click="toggleDeadlineSort" title="Sort by deadline" class="uppercase hover:text-ink">Deadline <span class="text-ink">{{ $arrow }}</span></button>
                </th>
                @if ($isOpen) <th class="{{ $th }}">Briefing</th> @endif
                <th class="{{ $th }} text-right">Est. Value</th>
                @if ($isOpen) <th class="{{ $th }}">Documents</th> @endif
                @foreach ($extraLabels as $label) <th class="{{ $th }} {{ $textExtra ? '' : 'text-right' }}">{{ $label }}</th> @endforeach
                @if ($status === TenderStatus::Awarded) <th class="{{ $th }} text-right" title="Actual gross profit from the PD">Actual GP</th> @endif
            </tr>
        </thead>
        <tbody>
        @forelse ($tenders as $t)
            @php $closing = $t->closingState(); [$done, $total] = $docs($t); @endphp
            <tr wire:key="tender-{{ $t->id }}" data-closing="{{ $closing }}" onclick="window.location='{{ route('tenders.show', $t) }}'"
                class="cursor-pointer border-t border-line hover:bg-subtle">
                <td class="{{ $td }} whitespace-nowrap">
                    <a href="{{ route('tenders.show', $t) }}" class="font-bold hover:underline">{{ $t->wo_number }}</a>
                    <div class="text-[11.5px] text-muted-2">{{ $t->wo_date->format('d M Y') }}</div>
                </td>
                <td class="{{ $td }}">
                    <div class="text-[11px] font-semibold text-muted-2">{{ $t->tender_code }}</div>
                    <div class="line-clamp-2" title="{{ $t->title }}">{{ $t->title }}</div>
                    @if ($t->was_cancelled) <span class="mt-1 inline-block rounded bg-bad-bg px-1.5 text-xs text-bad-ink">Cancelled</span> @endif
                </td>
                <td class="{{ $td }}">{{ $t->client }}@if ($t->ministry && $t->ministry !== $t->client) <div class="text-[11.5px] text-muted-2">{{ $t->ministry }}</div> @endif</td>
                <td class="{{ $td }}"><div class="flex items-center gap-2"><x-avatar :user="$t->pic" /> <span>{{ $t->pic->name }}</span></div></td>
                {{-- Rows stay plain like the other lists; only the date shows closing soon (amber) or overdue (red) --}}
                <td @if ($closing) data-deadline="{{ $closing }}" @endif class="{{ $td }} whitespace-nowrap {{ $deadlineClass($closing) }}">{{ $t->closing_date->format('d M Y') }}</td>
                @if ($isOpen)
                    <td class="{{ $td }}"><span @class(['rounded-full px-2 py-0.5 text-[11px] font-semibold', 'bg-good-bg text-good-ink' => $t->has_briefing, 'bg-bad-bg text-bad-ink' => ! $t->has_briefing])>{{ $t->has_briefing ? 'Yes' : 'No' }}</span></td>
                @endif
                <td class="{{ $td }} whitespace-nowrap text-right">{{ Money::format($t->estimated_value_sen) }}</td>
                @if ($isOpen)
                    <td class="{{ $td }}"><div class="flex items-center gap-2">
                        <div class="h-1.5 w-16 overflow-hidden rounded-full bg-hover"><div class="h-full bg-good-ink" style="width: {{ $t->documentPercent() }}%"></div></div>
                        <span class="text-[11.5px] text-muted">{{ $done }}/{{ $total }}</span></div></td>
                @endif
                @foreach ($extra($t) as [, $value]) <td class="{{ $td }} {{ $textExtra ? '' : 'whitespace-nowrap text-right' }}">{{ $value }}</td> @endforeach
                @if ($status === TenderStatus::Awarded)
                    @php [$gp, $below, $closed] = $actualGp($t); @endphp
                    <td class="{{ $td }} whitespace-nowrap text-right"><span @class(['text-bad-ink' => $below])>{{ $gp }}</span>
                        @if ($closed) <span class="ml-1 rounded-full bg-subtle px-2 py-0.5 text-xs">{{ $closed }}</span> @endif</td>
                @endif
            </tr>
        @empty
            <tr><td colspan="12" class="px-3 py-10 text-center text-muted">No tenders match your search.</td></tr>
        @endforelse
        </tbody>
    </x-data-table>

    {{-- Phone cards --}}
    <div class="space-y-2.5 md:hidden">
        @forelse ($tenders as $t)
            @php $closing = $t->closingState(); [$done, $total] = $docs($t); @endphp
            <a href="{{ route('tenders.show', $t) }}" wire:key="card-{{ $t->id }}" data-card="{{ $t->wo_number }}" class="block min-w-0 rounded-2xl border border-line bg-surface p-4 active:bg-subtle">
                <div class="flex items-center justify-between gap-2 text-[12px]">
                    <span class="font-bold">{{ $t->wo_number }}</span>
                    <span class="{{ $deadlineClass($closing) ?: 'text-muted' }}">Due {{ $t->closing_date->format('d M Y') }}</span>
                </div>
                <p class="mt-1.5 line-clamp-2 text-[13.5px] font-semibold">{{ $t->title }}</p>
                <p class="mt-0.5 truncate text-[11.5px] text-muted">{{ $t->client }}{{ $t->ministry && $t->ministry !== $t->client ? ' · '.$t->ministry : '' }} · {{ $t->tender_code }}</p>
                <div class="mt-2.5 flex items-center justify-between gap-2 text-[12.5px]">
                    <span class="flex min-w-0 items-center gap-2"><x-avatar :user="$t->pic" /> <span class="truncate">{{ $t->pic->name }}</span></span>
                    <span class="whitespace-nowrap font-semibold">{{ Money::format($t->estimated_value_sen) }}</span>
                </div>
                @if ($isOpen)
                    <div class="mt-2.5 flex items-center gap-2 text-[11.5px] text-muted">
                        <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-hover"><div class="h-full bg-good-ink" style="width: {{ $t->documentPercent() }}%"></div></div>
                        Docs {{ $done }}/{{ $total }} · Briefing {{ $t->has_briefing ? 'Yes' : 'No' }}
                    </div>
                @endif
                @if ($extraLabels)
                    <div class="mt-2.5 grid grid-cols-3 gap-2 rounded-xl bg-subtle p-2.5 text-[11px]">
                        @foreach ($extra($t) as [$label, $value]) <div class="min-w-0"><div class="text-muted">{{ $label }}</div><div class="truncate font-semibold">{{ $value }}</div></div> @endforeach
                        @if ($status === TenderStatus::Awarded) <div><div class="text-muted">Actual GP</div><div class="font-semibold">{{ $actualGp($t)[0] }}</div></div> @endif
                    </div>
                @endif
            </a>
        @empty
            <p class="rounded-2xl border border-line bg-surface p-8 text-center text-muted">No tenders match your search.</p>
        @endforelse
    </div>

    <footer class="flex flex-wrap items-center justify-between gap-2 text-[12.5px] text-muted">
        <span>@if ($tenders->total()) Showing {{ $tenders->firstItem() }}–{{ $tenders->lastItem() }} of {{ $tenders->total() }} tenders @endif</span>
        {{ $tenders->links('pagination.pager') }}
    </footer>

    @if ($isOpen) <livewire:register-tender-modal /> @endif
</div>
