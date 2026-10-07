@php $in = 'rounded-lg border border-line bg-surface px-2 py-1 text-sm'; @endphp
<div class="flex flex-wrap items-center gap-2 text-sm">
    <label class="flex items-center gap-1">Period
        <select wire:model.live="period" class="{{ $in }}" aria-label="Period">
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
    <span class="text-xs text-muted">{{ $reportPeriod->label() }} · by WO date</span>
</div>
@if ($reportPeriod->invalid && $period !== 'all')
    <p class="mt-1 text-xs text-warn-ink">That period wasn't valid — showing all time.</p>
@endif
