@php
    use App\Support\{Money, Percent};
    $card = 'min-w-0 rounded-[20px] border border-line bg-surface p-5'; // min-w-0: long titles must not stretch the grid on phones
    $tile = 'grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-accent-tint text-good-ink';
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
    $modeTotal = $r['modes']['EP']['total'] + $r['modes']['NON_EP']['total'];
    $epPct = $modeTotal ? (int) round($r['modes']['EP']['total'] * 100 / $modeTotal) : 0;
    $th = 'px-3.5 py-2.5 text-[10.5px] font-bold uppercase tracking-[0.5px] text-muted';
    $kpi = 'flex min-w-0 flex-col justify-between rounded-[14px] border border-[var(--qo-card-border)] bg-[var(--qo-card)] px-[18px] py-4 shadow-[0_6px_18px_var(--shadow-soft)] backdrop-blur-[6px]';
@endphp
<div class="space-y-5">
    {{-- Quick Overview (prototype: lavender gradient panel with soft glows) --}}
    <section class="relative overflow-hidden rounded-[20px] border border-line px-6 pb-6 pt-5"
             style="background: linear-gradient(115deg, var(--qo-1) 0%, var(--qo-2) 40%, var(--qo-3) 72%, var(--qo-4) 100%)">
        <div class="pointer-events-none absolute -right-[110px] -top-[130px] h-[360px] w-[360px] rounded-full" style="background: radial-gradient(circle at 35% 35%, rgba(140,196,246,.35), rgba(140,196,246,0) 68%)"></div>
        <div class="pointer-events-none absolute -bottom-[150px] -left-[120px] h-[340px] w-[340px] rounded-full" style="background: radial-gradient(circle at 60% 40%, rgba(186,164,255,.35), rgba(186,164,255,0) 70%)"></div>
        <div class="relative">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <h1 class="text-base font-bold">Quick Overview</h1>
                    <p class="mt-0.5 text-xs text-muted">Every card below follows the selected period of WO dates.</p>
                </div>
                <div>@include('reports.period-filter')</div>
            </div>
            @if ($c['total'] === 0) <p class="mt-3 text-sm text-muted">No tenders registered in this period.</p> @endif
            <div class="mt-[18px] grid grid-cols-2 gap-[13px] md:grid-cols-3 xl:grid-cols-6">
                @foreach ($overview as [$label, $value, $hint, $href])
                    @if ($href)
                        <a href="{{ $href }}" data-kpi="{{ $label }}" class="{{ $kpi }} hover:bg-surface">
                    @else
                        <div data-kpi="{{ $label }}" class="{{ $kpi }}">
                    @endif
                        <div class="flex flex-wrap items-baseline gap-x-[7px]">
                            <span class="text-2xl font-extrabold tracking-tight">{{ $value }}</span>
                            <span class="text-[12px] text-muted-2">({{ $hint }})</span>
                        </div>
                        <div class="mt-1.5 text-[13px] font-semibold text-ink-2">{{ $label }}</div>
                    @if ($href) </a> @else </div> @endif
                @endforeach
            </div>
        </div>
    </section>

    <div class="grid gap-5 lg:grid-cols-[minmax(0,38fr)_minmax(0,62fr)]">
        {{-- Upcoming deadlines --}}
        <section class="{{ $card }} lg:self-start">
            <div class="mb-3 flex items-center gap-3">
                <span class="{{ $tile }}"><x-icon name="calendar" class="h-[17px] w-[17px]" /></span>
                <div><h2 class="font-bold">Upcoming deadlines</h2><p class="text-xs text-muted">Live tenders, closing soonest first</p></div>
            </div>
            <ul class="divide-y divide-line text-sm">
                @forelse ($deadlines as $t)
                    @php $days = (int) $today->diffInDays($t->closing_date, false); @endphp
                    <li class="flex items-center gap-3 py-2.5">
                        <x-avatar :user="$t->pic" />
                        <a href="{{ route('tenders.show', $t) }}" class="min-w-0 flex-1">
                            <p class="truncate font-semibold">{{ $t->title }}</p>
                            <p class="truncate text-xs text-muted">{{ $t->client }}</p>
                        </a>
                        <span @class(['whitespace-nowrap text-xs font-semibold', 'text-bad-ink' => $days <= 3, 'text-muted' => $days > 3])>{{ $t->closing_date->format('d M Y') }}</span>
                    </li>
                @empty
                    <li class="py-2 text-muted">Nothing closing soon.</li>
                @endforelse
            </ul>
        </section>

        <div class="min-w-0 space-y-5">
            {{-- Portfolio mix --}}
            <section class="{{ $card }}">
                <div class="mb-4 flex items-center gap-3">
                    <span class="{{ $tile }}"><x-icon name="chart" class="h-[17px] w-[17px]" /></span>
                    <div><h2 class="font-bold">Portfolio mix</h2><p class="text-xs text-muted">Where every tender sits, and how it is submitted</p></div>
                </div>
                <div class="grid gap-6 xl:grid-cols-2">
                    <div class="min-w-0">
                        <p class="mb-2 text-[11px] font-bold uppercase tracking-[0.5px] text-muted">By status · {{ $reportPeriod->label() }}</p>
                        <div class="flex flex-wrap items-center gap-6">
                            <div class="relative h-32 w-32">
                                <svg viewBox="0 0 100 100" class="h-32 w-32 -rotate-90" role="img" aria-label="Tenders by status">
                                    <circle cx="50" cy="50" r="40" fill="none" stroke="var(--color-hover)" stroke-width="14" />
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
                                    <p class="text-2xl font-extrabold">{{ $c['total'] }}</p><p class="text-xs text-muted">tenders</p>
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
                    </div>
                    <div class="min-w-0">
                        <p class="mb-2 text-[11px] font-bold uppercase tracking-[0.5px] text-muted">By submission mode · {{ $reportPeriod->label() }}</p>
                        @if ($modeTotal > 0)
                            <div data-mode-bar data-ep-pct="{{ $epPct }}" class="mb-3 flex h-2.5 overflow-hidden rounded-full bg-hover" title="EP {{ $epPct }}% · Non-EP {{ 100 - $epPct }}%">
                                <div class="h-full bg-good-ink" style="width: {{ $epPct }}%"></div>
                                <div class="h-full bg-muted-2" style="width: {{ 100 - $epPct }}%"></div>
                            </div>
                        @endif
                        <div class="space-y-2.5">
                            @foreach (['EP' => ['EP', 'bg-good-ink'], 'NON_EP' => ['Non-EP', 'bg-muted-2']] as $key => [$label, $dot])
                                @php $m = $r['modes'][$key]; @endphp
                                <div class="rounded-xl border border-line p-3 text-sm">
                                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                                        <span class="flex items-center gap-2 font-bold"><span class="h-2 w-2 rounded-full {{ $dot }}"></span>{{ $label }} <span class="text-lg">{{ $m['total'] }}</span>
                                            <span class="text-xs font-normal text-muted">{{ $modeTotal ? round($m['total'] * 100 / $modeTotal) : 0 }}%</span></span>
                                        <span class="whitespace-nowrap font-semibold">{{ Money::format($m['bid_value_sen']) }}</span>
                                    </div>
                                    <div class="mt-1.5 grid grid-cols-4 gap-1 text-xs text-muted">
                                        <span>In Progress<br><b class="text-ink">{{ $m['in_progress'] }}</b></span>
                                        <span>Done<br><b class="text-ink">{{ $m['done'] }}</b></span>
                                        <span>Awarded<br><b class="text-ink">{{ $m['awarded'] }}</b></span>
                                        <span>Lost<br><b class="text-ink">{{ $m['lost'] }}</b></span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </section>

            {{-- PIC summary --}}
            <section class="{{ $card }}">
                <div class="mb-3 flex items-center gap-3">
                    <span class="{{ $tile }}"><x-icon name="staff" class="h-[17px] w-[17px]" /></span>
                    <div><h2 class="font-bold">PIC summary</h2><p class="text-xs text-muted">Tenders and bid value per person in charge</p></div>
                </div>
                <div class="relative overflow-x-auto">
                    <table class="w-full min-w-[520px] text-[13px]">
                        <thead class="bg-subtle"><tr>
                            <th class="{{ $th }} text-left">PIC</th>
                            <th class="{{ $th }} text-right">Tenders</th>
                            <th class="{{ $th }} text-left">Share of value</th>
                            <th class="{{ $th }} text-right">Bid value</th>
                        </tr></thead>
                        <tbody>
                        @foreach ($r['pics'] as $p)
                            <tr class="border-t border-line">
                                <td class="px-3.5 py-2.5"><a href="{{ route('status', $reportPeriod->addressParams()) }}" class="flex items-center gap-2 hover:underline"><x-initials :name="$p['name']" /> {{ $p['name'] }}</a></td>
                                <td class="px-3.5 py-2.5 text-right">{{ $p['total'] }}</td>
                                <td class="px-3.5 py-2.5"><div class="h-2 w-40 overflow-hidden rounded-full bg-hover"><div class="h-full bg-good-ink" style="width: {{ $p['share_bp'] / 100 }}%"></div></div></td>
                                <td class="whitespace-nowrap px-3.5 py-2.5 text-right">{{ Money::format($p['bid_value_sen']) }}</td>
                            </tr>
                        @endforeach
                        <tr class="border-t border-line bg-subtle font-bold">
                            <td class="px-3.5 py-2.5">Grand total</td><td class="px-3.5 py-2.5 text-right">{{ $r['totals']['total'] }}</td><td></td>
                            <td class="whitespace-nowrap px-3.5 py-2.5 text-right">{{ Money::format($r['totals']['bid_value_sen']) }}</td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>

    <div class="grid gap-5 md:grid-cols-2">
        <a href="{{ route('quotations.index') }}" class="{{ $card }} flex gap-3 hover:bg-hover">
            <span class="{{ $tile }}"><x-icon name="quotation" class="h-[17px] w-[17px]" /></span>
            <div><h2 class="font-bold">Quotations</h2>
                <p class="mt-1 text-sm"><b>{{ $quotes['open'] }}</b> open · {{ Money::format($quotes['open_total_sen']) }}</p>
                <p class="text-sm"><b>{{ $quotes['accepted'] }}</b> accepted in the period · {{ Money::format($quotes['accepted_total_sen']) }}</p></div>
        </a>
        <a href="{{ route('tenders.index', 'awarded') }}" class="{{ $card }} flex gap-3 hover:bg-hover">
            <span class="{{ $tile }}"><x-icon name="tenders" class="h-[17px] w-[17px]" /></span>
            <div><h2 class="font-bold">Projects</h2>
                <p class="mt-1 text-sm"><b>{{ $projects['running'] }}</b> running</p>
                <p @class(['text-sm', 'text-bad-ink' => $projects['below_margin'] > 0])><b>{{ $projects['below_margin'] }}</b> below approved margin</p>
                <p class="text-xs text-muted">Current state — not affected by the period.</p></div>
        </a>
    </div>
</div>
