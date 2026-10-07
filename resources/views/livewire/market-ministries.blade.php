@php
    use App\Support\Money;
    $th = 'px-3.5 py-3 text-left text-[10.5px] font-bold uppercase tracking-[0.5px] text-muted';
    $td = 'px-3.5 py-2.5 align-top';
    $max = max(1, $ministries[0]['value_sen'] ?? 0);
@endphp
<div class="space-y-4">
    <a href="{{ route('market.index', ['year' => $year]) }}" class="text-[13px] font-semibold text-muted hover:text-ink">← Back to Market Insights</a>
    <x-page-heading title="Spend by ministry" :subtitle="'Awarded value by ministry · '.$yearLabel">
        <x-slot:actions>@include('livewire.partials.market-year-select')</x-slot:actions>
    </x-page-heading>
    @if ($yearNote) <p class="text-[13px] text-warn-ink" role="status">{{ $yearNote }}</p> @endif

    <x-data-table min-width="0">
        <thead class="bg-subtle">
            <tr><th class="{{ $th }}">Ministry</th><th class="{{ $th }} text-right">Tenders</th><th class="{{ $th }} text-right">Awarded value</th></tr>
        </thead>
        <tbody>
        @forelse ($ministries as $m)
            <tr class="border-t border-line">
                <td class="{{ $td }}">
                    <span class="font-semibold">{{ $m['ministry'] }}</span>
                    <div class="mt-1 h-1.5 max-w-md rounded-full bg-hover"><div class="h-1.5 rounded-full bg-info-ink" style="width: {{ round($m['value_sen'] * 100 / $max, 1) }}%"></div></div>
                </td>
                <td class="{{ $td }} text-right">{{ number_format($m['tenders']) }}</td>
                <td class="{{ $td }} whitespace-nowrap text-right">{{ Money::format($m['value_sen']) }}</td>
            </tr>
        @empty
            <tr><td colspan="3" class="px-3 py-10 text-center text-muted">No awards in {{ $yearLabel === 'All years' ? 'any year' : $yearLabel }}.</td></tr>
        @endforelse
        </tbody>
    </x-data-table>
</div>
