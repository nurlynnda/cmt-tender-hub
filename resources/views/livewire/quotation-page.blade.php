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
            <div class="relative overflow-x-auto rounded-xl border border-line">
                <table class="w-full min-w-[900px] text-[13px]">
                    <thead class="bg-subtle">
                        <tr>
                            <th class="{{ $th }}">No</th><th class="{{ $th }}">Description</th><th class="{{ $th }}">Qty</th><th class="{{ $th }}">Unit</th>
                            <th class="{{ $th }} text-right">Unit price (RM)</th><th class="{{ $th }} text-right">Amount (RM)</th><th class="{{ $th }}"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse ($q->items as $n => $item)
                        @php $k = 'i'.$item->id; @endphp
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
                            <td class="px-3 py-1.5 text-right">
                                <input wire:model.live.blur="items.{{ $k }}.unit_price" @disabled(! $editable) class="{{ $in }} w-32 text-right" aria-label="Unit price">
                                @error("items.$k.unit_price") <span class="text-xs text-bad-ink">{{ $message }}</span> @enderror
                            </td>
                            <td class="whitespace-nowrap px-3 py-2 text-right">{{ $plain($totals['lines'][$n]) }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-xs">
                                @if ($editable)
                                    <button type="button" wire:click="moveItem({{ $item->id }}, -1)" aria-label="Move up">↑</button>
                                    <button type="button" wire:click="moveItem({{ $item->id }}, 1)" aria-label="Move down">↓</button>
                                    <button type="button" wire:click="removeItem({{ $item->id }})" wire:confirm="Remove this item?" class="ml-1 font-semibold text-bad-ink">Remove</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-3 py-8 text-center text-muted">No items yet.</td></tr>
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
                    <div class="flex justify-between"><span class="text-muted">SST ({{ Percent::format($q->sst_bp) }})</span><span>{{ Money::format($totals['sst_sen']) }}</span></div>
                    <div class="flex justify-between border-t border-line pt-1 text-[15px] font-extrabold"><span>Total</span><span>{{ Money::format($totals['total_sen']) }}</span></div>
                </div>
            </div>
            <p class="mt-2 text-right text-xs italic text-muted">{{ $totals['words'] }}</p>
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
