@php
    use App\Support\Money;
    $th = 'px-3.5 py-3 text-left text-[10.5px] font-bold uppercase tracking-[0.5px] text-muted';
    $td = 'px-3.5 py-2.5 align-top';
    $searching = trim($search) !== '';
@endphp
<div class="space-y-4">
    <a href="{{ route('market.index', ['year' => $year]) }}" class="text-[13px] font-semibold text-muted hover:text-ink">← Back to Market Insights</a>
    <x-page-heading title="Top contractors" :subtitle="'By awarded value · '.$yearLabel">
        <x-slot:actions>@include('livewire.partials.market-year-select')</x-slot:actions>
    </x-page-heading>
    @if ($yearNote) <p class="text-[13px] text-warn-ink" role="status">{{ $yearNote }}</p> @endif

    <label class="flex items-center gap-2 rounded-2xl border border-line bg-surface px-4 py-2">
        <x-icon name="search" class="h-4 w-4 text-muted-2" />
        <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search contractors"
               class="min-w-0 flex-1 bg-transparent py-1.5 text-sm outline-none placeholder:text-muted-2">
    </label>

    <x-data-table min-width="0">
        <thead class="bg-subtle">
            <tr>
                <th class="{{ $th }}">Contractor</th><th class="{{ $th }} text-right">Wins</th><th class="{{ $th }} text-right">Awarded value</th>
            </tr>
        </thead>
        <tbody>
        @forelse ($contractors as $c)
            @php $isOurs = in_array($c->name_key, $ownKeys, true); @endphp
            <tr wire:key="mc-{{ md5($c->name_key) }}" @class(['border-t border-line', 'bg-accent-tint' => $isOurs])>
                <td class="{{ $td }}">
                    <a href="{{ route('find-tenders.index', ['status' => 'awarded', 'contractor' => $c->name, ...$range]) }}" class="font-semibold hover:underline">{{ ($searching ? '' : ($contractors->firstItem() + $loop->index).'. ').$c->name }}</a>
                    @if ($isOurs) <span data-ours class="ml-1 rounded-full bg-good-bg px-1.5 text-[10.5px] font-bold text-good-ink">Ours</span> @endif
                </td>
                <td class="{{ $td }} text-right">{{ number_format($c->wins) }}</td>
                <td class="{{ $td }} whitespace-nowrap text-right">{{ Money::format((int) $c->value_sen) }}</td>
            </tr>
        @empty
            <tr><td colspan="3" class="px-3 py-10 text-center text-muted">No contractors match.</td></tr>
        @endforelse
        </tbody>
    </x-data-table>

    <footer class="flex flex-wrap items-center justify-between gap-2 text-[12.5px] text-muted">
        <span>@if ($contractors->total()) Showing {{ number_format($contractors->firstItem()) }}–{{ number_format($contractors->lastItem()) }} of {{ number_format($contractors->total()) }} contractors @endif</span>
        {{ $contractors->links('pagination.pager') }}
    </footer>
</div>
