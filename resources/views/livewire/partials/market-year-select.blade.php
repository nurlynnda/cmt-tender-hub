{{-- Year picker for the Market Insights pages: "2023 – now" plus each year from then with awards. --}}
<select wire:model.live="year" class="rounded-[9px] border border-line-2 bg-surface px-2.5 py-1.5 text-[13px]" aria-label="Year">
    <option value="all">{{ \App\Market\MarketReport::periodLabel() }}</option>
    @foreach ($years as $yy) <option value="{{ $yy }}">{{ $yy }}</option> @endforeach
    @unless ($year === 'all' || in_array((int) $year, $years, true)) <option value="{{ $year }}">{{ $year }}</option> @endunless
</select>
