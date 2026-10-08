@php
    use App\Support\{Money, Percent};
    $in = 'rounded-[8px] border border-line-2 bg-surface px-1.5 py-1 text-[13px] disabled:border-transparent disabled:bg-transparent';
    $cost = $summary['total_cost_sen'];
    $bid = $summary['bid_price_sen'];
    $costPct = $bid > 0 ? (int) min(100, max(0, round($cost * 100 / $bid))) : 0;
    $card = 'rounded-[14px] border border-line bg-surface px-4 py-3';
    $boxLabel = 'text-[10.5px] font-bold uppercase tracking-[0.5px] text-muted';
    $boxValue = 'text-xl font-extrabold tracking-tight';
    $low = $summary['below_target'];
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

    @if ($editable && $unsaved)
        <div class="flex flex-wrap items-center gap-3 rounded-xl border border-warn-ink/30 bg-warn-bg px-4 py-2.5 text-[13px] text-warn-ink" role="status">
            <span class="font-semibold">Unsaved changes — save your costing before you leave this tab.</span>
            <button type="button" wire:click="save" class="btn btn-dark ml-auto">Save changes</button>
        </div>
    @endif

    {{-- Summary boxes (prototype: Total Cost · Total Sell · Margin · Margin %), plus Under budget --}}
    <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-5">
        <div data-costing-box="cost" class="{{ $card }}">
            <p class="{{ $boxLabel }}">Total cost</p>
            <p class="{{ $boxValue }}">{{ Money::format($cost) }}</p>
        </div>
        <div data-costing-box="sell" class="{{ $card }}">
            <p class="{{ $boxLabel }}">Total sell · {{ $summary['is_override'] ? 'Your price' : 'Suggested' }}</p>
            <p class="{{ $boxValue }}">{{ Money::format($bid) }}</p>
            @if ($summary['is_override'])
                <p class="text-xs text-muted">Suggested {{ Money::format($summary['suggested_bid_sen']) }}
                    @if ($editable) · <button type="button" wire:click="resetOverride" class="font-semibold underline">Reset to suggested</button>@endif
                </p>
            @endif
        </div>
        <div data-costing-box="margin" @class([$card, 'text-bad-ink' => $low])>
            <p class="{{ $boxLabel }}">Margin</p>
            <p class="{{ $boxValue }}">{{ Money::format($summary['margin_sen']) }}</p>
        </div>
        <div data-costing-box="margin-pct" @class(['rounded-[14px] border px-4 py-3', 'border-line bg-surface' => ! $low, 'border-bad-ink/40 bg-bad-bg text-bad-ink' => $low])>
            <p class="{{ $boxLabel }} {{ $low ? '!text-bad-ink' : '' }}">Margin %</p>
            <p class="{{ $boxValue }}">{{ Percent::format($summary['margin_bp']) }}</p>
            @if ($low)
                <p class="text-xs font-semibold">⚠ Below the {{ $target }} target</p>
            @endif
        </div>
        <div class="{{ $card }}">
            <p class="{{ $boxLabel }}">Under budget</p>
            <p class="{{ $boxValue }}">{{ $summary['under_budget_bp'] === null ? '—' : Percent::format($summary['under_budget_bp']) }}</p>
            @if ($tender->estimated_value_sen)
                <p class="text-xs text-muted">Estimated {{ Money::format($tender->estimated_value_sen) }}</p>
            @endif
        </div>
    </div>
    <div class="flex h-2 overflow-hidden rounded-full bg-good-bg" title="Cost {{ $costPct }}% of the bid price">
        <div class="h-full bg-chip" style="width: {{ $costPct }}%"></div>
    </div>

    <div class="flex flex-wrap items-end gap-3 text-[13px]">
        <label class="flex items-center gap-2 font-semibold text-ink-2">Default margin %
            <input wire:model.live.blur="defaultMargin" @disabled(! $editable) class="w-20 rounded-[9px] border border-line-2 bg-surface px-2.5 py-1.5 font-normal"></label>
        @if ($editable)
            <button type="button" wire:click="applyDefaultToAll" wire:confirm="Set every line's margin to the default?" class="btn btn-outline">Apply to all lines</button>
        @endif
        <label class="flex items-center gap-2 font-semibold text-ink-2 sm:ml-auto">Your bid price (optional)
            <input wire:model.live.blur="override" @disabled(! $editable) placeholder="{{ Money::toInput($summary['suggested_bid_sen']) }}" class="w-36 rounded-[9px] border border-line-2 bg-surface px-2.5 py-1.5 font-normal"></label>
        @error('defaultMargin') <p class="w-full text-xs text-bad-ink">{{ $message }}</p> @enderror
        @error('override') <p class="w-full text-xs text-bad-ink">{{ $message }}</p> @enderror
    </div>

    <div class="relative overflow-x-auto rounded-2xl border border-line bg-surface">
        <table class="w-full min-w-[1480px] text-[13px]">
            <thead class="bg-subtle text-left text-[10.5px] font-bold uppercase tracking-[0.5px] text-muted">
                <tr>
                    <th class="px-2 py-2">Item</th><th class="px-2">Qty</th><th class="px-2">Unit</th><th class="px-2">Frequency</th>
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
                    <td class="px-2 py-1">
                        <input wire:model.live.blur="lines.{{ $i }}.frequency" @disabled(! $editable) class="{{ $in }} w-16" aria-label="Frequency" title="How many times this repeats, e.g. 12 for monthly over a year">
                        @error("lines.$i.frequency") <span class="block text-xs text-bad-ink">{{ $message }}</span> @enderror
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
                        @if ($calc['is_price_override'] ?? false)
                            <span @class(['block whitespace-nowrap text-[11px]', 'text-bad-ink' => $calc['effective_margin_bp'] < 0, 'text-muted' => $calc['effective_margin_bp'] >= 0])>{{ Percent::format($calc['effective_margin_bp']) }} from price{{ $calc['effective_margin_bp'] < 0 ? ' · below cost' : '' }}</span>
                        @endif
                    </td>
                    <td class="whitespace-nowrap px-2 py-1 text-right">
                        <input wire:model.live.blur="lines.{{ $i }}.unit_price" @disabled(! $editable) placeholder="{{ Money::toInput($calc['price_per_unit_sen'] ?? 0) }}"
                               class="{{ $in }} w-28 text-right placeholder:text-ink" aria-label="Selling price per unit" title="Type a price to work the margin out from it; clear it to use the margin">
                        @error("lines.$i.unit_price") <span class="block text-xs text-bad-ink">{{ $message }}</span> @enderror
                    </td>
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
                        <td></td>
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
                <tr><td colspan="12" class="px-3 py-8 text-center text-muted">No cost lines yet.</td></tr>
            @endforelse
            </tbody>
        </table>
        <datalist id="costing-vendors">
            @foreach ($vendors as $v) <option value="{{ $v }}"></option> @endforeach
        </datalist>
    </div>

    @if ($editable)
        <div class="flex flex-wrap items-center gap-2 text-[13px]">
            <button type="button" wire:click="addLine" class="btn btn-outline">+ Add line item</button>
            <button type="button" wire:click="openImport" class="btn btn-outline">Bulk Import</button>
            <button type="button" wire:click="save" class="btn btn-dark ml-auto">Save costing</button>
        </div>
        @if ($errors->any())
            <p class="text-[13px] text-bad-ink">Some fields need fixing — see the messages in red above.</p>
        @endif
        @if ($showImport || $importErrors)
            <x-dialog title="Bulk Import Items" subtitle="Paste rows copied from Excel or Sheets, or type one item per line as: Item name, Qty, Unit, Unit cost." close="closeImport">
                <textarea wire:model="importText" rows="8" placeholder="Desktop PC, 20, unit, 2500&#10;Monitor, 20, unit, 450&#10;Installation, 1, lot, 12000"
                          class="w-full rounded-[9px] border border-line-2 bg-surface p-2.5 font-mono text-xs" aria-label="Rows to import"></textarea>
                @foreach ($importErrors as $e) <p class="text-xs text-bad-ink">{{ $e }}</p> @endforeach
                <x-slot:actions>
                    <button type="button" wire:click="import" class="btn btn-primary">Import items</button>
                </x-slot:actions>
            </x-dialog>
        @endif
    @endif

    <div class="grid gap-4 md:grid-cols-2">
        <div class="{{ $card }} text-sm">
            <h3 class="mb-1 font-bold">Target-cost guide</h3>
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
    </div>
</section>
