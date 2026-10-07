@php
    use App\Support\{Money, Percent};
    $cols = ['name' => 'PIC', 'total' => 'Total', 'in_progress' => 'In Progress', 'done' => 'Done', 'awarded' => 'Awarded', 'lost' => 'Lost',
        'win_rate_bp' => 'Win rate', 'bid_value_sen' => 'Bid value', 'won_value_sen' => 'Won value'];
    $lists = ['in_progress' => 'in-progress', 'done' => 'done', 'awarded' => 'awarded', 'lost' => 'lost'];
    $cell = fn (array $row, string $k) => match ($k) {
        'win_rate_bp' => $row[$k] === null ? '—' : Percent::format($row[$k], 0),
        'bid_value_sen', 'won_value_sen' => Money::format($row[$k]),
        default => $row[$k],
    };
@endphp
<div class="space-y-4">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold">Status</h1>
            <p class="text-sm text-muted">Per-PIC tender performance</p>
        </div>
        <div>@include('reports.period-filter')</div>
    </div>
    <div class="relative overflow-x-auto rounded-xl border border-line bg-surface">
        <table class="w-full min-w-[860px] text-sm">
            <thead class="bg-subtle text-xs uppercase text-muted">
                <tr>
                    @foreach ($cols as $k => $label)
                        <th @class(['px-3 py-2', 'text-left' => $k === 'name', 'text-right' => $k !== 'name'])>
                            <button type="button" wire:click="sortBy('{{ $k }}')" class="uppercase hover:text-ink">{{ $label }}
                                @if ($sort === $k) {{ $dir === 'asc' ? '↑' : '↓' }} @endif</button>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
            @foreach ($rows as $row)
                <tr wire:key="pic-{{ $row['user_id'] }}" class="border-t border-line">
                    @foreach ($cols as $k => $label)
                        <td @class(['whitespace-nowrap px-3 py-2', 'text-right' => $k !== 'name'])>
                            @if (isset($lists[$k]) && $row[$k] > 0)
                                <a href="{{ route('tenders.index', [$lists[$k], 'pic' => $row['user_id'], ...$reportPeriod->listFilters()]) }}" class="underline">{{ $row[$k] }}</a>
                            @else
                                {{ $cell($row, $k) }}
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
            <tr class="border-t border-line font-semibold">
                @foreach ($cols as $k => $label)
                    <td @class(['whitespace-nowrap px-3 py-2', 'text-right' => $k !== 'name'])>{{ $cell($totals, $k) }}</td>
                @endforeach
            </tr>
            </tbody>
        </table>
    </div>
    <p class="text-xs text-muted">Win rate = Awarded ÷ (Awarded + Lost), cancelled tenders left out. Bid value uses the submitted price, or the estimated value for tenders still in progress.</p>
</div>
