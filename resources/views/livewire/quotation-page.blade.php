@php
    use App\Support\{Money, Percent};
    $in = 'w-full rounded-[9px] border border-line-2 bg-surface px-2.5 py-1.5 text-[13px] disabled:border-transparent disabled:bg-transparent';
    $lbl = 'flex flex-col gap-1 text-[12px] font-semibold text-muted';
    $status = $q->status->value;
    $plain = fn (int $sen) => ltrim(Money::format($sen), 'RM ');
    $th = 'px-3 py-3 text-left text-[10.5px] font-bold uppercase tracking-[0.5px] text-muted';
@endphp
<div class="space-y-4">
    <a href="{{ route('quotations.index') }}" class="text-[13px] font-semibold text-muted hover:text-ink">← Back to quotations</a>

    @if ($problem)
        <div class="flex items-center justify-between gap-3 rounded-xl bg-warn-bg p-3 text-sm text-warn-ink" role="alert">
            <span>{{ $problem }}</span>
            <a href="{{ route('quotations.show', $q) }}" class="shrink-0 font-semibold underline">Reload</a>
        </div>
    @endif

    <header class="space-y-3 rounded-[20px] border border-line bg-surface p-5">
        <div class="flex flex-wrap items-center gap-2">
            <span class="font-extrabold tracking-tight">{{ $q->number }}</span>
            <x-status-pill :status="$q->displayStatus()" />
            @if ($q->revisionOf)
                <a href="{{ route('quotations.show', $q->revisionOf) }}" class="text-xs font-semibold text-muted underline">Revision of {{ $q->revisionOf->number }}</a>
            @endif
            {{-- Shown for two seconds after any field saves itself --}}
            <span x-data="{ s: false }" x-on:saved.window="s = true; clearTimeout(window.__savedTimer); window.__savedTimer = setTimeout(() => s = false, 2000)"
                  x-show="s" x-cloak class="text-[12px] font-semibold text-good-ink">Saved ✓</span>
            <div class="ml-auto flex flex-wrap gap-2">
                @if ($canUpdate && $status === 'draft')
                    <button type="button" wire:click="markSent" wire:confirm="Mark as Sent? The quotation will be locked." class="btn btn-dark">Mark as Sent</button>
                @endif
                @if ($canUpdate && $status === 'sent')
                    <button type="button" wire:click="markAccepted" class="btn btn-primary">Mark Accepted</button>
                    <button type="button" wire:click="markRejected" wire:confirm="Mark this quotation Rejected?" class="btn btn-outline">Mark Rejected</button>
                    <button type="button" wire:click="revise" class="btn btn-outline">Revise</button>
                @endif
                @if ($status === 'accepted')
                    @if ($q->project)
                        <a href="{{ route('quotations.pd', $q) }}" class="btn btn-dark">Open project</a>
                    @elseif ($canUpdate)
                        <button type="button" wire:click="createProject" wire:confirm="Create a project (PD) from this quotation?" class="btn btn-primary">Create project</button>
                    @endif
                @endif
                @if ($canBackToDraft)
                    <button type="button" wire:click="backToDraft" wire:confirm="Move this quotation back to Draft?" class="btn btn-outline">Back to Draft</button>
                @endif
                <button type="button" wire:click="duplicate" class="btn btn-outline">Duplicate</button>
                <a href="{{ route('quotations.pdf', $q) }}" class="btn btn-outline">Download PDF</a>
            </div>
        </div>
        @if ($q->customer_name || $q->subject)
            <h1 class="text-xl font-extrabold leading-snug tracking-tight">{{ $q->customer_name }}@if ($q->customer_name && $q->subject) — @endif{{ $q->subject }}</h1>
        @endif
    </header>

    <x-tabs>
        @foreach (['details' => 'Details', 'items' => 'Items', 'terms' => 'Terms & Conditions', 'preview' => 'Preview', 'history' => 'History'] as $key => $label)
            <x-tab :active="$tab === $key" wire:click="$set('tab', '{{ $key }}')">{{ $label }}</x-tab>
        @endforeach
    </x-tabs>

    @if ($tab === 'details')
        <div class="grid gap-4 lg:grid-cols-2">
            <x-card title="Customer" icon="user">
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="{{ $lbl }}">Date
                        <input type="date" wire:model.live.blur="form.quote_date" @disabled(! $editable) class="{{ $in }} font-normal">
                        @error('form.quote_date') <span class="text-xs text-bad-ink">{{ $message }}</span> @enderror
                    </label>
                    <label class="{{ $lbl }}">Validity (days)
                        <input wire:model.live.blur="form.validity_days" @disabled(! $editable) class="{{ $in }} font-normal">
                        <span class="text-xs font-normal">Valid until {{ $q->validUntil()->format('d M Y') }}</span>
                        @error('form.validity_days') <span class="text-xs text-bad-ink">{{ $message }}</span> @enderror
                    </label>
                    @foreach (['customer_name' => 'Customer', 'attention' => 'Attention', 'attention_phone' => 'Attention mobile no.', 'attention_email' => 'Attention email'] as $k => $label)
                        <label @class([$lbl, 'sm:col-span-2' => in_array($k, ['customer_name', 'attention'], true)])>{{ $label }}
                            <input wire:model.live.blur="form.{{ $k }}" @disabled(! $editable) class="{{ $in }} font-normal">
                            @error("form.$k") <span class="text-xs text-bad-ink">{{ $message }}</span> @enderror
                        </label>
                    @endforeach
                    <label class="{{ $lbl }} sm:col-span-2">Customer address
                        <textarea wire:model.live.blur="form.customer_address" rows="3" @disabled(! $editable) class="{{ $in }} font-normal"></textarea>
                        @error('form.customer_address') <span class="text-xs text-bad-ink">{{ $message }}</span> @enderror
                    </label>
                    <label class="{{ $lbl }} sm:col-span-2">Subject
                        <input wire:model.live.blur="form.subject" @disabled(! $editable) class="{{ $in }} font-normal">
                        @error('form.subject') <span class="text-xs text-bad-ink">{{ $message }}</span> @enderror
                    </label>
                </div>
            </x-card>

            <x-card title="Prepared by" icon="staff">
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="{{ $lbl }} sm:col-span-2">Prepared by
                        <select wire:model.live="form.prepared_by" @disabled(! $editable) class="{{ $in }} font-normal">
                            @foreach ($people as $p) <option value="{{ $p->id }}">{{ $p->name }}</option> @endforeach
                        </select>
                        @error('form.prepared_by') <span class="text-xs text-bad-ink">{{ $message }}</span> @enderror
                    </label>
                    @foreach (['preparer_position' => 'Position', 'preparer_phone' => 'Phone', 'preparer_email' => 'Email'] as $k => $label)
                        <label class="{{ $lbl }}">{{ $label }}
                            <input wire:model.live.blur="form.{{ $k }}" @disabled(! $editable) class="{{ $in }} font-normal">
                            @error("form.$k") <span class="text-xs text-bad-ink">{{ $message }}</span> @enderror
                        </label>
                    @endforeach
                    <label class="{{ $lbl }}">SST %
                        <input wire:model.live.blur="form.sst" @disabled(! $editable) class="{{ $in }} w-24 font-normal">
                        @error('form.sst') <span class="text-xs text-bad-ink">{{ $message }}</span> @enderror
                    </label>
                    <div class="flex flex-wrap items-center gap-4 text-[13px] sm:col-span-2">
                        <label class="flex items-center gap-2"><input type="checkbox" wire:model.live="form.show_signature" @disabled(! $editable) class="h-4 w-4 accent-[var(--accent-solid)]"> Typed signature</label>
                        <label class="flex items-center gap-2"><input type="checkbox" wire:model.live="form.show_stamp" @disabled(! $editable) class="h-4 w-4 accent-[var(--accent-solid)]"> Company stamp</label>
                        @if (empty($q->letterhead['stamp_path'])) <span class="text-xs text-muted">No stamp on this quotation's letterhead.</span> @endif
                    </div>
                </div>
            </x-card>
        </div>
    @elseif ($tab === 'items')
        <x-card title="Items" icon="tenders">
            <label class="mb-3 flex items-center gap-2 text-[13px] font-semibold text-muted">Default margin % for new items
                <input wire:model.live.blur="form.default_margin" @disabled(! $editable) class="{{ $in }} !w-20 font-normal" aria-label="Default margin %">
            </label>
            @error('form.default_margin') <p class="-mt-2 mb-2 text-xs text-bad-ink">{{ $message }}</p> @enderror
            <div class="relative overflow-x-auto rounded-xl border border-line">
                <table class="w-full min-w-[1040px] text-[13px]">
                    <thead class="bg-subtle">
                        <tr>
                            <th class="{{ $th }}">No</th><th class="{{ $th }}">Description</th><th class="{{ $th }}">Qty</th><th class="{{ $th }}">Unit</th>
                            <th class="{{ $th }}" title="How many times this repeats, e.g. 12 for monthly over a year">Freq.</th>
                            <th class="{{ $th }} text-right">Unit price (RM)</th><th class="{{ $th }} text-right">Amount (RM)</th>
                            <th class="{{ $th }}" title="Tick to charge SST on this item">SST</th><th class="{{ $th }}"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse ($q->items as $n => $item)
                        @php $k = 'i'.$item->id; $calc = \App\Costing\CostingCalculator::line($item->costingLine()); $subs = $items[$k]['sub_items'] ?? []; @endphp
                        <tr wire:key="item-{{ $item->id }}" class="border-t border-line align-top">
                            <td class="px-3 py-2">{{ $n + 1 }}</td>
                            <td class="px-3 py-1.5">
                                <input wire:model.live.blur="items.{{ $k }}.title" @disabled(! $editable) class="{{ $in }} min-w-72 font-semibold" aria-label="Title">
                                @error("items.$k.title") <span class="text-xs text-bad-ink">{{ $message }}</span> @enderror
                                <textarea wire:model.live.blur="items.{{ $k }}.details" rows="2" @disabled(! $editable) placeholder="Details (optional) — e.g. Power Supply: 100–240 V" class="{{ $in }} mt-1 text-xs" aria-label="Details"></textarea>
                            </td>
                            <td class="px-3 py-1.5">
                                <input wire:model.live.blur="items.{{ $k }}.quantity" @disabled(! $editable) class="{{ $in }} w-20" aria-label="Quantity">
                                @error("items.$k.quantity") <span class="text-xs text-bad-ink">{{ $message }}</span> @enderror
                            </td>
                            <td class="px-3 py-1.5">
                                <input wire:model.live.blur="items.{{ $k }}.unit" @disabled(! $editable) class="{{ $in }} w-20" aria-label="Unit">
                                @error("items.$k.unit") <span class="text-xs text-bad-ink">{{ $message }}</span> @enderror
                            </td>
                            <td class="px-3 py-1.5">
                                <input wire:model.live.blur="items.{{ $k }}.frequency" @disabled(! $editable) class="{{ $in }} w-16" aria-label="Frequency">
                                @error("items.$k.frequency") <span class="text-xs text-bad-ink">{{ $message }}</span> @enderror
                            </td>
                            <td class="px-3 py-1.5 text-right">
                                <input wire:model.live.blur="items.{{ $k }}.unit_price" @disabled(! $editable) placeholder="{{ \App\Support\Money::toInput($item->unit_price_sen) }}"
                                       class="{{ $in }} w-32 text-right placeholder:text-ink" aria-label="Unit price" title="Type a price to work the margin out from it; clear it to use the margin">
                                @error("items.$k.unit_price") <span class="text-xs text-bad-ink">{{ $message }}</span> @enderror
                            </td>
                            <td class="whitespace-nowrap px-3 py-2 text-right">{{ $plain($totals['lines'][$n]) }}</td>
                            <td class="px-3 py-2">
                                <input type="checkbox" wire:model.live="items.{{ $k }}.sst" @disabled(! $editable) aria-label="SST on this item" class="h-4 w-4 accent-[var(--accent-solid)]">
                            </td>
                            <td class="whitespace-nowrap px-3 py-2 text-xs">
                                @if ($editable)
                                    <button type="button" wire:click="moveItem({{ $item->id }}, -1)" aria-label="Move up">↑</button>
                                    <button type="button" wire:click="moveItem({{ $item->id }}, 1)" aria-label="Move down">↓</button>
                                    <button type="button" wire:click="addSubItem({{ $item->id }})" class="ml-1 underline">+ Sub-item</button>
                                    <button type="button" wire:click="removeItem({{ $item->id }})" wire:confirm="Remove this item?" class="ml-1 font-semibold text-bad-ink">Remove</button>
                                @endif
                            </td>
                        </tr>
                        {{-- Internal costing for this item: never printed on the quotation --}}
                        <tr wire:key="item-cost-{{ $item->id }}" class="bg-subtle align-top text-xs">
                            <td></td>
                            <td colspan="8" class="px-3 py-2">
                                <p class="mb-1.5 text-[10.5px] font-bold uppercase tracking-[0.5px] text-muted">Costing (not shown to the customer)</p>
                                <div class="flex flex-wrap items-start gap-3">
                                    <label class="flex flex-col gap-0.5 text-muted">Unit cost
                                        @if ($subs !== [])
                                            <span class="py-1.5 font-semibold text-ink" title="Total of the sub-items for one unit">{{ Money::format($calc['unit_cost_sen']) }}</span>
                                        @else
                                            <input wire:model.live.blur="items.{{ $k }}.unit_cost" @disabled(! $editable) class="{{ $in }} !w-28 text-right" aria-label="Unit cost">
                                        @endif
                                        @error("items.$k.unit_cost") <span class="text-bad-ink">{{ $message }}</span> @enderror
                                    </label>
                                    <label class="flex flex-col gap-0.5 text-muted">Margin %
                                        <input wire:model.live.blur="items.{{ $k }}.margin" @disabled(! $editable) class="{{ $in }} !w-20" aria-label="Margin %">
                                        @error("items.$k.margin") <span class="text-bad-ink">{{ $message }}</span> @enderror
                                        @if ($calc['is_price_override'])
                                            <span @class(['whitespace-nowrap text-[11px]', 'text-bad-ink' => $calc['effective_margin_bp'] < 0])>{{ Percent::format($calc['effective_margin_bp']) }} from price{{ $calc['effective_margin_bp'] < 0 ? ' · below cost' : '' }}</span>
                                        @endif
                                    </label>
                                    <label class="flex flex-col gap-0.5 text-muted">Vendor
                                        <input wire:model.live.blur="items.{{ $k }}.vendor" @disabled(! $editable) class="{{ $in }} !w-40" aria-label="Vendor">
                                    </label>
                                    <label class="flex flex-col gap-0.5 text-muted">Quotation link
                                        @if (! $editable && ($items[$k]['quote_url'] ?? '') !== '')
                                            <a href="{{ $items[$k]['quote_url'] }}" target="_blank" rel="noopener noreferrer" class="py-1.5 text-info-ink underline">Open</a>
                                        @else
                                            <input wire:model.live.blur="items.{{ $k }}.quote_url" @disabled(! $editable) placeholder="https://…" class="{{ $in }} !w-52" aria-label="Quotation link">
                                        @endif
                                        @error("items.$k.quote_url") <span class="text-bad-ink">{{ $message }}</span> @enderror
                                    </label>
                                    <span class="flex flex-col gap-0.5 text-muted">Line cost <span class="py-1.5 font-semibold text-ink">{{ Money::format($calc['line_cost_sen']) }}</span></span>
                                </div>
                                @foreach ($subs as $j => $sub)
                                    <div wire:key="item-sub-{{ $item->id }}-{{ $j }}" class="mt-1.5 flex flex-wrap items-center gap-2 pl-3">
                                        <span>↳</span>
                                        <input wire:model.live.blur="items.{{ $k }}.sub_items.{{ $j }}.description" @disabled(! $editable) placeholder="Sub-item" class="{{ $in }} !w-56" aria-label="Sub-item">
                                        <input wire:model.live.blur="items.{{ $k }}.sub_items.{{ $j }}.quantity" @disabled(! $editable) class="{{ $in }} !w-14" aria-label="Sub-item quantity">
                                        <input wire:model.live.blur="items.{{ $k }}.sub_items.{{ $j }}.unit" @disabled(! $editable) class="{{ $in }} !w-16" aria-label="Sub-item unit">
                                        <input wire:model.live.blur="items.{{ $k }}.sub_items.{{ $j }}.unit_cost" @disabled(! $editable) class="{{ $in }} !w-28 text-right" aria-label="Sub-item unit cost">
                                        <input wire:model.live.blur="items.{{ $k }}.sub_items.{{ $j }}.vendor" @disabled(! $editable) placeholder="Vendor" class="{{ $in }} !w-32" aria-label="Sub-item vendor">
                                        <input wire:model.live.blur="items.{{ $k }}.sub_items.{{ $j }}.quote_url" @disabled(! $editable) placeholder="https://…" class="{{ $in }} !w-40" aria-label="Sub-item quotation link">
                                        @if ($editable)
                                            <button type="button" wire:click="removeSubItem({{ $item->id }}, {{ $j }})" class="text-bad-ink">Remove</button>
                                        @endif
                                        @foreach (['description', 'quantity', 'unit_cost', 'quote_url'] as $f)
                                            @error("items.$k.sub_items.$j.$f") <span class="w-full text-bad-ink">{{ $message }}</span> @enderror
                                        @endforeach
                                    </div>
                                @endforeach
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-3 py-8 text-center text-muted">No items yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4 flex flex-wrap items-start gap-4">
                @if ($editable)
                    <button type="button" wire:click="addItem" class="btn btn-outline">+ Add item</button>
                @endif
                <div class="ml-auto w-full max-w-xs space-y-1 text-[13px]">
                    <div class="flex justify-between"><span class="text-muted">Subtotal</span><span>{{ Money::format($totals['subtotal_sen']) }}</span></div>
                    <div class="flex justify-between"><span class="text-muted">SST ({{ Percent::format($q->sst_bp) }}) on items marked *</span><span>{{ Money::format($totals['sst_sen']) }}</span></div>
                    <div class="flex justify-between border-t border-line pt-1 text-[15px] font-extrabold"><span>Total</span><span>{{ Money::format($totals['total_sen']) }}</span></div>
                </div>
            </div>
            <p class="mt-2 text-right text-xs italic text-muted">{{ $totals['words'] }}</p>
            <div class="ml-auto mt-4 w-full max-w-xs space-y-1 rounded-[14px] border border-line bg-subtle px-4 py-3 text-[13px]" data-profit>
                <p class="text-[10.5px] font-bold uppercase tracking-[0.5px] text-muted">Profit (internal)</p>
                <div class="flex justify-between"><span class="text-muted">Total cost</span><span>{{ Money::format($costing['total_cost_sen']) }}</span></div>
                <div class="flex justify-between"><span class="text-muted">Quotation subtotal</span><span>{{ Money::format($costing['suggested_bid_sen']) }}</span></div>
                <div class="flex justify-between font-bold"><span>Margin</span><span @class(['text-bad-ink' => $costing['margin_sen'] < 0])>{{ Money::format($costing['margin_sen']) }} · {{ Percent::format($costing['margin_bp']) }}</span></div>
                @if ($costing['below_target'])
                    <p class="text-xs font-semibold text-bad-ink">Below the {{ $target }} company target</p>
                @endif
            </div>
        </x-card>
    @elseif ($tab === 'terms')
        <x-card title="Terms & Conditions" subtitle="One term per line. They are numbered automatically on the quotation." icon="quotation">
            <textarea wire:model.live.blur="form.terms" rows="10" @disabled(! $editable) class="{{ $in }}" aria-label="Terms"></textarea>
            @error('form.terms') <span class="text-xs text-bad-ink">{{ $message }}</span> @enderror
            @if ($editable)
                <button type="button" wire:click="resetTerms" wire:confirm="Replace these terms with the company default?" class="btn btn-outline mt-3">Reset to company default</button>
            @endif
        </x-card>
    @elseif ($tab === 'preview')
        <x-card class="!p-2">
            <iframe src="{{ route('quotations.pdf', [$q, 'inline' => 1]) }}" title="Quotation preview" class="h-[80vh] w-full rounded-xl"></iframe>
        </x-card>
    @else
        <x-card title="History" subtitle="Everything that happened to this quotation, newest first" icon="clock">
            @include('partials.timeline', ['entries' => $activity])
        </x-card>
    @endif
</div>
