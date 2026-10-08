@php
    use App\Support\{Money, Percent};
    $cols = ['name' => 'PIC', 'total' => 'Total', 'in_progress' => 'In Progress', 'done' => 'Done', 'awarded' => 'Awarded', 'lost' => 'Lost',
        'dropped' => 'Dropped', 'win_rate_bp' => 'Win rate', 'bid_value_sen' => 'Bid value', 'won_value_sen' => 'Won value'];
    $lists = ['in_progress' => 'in-progress', 'done' => 'done', 'awarded' => 'awarded', 'lost' => 'lost', 'dropped' => 'dropped'];
    $cell = fn (array $row, string $k) => match ($k) {
        'win_rate_bp' => $row[$k] === null ? '—' : Percent::format($row[$k], 0),
        'bid_value_sen', 'won_value_sen' => Money::format($row[$k]),
        default => $row[$k],
    };
    $th = 'px-3.5 py-3 text-left text-[10.5px] font-bold uppercase tracking-[0.5px] text-muted';
    $td = 'px-3.5 py-3 align-top';
    $drill = fn (array $row, string $k) => route('tenders.index', [$lists[$k], 'pic' => $row['user_id'], ...$reportPeriod->listFilters()]);
@endphp
<div class="space-y-4">
    <x-page-heading title="Status" subtitle="Per-PIC tender performance">
        <x-slot:actions>@include('reports.period-filter')</x-slot:actions>
    </x-page-heading>

    {{-- Desktop / tablet table --}}
    <x-data-table resizable="status" min-width="860px" class="hidden md:block">
        <thead class="bg-subtle"><tr>
            @foreach ($cols as $k => $label)
                <th @class([$th, 'text-right' => $k !== 'name'])>
                    <button type="button" wire:click="sortBy('{{ $k }}')" class="uppercase hover:text-ink">{{ $label }}
                        @if ($sort === $k) <span class="text-ink">{{ $dir === 'asc' ? '↑' : '↓' }}</span> @endif</button>
                </th>
            @endforeach
        </tr></thead>
        <tbody>
        @foreach ($rows as $row)
            <tr wire:key="pic-{{ $row['user_id'] }}" class="border-t border-line hover:bg-subtle">
                @foreach ($cols as $k => $label)
                    <td @class([$td, 'whitespace-nowrap', 'text-right' => $k !== 'name'])>
                        @if ($k === 'name')
                            <span class="flex items-center gap-2"><x-initials :name="$row['name']" /> {{ $row['name'] }}</span>
                        @elseif (isset($lists[$k]) && $row[$k] > 0)
                            <a href="{{ $drill($row, $k) }}" class="underline">{{ $row[$k] }}</a>
                        @else
                            {{ $cell($row, $k) }}
                        @endif
                    </td>
                @endforeach
            </tr>
        @endforeach
        <tr class="border-t border-line bg-subtle font-bold">
            @foreach ($cols as $k => $label)
                <td @class([$td, 'whitespace-nowrap', 'text-right' => $k !== 'name'])>{{ $cell($totals, $k) }}</td>
            @endforeach
        </tr>
        </tbody>
    </x-data-table>

    {{-- Phone cards --}}
    <div class="space-y-2.5 md:hidden">
        @foreach ($rows as $row)
            <div data-pic-card="{{ $row['user_id'] }}" wire:key="pic-card-{{ $row['user_id'] }}" class="min-w-0 rounded-2xl border border-line bg-surface p-4">
                <div class="flex items-center justify-between gap-2">
                    <span class="flex min-w-0 items-center gap-2 font-semibold"><x-initials :name="$row['name']" /> <span class="truncate">{{ $row['name'] }}</span></span>
                    <span class="whitespace-nowrap text-sm font-semibold">{{ $cell($row, 'bid_value_sen') }}</span>
                </div>
                <div class="mt-3 grid grid-cols-5 gap-1.5 text-center text-[11px]">
                    @foreach (['total' => 'Total', 'awarded' => 'Won', 'lost' => 'Lost', 'in_progress' => 'Active', 'win_rate_bp' => 'Win rate'] as $k => $label)
                        <div class="rounded-lg bg-subtle p-1.5"><div class="text-muted">{{ $label }}</div>
                            @if (isset($lists[$k]) && $row[$k] > 0)
                                <a href="{{ $drill($row, $k) }}" class="font-bold underline">{{ $row[$k] }}</a>
                            @else
                                <div class="font-bold">{{ $cell($row, $k) }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    <p class="text-xs text-muted">Win rate = Awarded ÷ (Awarded + Lost), cancelled tenders left out. Bid value uses the submitted price, or the estimated value for tenders still in progress.</p>
</div>
