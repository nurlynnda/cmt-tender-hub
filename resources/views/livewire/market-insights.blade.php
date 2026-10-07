@php
    use App\Support\Money;
    $box = 'flex min-w-0 flex-col justify-between rounded-[14px] border border-line bg-surface px-[18px] py-4';
    $boxLink = $box.' hover:bg-subtle';
    $label = 'text-[11px] font-bold uppercase tracking-[0.5px] text-muted';
    $maxValue = max(1, ...array_column($byYear, 'value_sen'), ...[0]);
    $maxCount = max(1, ...array_column($byYear, 'tenders'), ...[0]);
    $maxMinistry = max(1, $ministries[0]['value_sen'] ?? 0);
    $maxTop = max(1, (int) ($top->first()->value_sen ?? 0));
    $oursPill = '<span data-ours class="ml-1 rounded-full bg-good-bg px-1.5 text-[10.5px] font-bold text-good-ink">Ours</span>';
    $awardedLink = fn (array $extra = []) => route('find-tenders.index', ['status' => 'awarded', ...$extra, ...$range]);
@endphp
<div class="space-y-5">
    <x-page-heading title="Market Insights" subtitle="Government tenders awarded — from the tenders collected in Find Tenders">
        <x-slot:actions>
            @include('livewire.partials.market-year-select')
        </x-slot:actions>
    </x-page-heading>
    @if ($yearNote) <p class="text-[13px] text-warn-ink" role="status">{{ $yearNote }}</p> @endif

    {{-- Right now (open tenders, as in Find Tenders) --}}
    <section>
        <p class="{{ $label }} mb-2">Right now</p>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            @foreach ([
                ['Open tenders', $now['open'], route('find-tenders.index')],
                ['Closing today', $now['closing_today'], route('find-tenders.index', ['from' => $today, 'to' => $today])],
                ['Closing this week', $now['closing_week'], route('find-tenders.index', ['from' => $today, 'to' => $weekEnd])],
            ] as [$name, $n, $href])
                <a href="{{ $href }}" class="{{ $boxLink }}">
                    <span class="text-2xl font-extrabold tracking-tight">{{ number_format($n) }}</span>
                    <span class="mt-1.5 text-[13px] font-semibold text-ink-2">{{ $name }}</span>
                </a>
            @endforeach
        </div>
    </section>

    {{-- The chosen year --}}
    <section>
        <p class="{{ $label }} mb-2">{{ $yearLabel }}</p>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            @foreach ([
                ['Awarded tenders', number_format($summary['tenders'])],
                ['Awarded value', Money::short($summary['value_sen'])],
                ['Contractors', number_format($summary['contractors'])],
            ] as [$name, $value])
                <div class="{{ $box }}">
                    <span class="text-2xl font-extrabold tracking-tight">{{ $value }}</span>
                    <span class="mt-1.5 text-[13px] font-semibold text-ink-2">{{ $name }}</span>
                </div>
            @endforeach
        </div>
        @if ($summary['unpriced'])
            <p class="mt-2 text-xs text-muted">Awarded value excludes {{ number_format($summary['unpriced']) }} {{ $summary['unpriced'] === 1 ? 'award' : 'awards' }} with no published price.</p>
        @endif
    </section>

    {{-- Our company --}}
    <x-card :title="$ownLabel" subtitle="Our place among contractors by awarded value" icon="award">
        @if ($own)
            <div data-own-rank class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-[15px]"><span class="text-xl font-extrabold">#{{ number_format($own['rank']) }} of {{ number_format($own['of']) }}</span>
                    <span class="text-muted">contractors</span> ·
                    <span class="font-semibold">{{ number_format($own['wins']) }} {{ $own['wins'] === 1 ? 'win' : 'wins' }}</span> ·
                    <span class="font-semibold">{{ Money::short($own['value_sen']) }}</span></p>
                <a href="{{ $awardedLink(['ours' => 1]) }}" class="btn btn-outline">See our wins</a>
            </div>
        @else
            <p class="text-[13px] text-muted">No awards in {{ $year === 'all' ? 'any year' : $year }}.</p>
        @endif
    </x-card>

    {{-- Every year --}}
    <div class="grid gap-5 lg:grid-cols-2">
        @foreach ([['Awarded value by year', 'value_sen', $maxValue], ['Tenders awarded by year', 'tenders', $maxCount]] as [$title, $field, $max])
            <x-card :title="$title" subtitle="Every year, by closing date" icon="chart">
                <ul class="space-y-2.5 text-[13px]">
                    @forelse ($byYear as $row)
                        <li>
                            <div class="flex justify-between gap-3"><span class="font-semibold">{{ $row['year'] }}</span>
                                <span>{{ $field === 'value_sen' ? Money::short($row['value_sen']) : number_format($row['tenders']) }}</span></div>
                            <div class="mt-1 h-2 rounded-full bg-hover"><div class="h-2 rounded-full bg-good-ink" style="width: {{ round($row[$field] * 100 / $max, 1) }}%"></div></div>
                        </li>
                    @empty
                        <li class="text-muted">No awards yet.</li>
                    @endforelse
                </ul>
            </x-card>
        @endforeach
    </div>

    <div class="grid gap-5 xl:grid-cols-2">
        {{-- Spend by ministry --}}
        <x-card title="Spend by ministry" :subtitle="'Top 10 · '.$yearLabel" icon="chart">
            <x-slot:actions><a href="{{ route('market.ministries', ['year' => $year]) }}" class="btn btn-outline">See all</a></x-slot:actions>
            <ul class="space-y-2.5 text-[13px]">
                @forelse ($ministries as $m)
                    <li>
                        <div class="flex justify-between gap-3"><span class="min-w-0 truncate font-semibold" title="{{ $m['ministry'] }}">{{ $m['ministry'] }}</span>
                            <span class="whitespace-nowrap">{{ Money::short($m['value_sen']) }} <span class="text-muted">· {{ number_format($m['tenders']) }}</span></span></div>
                        <div class="mt-1 h-2 rounded-full bg-hover"><div class="h-2 rounded-full bg-info-ink" style="width: {{ round($m['value_sen'] * 100 / $maxMinistry, 1) }}%"></div></div>
                    </li>
                @empty
                    <li class="text-muted">No awards in {{ $yearLabel === 'All years' ? 'any year' : $yearLabel }}.</li>
                @endforelse
            </ul>
        </x-card>

        {{-- Top contractors --}}
        <x-card title="Top contractors" :subtitle="'By awarded value · '.$yearLabel" icon="staff">
            <x-slot:actions><a href="{{ route('market.contractors', ['year' => $year]) }}" class="btn btn-outline">See all</a></x-slot:actions>
            <ul class="space-y-1 text-[13px]">
                @forelse ($top as $c)
                    @php $isOurs = in_array($c->name_key, $ownKeys, true); @endphp
                    <li @class(['rounded-lg px-2 py-1.5', 'bg-accent-tint' => $isOurs])>
                        <div class="flex justify-between gap-3">
                            <a href="{{ $awardedLink(['contractor' => $c->name]) }}" class="min-w-0 truncate font-semibold hover:underline" title="{{ $c->name }}">{{ $loop->iteration }}. {{ $c->name }}</a>
                            <span class="whitespace-nowrap">@if ($isOurs){!! $oursPill !!}@endif {{ Money::short((int) $c->value_sen) }} <span class="text-muted">· {{ number_format($c->wins) }} {{ (int) $c->wins === 1 ? 'win' : 'wins' }}</span></span>
                        </div>
                        <div class="mt-1 h-2 rounded-full bg-hover"><div class="h-2 rounded-full bg-good-ink" style="width: {{ round($c->value_sen * 100 / $maxTop, 1) }}%"></div></div>
                    </li>
                @empty
                    <li class="text-muted">No awards in {{ $yearLabel === 'All years' ? 'any year' : $yearLabel }}.</li>
                @endforelse
                @if ($own && $own['rank'] > 10)
                    <li data-own-extra class="rounded-lg bg-accent-tint px-2 py-1.5">
                        <div class="flex justify-between gap-3">
                            <a href="{{ $awardedLink(['ours' => 1]) }}" class="min-w-0 truncate font-semibold hover:underline">… {{ $own['rank'] }}. {{ $ownLabel }}</a>
                            <span class="whitespace-nowrap">{!! $oursPill !!} {{ Money::short($own['value_sen']) }} <span class="text-muted">· {{ number_format($own['wins']) }} {{ $own['wins'] === 1 ? 'win' : 'wins' }}</span></span>
                        </div>
                    </li>
                @endif
            </ul>
        </x-card>
    </div>
</div>
