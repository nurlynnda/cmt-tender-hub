@php
    use App\Support\{Money, Percent};
    $in = 'rounded border border-line bg-surface px-1.5 py-1 text-sm disabled:border-transparent disabled:bg-transparent';
    $cost = $summary['total_cost_sen'];
    $bid = $summary['bid_price_sen'];
    $costPct = $bid > 0 ? (int) min(100, max(0, round($cost * 100 / $bid))) : 0;
    $card = 'rounded-xl border border-line bg-surface p-3';
@endphp
{{-- "typed" covers edits still in the box that have not reached the server yet; the page is told
     on the first keystroke so Mark Done is blocked straight away. The import box is not an edit. --}}
<section class="space-y-4" x-data="{ typed: false }"
         x-on:input="if ($event.target.tagName !== 'TEXTAREA' && ! typed) { typed = true; $wire.$dispatch('costing-dirty', { dirty: true }) }"
         x-on:costing-saved.window="typed = false"
         x-init="window.addEventListener('beforeunload', e => { if (typed || $wire.unsaved) { e.preventDefault(); e.returnValue = ''; } })">
    @if ($conflict)
        <div class="flex items-center justify-between gap-3 rounded-lg bg-warn-bg p-3 text-sm text-warn-ink" role="alert">
            <span>{{ $conflict }} Your costing edits are still on screen — note down anything you need before reloading.</span>
            <a href="{{ route('tenders.show', $tender) }}?tab=costing" class="shrink-0 font-medium underline">Reload</a>
        </div>
    @endif

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
        <div class="{{ $card }}">
            <p class="text-xs uppercase text-muted">Total cost</p>
            <p class="text-lg font-semibold">{{ Money::format($cost) }}</p>
        </div>
        <div class="{{ $card }}">
            <p class="text-xs uppercase text-muted">Bid price · {{ $summary['is_override'] ? 'Your price' : 'Suggested' }}</p>
            <p class="text-lg font-semibold">{{ Money::format($bid) }}</p>
            @if ($summary['is_override'])
                <p class="text-xs text-muted">Suggested {{ Money::format($summary['suggested_bid_sen']) }}
                    @if ($editable) · <button type="button" wire:click="resetOverride" class="underline">Reset to suggested</button>@endif
                </p>
            @endif
        </div>
        <div class="{{ $card }}">
            <p class="text-xs uppercase text-muted">Margin</p>
            <p class="text-lg font-semibold">{{ Money::format($summary['margin_sen']) }}</p>
        </div>
        <div @class(['rounded-xl border p-3', 'border-line bg-surface' => ! $summary['below_target'], 'border-bad-ink bg-bad-bg text-bad-ink' => $summary['below_target']])>
            <p class="text-xs uppercase">Margin %</p>
            <p class="text-lg font-semibold">{{ Percent::format($summary['margin_bp']) }}</p>
            @if ($summary['below_target'])
                <p class="text-xs">⚠ Below the {{ $target }} target</p>
            @endif
        </div>
        <div class="{{ $card }}">
            <p class="text-xs uppercase text-muted">Under budget</p>
            <p class="text-lg font-semibold">{{ $summary['under_budget_bp'] === null ? '—' : Percent::format($summary['under_budget_bp']) }}</p>
            @if ($tender->estimated_value_sen)
                <p class="text-xs text-muted">Estimated {{ Money::format($tender->estimated_value_sen) }}</p>
            @endif
        </div>
    </div>
    <div class="flex h-2 overflow-hidden rounded-full bg-good-bg" title="Cost {{ $costPct }}% of the bid price">
        <div class="h-full bg-chip" style="width: {{ $costPct }}%"></div>
    </div>

    <div class="flex flex-wrap items-end gap-3 text-sm">
        <label class="flex items-center gap-1">Default margin %
            <input wire:model.live.blur="defaultMargin" @disabled(! $editable) class="w-20 rounded border border-line bg-surface px-2 py-1"></label>
        @if ($editable)
            <button type="button" wire:click="applyDefaultToAll" wire:confirm="Set every line's margin to the default?" class="rounded-lg border border-line px-3 py-1 hover:bg-hover">Apply to all lines</button>
        @endif
        <label class="flex items-center gap-1 sm:ml-auto">Your bid price (optional)
            <input wire:model.live.blur="override" @disabled(! $editable) placeholder="{{ Money::toInput($summary['suggested_bid_sen']) }}" class="w-36 rounded border border-line bg-surface px-2 py-1"></label>
        @error('defaultMargin') <p class="w-full text-xs text-bad-ink">{{ $message }}</p> @enderror
        @error('override') <p class="w-full text-xs text-bad-ink">{{ $message }}</p> @enderror
    </div>

    <div class="relative overflow-x-auto rounded-xl border border-line bg-surface">
        <table class="w-full min-w-[1550px] text-sm">
            <thead class="bg-subtle text-left text-xs uppercase text-muted">
                <tr>
                    <th class="px-2 py-2">Item</th><th class="px-2">Qty</th><th class="px-2">Unit</th><th class="px-2">Frequency</th><th class="px-2">Year</th>
                    <th class="px-2 text-right">Unit cost</th><th class="px-2 text-right">Line cost</th><th class="px-2">Margin %</th>
                    <th class="px-2 text-right">Price/unit</th><th class="px-2 text-right">Selling price</th><th class="px-2">Vendor</th>
                    <th class="px-2">Quotation link</th><th class="px-2"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody>
            @forelse ($lines as $i => $line)
                @php $calc = $summary['lines'][$i] ?? null; $hasSubs = ! empty($line['sub_items']); @endphp
                <tr wire:key="line-{{ $i }}" class="border-t border-line align-top">
                    <td class="px-2 py-1">
                        <input wire:model.live.blur="lines.{{ $i }}.description" @disabled(! $editable) class="{{ $in }} w-full min-w-80" aria-label="Item">
                        @error("lines.$i.description") <span class="text-xs text-bad-ink">{{ $message }}</span> @enderror
                    </td>
                    <td class="px-2 py-1">
                        <input wire:model.live.blur="lines.{{ $i }}.quantity" @disabled(! $editable) class="{{ $in }} w-16" aria-label="Quantity">
                        @error("lines.$i.quantity") <span class="text-xs text-bad-ink">{{ $message }}</span> @enderror
                    </td>
                    <td class="px-2 py-1">
                        <input wire:model.live.blur="lines.{{ $i }}.unit" @disabled(! $editable) class="{{ $in }} w-16" aria-label="Unit">
                        @error("lines.$i.unit") <span class="text-xs text-bad-ink">{{ $message }}</span> @enderror
                    </td>
                    <td class="whitespace-nowrap px-2 py-1">
                        <select wire:model.live="lines.{{ $i }}.frequency" @disabled(! $editable) class="{{ $in }} w-28" aria-label="Frequency">
                            <option value="one_off">One-off</option>
                            <option value="monthly">Monthly</option>
                        </select>
                        @if (($line['frequency'] ?? '') === 'monthly')
                            <div class="mt-1">× <input wire:model.live.blur="lines.{{ $i }}.months" @disabled(! $editable) class="{{ $in }} w-14" aria-label="Months"> months</div>
                            @error("lines.$i.months") <span class="block text-xs text-bad-ink">{{ $message }}</span> @enderror
                        @endif
                    </td>
                    <td class="px-2 py-1">
                        <select wire:model.live="lines.{{ $i }}.project_year" @disabled(! $editable) class="{{ $in }} w-20" aria-label="Year">
                            @foreach (range(1, 7) as $y) <option value="{{ $y }}">Y{{ $y }}</option> @endforeach
                        </select>
                    </td>
                    <td class="whitespace-nowrap px-2 py-1 text-right">
                        @if ($hasSubs)
                            <span title="Total of the sub-items for one unit">{{ Money::format($calc['unit_cost_sen'] ?? 0) }}</span>
                        @else
                            <input wire:model.live.blur="lines.{{ $i }}.unit_cost" @disabled(! $editable) class="{{ $in }} w-28 text-right" aria-label="Unit cost">
                            @error("lines.$i.unit_cost") <span class="text-xs text-bad-ink">{{ $message }}</span> @enderror
                        @endif
                    </td>
                    <td class="whitespace-nowrap px-2 py-1 text-right">{{ Money::format($calc['line_cost_sen'] ?? 0) }}</td>
                    <td class="px-2 py-1">
                        <input wire:model.live.blur="lines.{{ $i }}.margin" @disabled(! $editable) class="{{ $in }} w-16" aria-label="Margin %">
                        @error("lines.$i.margin") <span class="text-xs text-bad-ink">{{ $message }}</span> @enderror
                    </td>
                    <td class="whitespace-nowrap px-2 py-1 text-right">{{ Money::format($calc['price_per_unit_sen'] ?? 0) }}</td>
                    <td class="whitespace-nowrap px-2 py-1 text-right">{{ Money::format($calc['selling_sen'] ?? 0) }}</td>
                    <td class="px-2 py-1">
                        <input wire:model.live.blur="lines.{{ $i }}.vendor" list="costing-vendors" @disabled(! $editable) class="{{ $in }} w-32" aria-label="Vendor">
                    </td>
                    <td class="px-2 py-1">
                        @if (! $editable && ($line['quote_url'] ?? '') !== '')
                            <a href="{{ $line['quote_url'] }}" target="_blank" rel="noopener noreferrer" class="text-info-ink underline">Open</a>
                        @else
                            <input wire:model.live.blur="lines.{{ $i }}.quote_url" @disabled(! $editable) placeholder="https://…" class="{{ $in }} w-40" aria-label="Quotation link">
                            @error("lines.$i.quote_url") <span class="text-xs text-bad-ink">{{ $message }}</span> @enderror
                        @endif
                    </td>
                    <td class="whitespace-nowrap px-2 py-1 text-xs">
                        @if ($editable)
                            <button type="button" wire:click="moveLine({{ $i }}, -1)" title="Move up" aria-label="Move up">↑</button>
                            <button type="button" wire:click="moveLine({{ $i }}, 1)" title="Move down" aria-label="Move down">↓</button>
                            <button type="button" wire:click="addSubItem({{ $i }})" class="ml-1 underline">+ Sub-item</button>
                            <button type="button" wire:click="removeLine({{ $i }})" wire:confirm="Remove this line?" class="ml-1 text-bad-ink">Remove</button>
                        @endif
                    </td>
                </tr>
                @foreach ($line['sub_items'] ?? [] as $j => $sub)
                    <tr wire:key="sub-{{ $i }}-{{ $j }}" class="bg-subtle align-top text-xs">
                        <td class="py-1 pl-6 pr-2">
                            <span class="flex items-center gap-1">↳ <input wire:model.live.blur="lines.{{ $i }}.sub_items.{{ $j }}.description" @disabled(! $editable) class="{{ $in }} w-full" aria-label="Sub-item"></span>
                            @error("lines.$i.sub_items.$j.description") <span class="text-bad-ink">{{ $message }}</span> @enderror
                        </td>
                        <td class="px-2 py-1">
                            <input wire:model.live.blur="lines.{{ $i }}.sub_items.{{ $j }}.quantity" @disabled(! $editable) class="{{ $in }} w-16" aria-label="Sub-item quantity">
                            @error("lines.$i.sub_items.$j.quantity") <span class="text-bad-ink">{{ $message }}</span> @enderror
                        </td>
                        <td class="px-2 py-1"><input wire:model.live.blur="lines.{{ $i }}.sub_items.{{ $j }}.unit" @disabled(! $editable) class="{{ $in }} w-16" aria-label="Sub-item unit"></td>
                        <td colspan="2"></td>
                        <td class="whitespace-nowrap px-2 py-1 text-right">
                            <input wire:model.live.blur="lines.{{ $i }}.sub_items.{{ $j }}.unit_cost" @disabled(! $editable) class="{{ $in }} w-28 text-right" aria-label="Sub-item unit cost">
                            @error("lines.$i.sub_items.$j.unit_cost") <span class="text-bad-ink">{{ $message }}</span> @enderror
                        </td>
                        <td colspan="4"></td>
                        <td class="px-2 py-1"><input wire:model.live.blur="lines.{{ $i }}.sub_items.{{ $j }}.vendor" list="costing-vendors" @disabled(! $editable) class="{{ $in }} w-32" aria-label="Sub-item vendor"></td>
                        <td class="px-2 py-1">
                            @if (! $editable && ($sub['quote_url'] ?? '') !== '')
                                <a href="{{ $sub['quote_url'] }}" target="_blank" rel="noopener noreferrer" class="text-info-ink underline">Open</a>
                            @else
                                <input wire:model.live.blur="lines.{{ $i }}.sub_items.{{ $j }}.quote_url" @disabled(! $editable) placeholder="https://…" class="{{ $in }} w-40" aria-label="Sub-item quotation link">
                                @error("lines.$i.sub_items.$j.quote_url") <span class="text-bad-ink">{{ $message }}</span> @enderror
                            @endif
                        </td>
                        <td class="px-2 py-1">
                            @if ($editable)
                                <button type="button" wire:click="removeSubItem({{ $i }}, {{ $j }})" class="text-bad-ink">Remove</button>
                            @endif
                        </td>
                    </tr>
                @endforeach
            @empty
                <tr><td colspan="13" class="px-3 py-8 text-center text-muted">No cost lines yet.</td></tr>
            @endforelse
            </tbody>
        </table>
        <datalist id="costing-vendors">
            @foreach ($vendors as $v) <option value="{{ $v }}"></option> @endforeach
        </datalist>
    </div>

    @if ($editable)
        <div class="flex flex-wrap items-center gap-2 text-sm">
            <button type="button" wire:click="addLine" class="rounded-lg border border-line px-3 py-1.5 hover:bg-hover">+ Add line</button>
            <button type="button" wire:click="$toggle('showImport')" class="rounded-lg border border-line px-3 py-1.5 hover:bg-hover">Bulk import</button>
            <span class="ml-auto">
                @if ($unsaved) <span class="text-warn-ink">Unsaved changes</span> @endif
            </span>
            <button type="button" wire:click="save" class="rounded-lg bg-chip px-4 py-1.5 font-medium text-chip-ink hover:bg-chip-hover">Save costing</button>
        </div>
        @if ($errors->any())
            <p class="text-sm text-bad-ink">Some fields need fixing — see the messages in red above.</p>
        @endif
        @if ($showImport || $importErrors)
            <div class="space-y-2 rounded-xl border border-line bg-surface p-3 text-sm">
                <p class="text-muted">Copy rows from Excel and paste them here. Columns: description, quantity, unit, unit cost.</p>
                <textarea wire:model="importText" rows="4" class="w-full rounded border border-line bg-surface p-2 font-mono text-xs" aria-label="Rows to import"></textarea>
                <button type="button" wire:click="import" class="rounded-lg border border-line px-3 py-1 hover:bg-hover">Add these lines</button>
                @foreach ($importErrors as $e) <p class="text-xs text-bad-ink">{{ $e }}</p> @endforeach
            </div>
        @endif
    @endif

    <div class="grid gap-4 md:grid-cols-2">
        <div class="{{ $card }} text-sm">
            <h3 class="mb-1 font-medium">Target-cost guide</h3>
            <p class="mb-2 text-xs text-muted">At this bid price, keep the total cost under:</p>
            <table class="w-full"><tbody>
                @foreach ($summary['guide'] as $g)
                    <tr @class(['border-t border-line', 'font-medium' => $g['margin_bp'] === App\Costing\CostingCalculator::COMPANY_TARGET_MARGIN_BP])>
                        <td class="py-1">{{ Percent::format($g['margin_bp'], 0) }} margin</td>
                        <td class="py-1 text-right">{{ Money::format($g['max_cost_sen']) }}</td>
                    </tr>
                @endforeach
            </tbody></table>
        </div>
        <div class="{{ $card }} text-sm">
            <h3 class="mb-2 font-medium">Cost by year</h3>
            <table class="w-full"><tbody>
                @forelse ($summary['cost_by_year'] as $year => $sen)
                    <tr class="border-t border-line"><td class="py-1">Year {{ $year }}</td><td class="py-1 text-right">{{ Money::format($sen) }}</td></tr>
                @empty
                    <tr><td class="py-1 text-muted">—</td></tr>
                @endforelse
            </tbody></table>
        </div>
    </div>
</section>
