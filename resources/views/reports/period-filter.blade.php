@php $in = 'rounded-lg border border-line-2 bg-surface px-2.5 py-1.5 text-xs'; @endphp
<div class="flex flex-wrap items-center gap-2 rounded-[11px] border border-[var(--qo-card-border)] bg-[var(--qo-card)] py-1.5 pl-3 pr-1.5 text-sm">
    <label class="flex items-center gap-2 text-[11.5px] font-bold text-ink-2">Period
        <select wire:model.live="period" class="{{ $in }} font-normal" aria-label="Period">
            @foreach (['all' => 'All time', 'this_month' => 'This month', 'last_month' => 'Last month', 'this_year' => 'This year', 'month' => 'A month…', 'custom' => 'Custom…'] as $k => $label)
                <option value="{{ $k }}">{{ $label }}</option>
            @endforeach
        </select>
    </label>
    @if ($period === 'month')
        <input type="month" wire:model.live="month" class="{{ $in }}" aria-label="Month">
    @elseif ($period === 'custom')
        <input type="date" wire:model.live="from" class="{{ $in }}" aria-label="From">
        <span class="text-muted">to</span>
        <input type="date" wire:model.live="to" class="{{ $in }}" aria-label="To">
    @endif
    <span class="pr-1.5 text-[11px] text-muted">{{ $reportPeriod->label() }} · by WO date</span>
</div>
@if ($reportPeriod->invalid && $period !== 'all')
    <p class="mt-1 text-xs text-warn-ink">That period wasn't valid — showing all time.</p>
@endif
