@php
    use App\Support\{Money, Percent};
    $card = 'min-w-0 rounded-xl border border-line bg-surface p-4'; // min-w-0: long titles must not stretch the grid on phones
    $c = $r['counts'];
    $rate = $r['win_rate_bp'] === null ? '—' : Percent::format($r['win_rate_bp'], 0);
    $segments = [
        ['In Progress', $c['in_progress'], 'var(--color-info-ink)'],
        ['Awarded', $c['awarded'], 'var(--color-good-ink)'],
        ['Done', $c['done'], 'var(--color-muted)'],
        ['Lost', $c['lost'], 'var(--color-bad-ink)'],
    ];
    $circumference = 2 * M_PI * 40;
    $overview = [
        ['In Progress', $c['in_progress'], $r['due_this_week'].' due this week', route('tenders.index', ['in-progress', ...$reportPeriod->listFilters()])],
        ['Awarded', $c['awarded'], Money::format($r['won_value_sen']).' won', route('tenders.index', ['awarded', ...$reportPeriod->listFilters()])],
        ['Done', $c['done'], 'awaiting result', route('tenders.index', ['done', ...$reportPeriod->listFilters()])],
        ['Lost', $c['lost'].($c['cancelled'] ? ' ('.$c['cancelled'].' cancelled)' : ''), 'incl. cancelled', route('tenders.index', ['lost', ...$reportPeriod->listFilters()])],
        ['Win rate', $rate, $r['decided'] ? $r['won'].' of '.$r['decided'].' decided' : 'no decided bids yet', null],
        ['Portfolio value', Money::format($r['bid_value_sen']), $r['without_value'] ? $r['without_value'].' without a value' : 'bid value', null],
    ];
@endphp
<div class="space-y-4">
    <section class="{{ $card }} space-y-3">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-lg font-semibold">Quick Overview</h1>
                <p class="text-xs text-muted">Every card follows the selected period of WO dates.</p>
            </div>
            <div>@include('reports.period-filter')</div>
        </div>
        @if ($c['total'] === 0)
            <p class="text-sm text-muted">No tenders registered in this period.</p>
        @endif
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
            @foreach ($overview as [$label, $value, $hint, $href])
                @if ($href)
                    <a href="{{ $href }}" class="rounded-lg border border-line bg-canvas p-3 hover:bg-hover">
                @else
                    <div class="rounded-lg border border-line bg-canvas p-3">
                @endif
                    <p class="text-xl font-semibold">{{ $value }}</p>
                    <p class="text-sm">{{ $label }}</p>
                    <p class="text-xs text-muted">{{ $hint }}</p>
                @if ($href) </a> @else </div> @endif
            @endforeach
        </div>
    </section>

    <div class="grid gap-4 lg:grid-cols-2">
        <section class="{{ $card }}">
            <h2 class="font-medium">Upcoming deadlines</h2>
            <p class="mb-2 text-xs text-muted">Live tenders, closing soonest first</p>
            <ul class="divide-y divide-line text-sm">
                @forelse ($deadlines as $t)
                    @php $days = (int) $today->diffInDays($t->closing_date, false); @endphp
                    <li class="flex items-center gap-3 py-2">
                        <x-avatar :user="$t->pic" />
                        <a href="{{ route('tenders.show', $t) }}" class="min-w-0 flex-1">
                            <p class="truncate font-medium">{{ $t->title }}</p>
                            <p class="truncate text-xs text-muted">{{ $t->client }}</p>
                        </a>
                        <span @class(['whitespace-nowrap text-xs', 'font-medium text-bad-ink' => $days <= 3, 'text-muted' => $days > 3])>{{ $t->closing_date->format('d M Y') }}</span>
                    </li>
                @empty
                    <li class="py-2 text-muted">Nothing closing soon.</li>
                @endforelse
            </ul>
        </section>

        <section class="{{ $card }}">
            <h2 class="font-medium">Portfolio mix</h2>
            <p class="mb-3 text-xs text-muted">Where every tender sits, and how it is submitted</p>
            <div class="flex flex-wrap items-center gap-6">
                <div class="relative h-32 w-32">
                    <svg viewBox="0 0 100 100" class="h-32 w-32 -rotate-90" role="img" aria-label="Tenders by status">
                        <circle cx="50" cy="50" r="40" fill="none" stroke="var(--color-subtle)" stroke-width="14" />
                        @php $offset = 0; @endphp
                        @foreach ($segments as [$label, $n, $colour])
                            @if ($n > 0 && $c['total'] > 0)
                                @php $len = $circumference * $n / $c['total']; @endphp
                                <circle cx="50" cy="50" r="40" fill="none" stroke="{{ $colour }}" stroke-width="14"
                                        stroke-dasharray="{{ $len }} {{ $circumference - $len }}" stroke-dashoffset="{{ -$offset }}" />
                                @php $offset += $len; @endphp
                            @endif
                        @endforeach
                    </svg>
                    <div class="absolute inset-0 grid place-content-center text-center">
                        <p class="text-2xl font-semibold">{{ $c['total'] }}</p><p class="text-xs text-muted">tenders</p>
                    </div>
                </div>
                <ul class="space-y-1 text-sm">
                    @foreach ($segments as [$label, $n, $colour])
                        <li class="flex items-center gap-2">
                            <span class="h-2.5 w-2.5 rounded-full" style="background: {{ $colour }}"></span>
                            {{ $label }} <span class="text-muted">{{ $n }} ({{ $c['total'] ? round($n * 100 / $c['total']) : 0 }}%)</span>
                        </li>
                    @endforeach
                </ul>
            </div>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                @foreach (['EP' => 'EP', 'NON_EP' => 'Non-EP'] as $key => $label)
                    @php $m = $r['modes'][$key]; @endphp
                    <div class="rounded-lg border border-line p-3 text-sm">
                        <div class="flex items-baseline justify-between gap-2">
                            <span class="font-medium">{{ $label }} <span class="text-lg">{{ $m['total'] }}</span></span>
                            <span class="whitespace-nowrap">{{ Money::format($m['bid_value_sen']) }}</span>
                        </div>
                        <div class="mt-1 grid grid-cols-4 gap-1 text-xs text-muted">
                            <span>In Progress<br><b class="text-ink">{{ $m['in_progress'] }}</b></span>
                            <span>Done<br><b class="text-ink">{{ $m['done'] }}</b></span>
                            <span>Awarded<br><b class="text-ink">{{ $m['awarded'] }}</b></span>
                            <span>Lost<br><b class="text-ink">{{ $m['lost'] }}</b></span>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <section class="{{ $card }} lg:col-span-2">
            <h2 class="font-medium">PIC summary</h2>
            <p class="mb-2 text-xs text-muted">Tenders and bid value per person in charge</p>
            <div class="relative overflow-x-auto">
                <table class="w-full min-w-[520px] text-sm">
                    <thead class="bg-subtle text-left text-xs uppercase text-muted">
                        <tr><th class="px-3 py-2">PIC</th><th class="px-3 py-2 text-right">Tenders</th><th class="px-3 py-2">Share of value</th><th class="px-3 py-2 text-right">Bid value</th></tr>
                    </thead>
                    <tbody>
                    @foreach ($r['pics'] as $p)
                        <tr class="border-t border-line">
                            <td class="px-3 py-2"><a href="{{ route('status', $reportPeriod->addressParams()) }}" class="hover:underline">{{ $p['name'] }}</a></td>
                            <td class="px-3 py-2 text-right">{{ $p['total'] }}</td>
                            <td class="px-3 py-2">
                                <div class="h-2 w-40 overflow-hidden rounded-full bg-subtle"><div class="h-full bg-good-ink" style="width: {{ $p['share_bp'] / 100 }}%"></div></div>
                            </td>
                            <td class="whitespace-nowrap px-3 py-2 text-right">{{ Money::format($p['bid_value_sen']) }}</td>
                        </tr>
                    @endforeach
                    <tr class="border-t border-line font-semibold">
                        <td class="px-3 py-2">Grand total</td><td class="px-3 py-2 text-right">{{ $r['totals']['total'] }}</td><td></td>
                        <td class="whitespace-nowrap px-3 py-2 text-right">{{ Money::format($r['totals']['bid_value_sen']) }}</td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </section>
        <div class="space-y-4">
            <a href="{{ route('quotations.index') }}" class="{{ $card }} block hover:bg-hover">
                <h2 class="font-medium">Quotations</h2>
                <p class="mt-1 text-sm"><b>{{ $quotes['open'] }}</b> open · {{ Money::format($quotes['open_total_sen']) }}</p>
                <p class="text-sm"><b>{{ $quotes['accepted'] }}</b> accepted in the period · {{ Money::format($quotes['accepted_total_sen']) }}</p>
            </a>
            <a href="{{ route('tenders.index', 'awarded') }}" class="{{ $card }} block hover:bg-hover">
                <h2 class="font-medium">Projects</h2>
                <p class="mt-1 text-sm"><b>{{ $projects['running'] }}</b> running</p>
                <p @class(['text-sm', 'text-bad-ink' => $projects['below_margin'] > 0])><b>{{ $projects['below_margin'] }}</b> below approved margin</p>
                <p class="text-xs text-muted">Current state — not affected by the period.</p>
            </a>
        </div>
    </div>
</div>
