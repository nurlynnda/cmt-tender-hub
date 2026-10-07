@php
    use App\Support\{Money, Percent};
    $in = 'w-full rounded border border-line bg-surface px-2 py-1.5 text-sm disabled:border-transparent disabled:bg-transparent';
    $btn = 'rounded-lg px-3 py-1.5 text-sm font-medium';
    $tone = [
        'draft' => 'bg-subtle text-muted', 'sent' => 'bg-info-bg text-info-ink', 'expired' => 'bg-warn-bg text-warn-ink',
        'accepted' => 'bg-good-bg text-good-ink', 'rejected' => 'bg-bad-bg text-bad-ink', 'revised' => 'bg-subtle text-muted',
    ];
    $status = $q->status->value;
    $plain = fn (int $sen) => ltrim(Money::format($sen), 'RM ');
@endphp
<div class="space-y-4">
    <a href="{{ route('quotations.index') }}" class="text-sm text-muted hover:text-ink">← Back to quotations</a>

    @if ($problem)
        <div class="flex items-center justify-between gap-3 rounded-lg bg-warn-bg p-3 text-sm text-warn-ink" role="alert">
            <span>{{ $problem }}</span>
            <a href="{{ route('quotations.show', $q) }}" class="shrink-0 font-medium underline">Reload</a>
        </div>
    @endif

    <header class="flex flex-wrap items-center gap-2 rounded-xl border border-line bg-surface p-4">
        <span class="font-mono font-semibold">{{ $q->number }}</span>
        <span class="rounded-full px-2 py-0.5 text-xs {{ $tone[$q->displayStatus()] }}">{{ $q->displayLabel() }}</span>
        @if ($q->revisionOf)
            <a href="{{ route('quotations.show', $q->revisionOf) }}" class="text-xs text-muted underline">Revision of {{ $q->revisionOf->number }}</a>
        @endif
        <span class="text-sm text-muted">{{ $q->customer_name }}</span>
        <div class="ml-auto flex flex-wrap gap-2">
            @if ($canUpdate && $status === 'draft')
                <button type="button" wire:click="markSent" wire:confirm="Mark as Sent? The quotation will be locked." class="{{ $btn }} bg-chip text-chip-ink hover:bg-chip-hover">Mark as Sent</button>
            @endif
            @if ($canUpdate && $status === 'sent')
                <button type="button" wire:click="markAccepted" class="{{ $btn }} bg-accent text-accent-ink">Mark Accepted</button>
                <button type="button" wire:click="markRejected" wire:confirm="Mark this quotation Rejected?" class="{{ $btn }} border border-line hover:bg-hover">Mark Rejected</button>
                <button type="button" wire:click="revise" class="{{ $btn }} border border-line hover:bg-hover">Revise</button>
            @endif
            @if ($status === 'accepted')
                @if ($q->project)
                    <a href="{{ route('quotations.pd', $q) }}" class="{{ $btn }} bg-chip text-chip-ink hover:bg-chip-hover">Open project</a>
                @elseif ($canUpdate)
                    <button type="button" wire:click="createProject" wire:confirm="Create a project (PD) from this quotation?" class="{{ $btn }} bg-chip text-chip-ink hover:bg-chip-hover">Create project</button>
                @endif
            @endif
            @if ($canBackToDraft)
                <button type="button" wire:click="backToDraft" wire:confirm="Move this quotation back to Draft?" class="{{ $btn }} border border-line hover:bg-hover">Back to Draft</button>
            @endif
            <button type="button" wire:click="duplicate" class="{{ $btn }} border border-line hover:bg-hover">Duplicate</button>
            <a href="{{ route('quotations.pdf', $q) }}" class="{{ $btn }} border border-line hover:bg-hover">Download PDF</a>
        </div>
    </header>

    <nav class="flex gap-1 overflow-x-auto border-b border-line text-sm">
        @foreach (['details' => 'Details', 'items' => 'Items', 'terms' => 'Terms & Conditions', 'preview' => 'Preview', 'history' => 'History'] as $key => $label)
            <button type="button" wire:click="$set('tab', '{{ $key }}')" @class([
                'whitespace-nowrap px-3 py-2 -mb-px border-b-2',
                'border-ink font-medium' => $tab === $key,
                'border-transparent text-muted hover:text-ink' => $tab !== $key,
            ])>{{ $label }}</button>
        @endforeach
    </nav>

    @if ($tab === 'details')
        <div class="grid gap-3 rounded-xl border border-line bg-surface p-4 text-sm md:grid-cols-2">
            <label class="flex flex-col gap-1">Date
                <input type="date" wire:model.live.blur="form.quote_date" @disabled(! $editable) class="{{ $in }}">
                @error('form.quote_date') <span class="text-xs text-bad-ink">{{ $message }}</span> @enderror
            </label>
            <label class="flex flex-col gap-1">Validity (days)
                <input wire:model.live.blur="form.validity_days" @disabled(! $editable) class="{{ $in }}">
                <span class="text-xs text-muted">Valid until {{ $q->validUntil()->format('d M Y') }}</span>
                @error('form.validity_days') <span class="text-xs text-bad-ink">{{ $message }}</span> @enderror
            </label>
            @foreach (['customer_name' => 'Customer', 'attention' => 'Attention', 'attention_phone' => 'Attention mobile no.', 'attention_email' => 'Attention email'] as $k => $label)
                <label @class(['flex flex-col gap-1', 'md:col-span-2' => in_array($k, ['customer_name', 'attention'], true)])>{{ $label }}
                    <input wire:model.live.blur="form.{{ $k }}" @disabled(! $editable) class="{{ $in }}">
                    @error("form.$k") <span class="text-xs text-bad-ink">{{ $message }}</span> @enderror
                </label>
            @endforeach
            <label class="flex flex-col gap-1 md:col-span-2">Customer address
                <textarea wire:model.live.blur="form.customer_address" rows="3" @disabled(! $editable) class="{{ $in }}"></textarea>
                @error('form.customer_address') <span class="text-xs text-bad-ink">{{ $message }}</span> @enderror
            </label>
            <label class="flex flex-col gap-1 md:col-span-2">Subject
                <input wire:model.live.blur="form.subject" @disabled(! $editable) class="{{ $in }}">
                @error('form.subject') <span class="text-xs text-bad-ink">{{ $message }}</span> @enderror
            </label>
            <label class="flex flex-col gap-1">Prepared by
                <select wire:model.live="form.prepared_by" @disabled(! $editable) class="{{ $in }}">
                    @foreach ($people as $p) <option value="{{ $p->id }}">{{ $p->name }}</option> @endforeach
                </select>
                @error('form.prepared_by') <span class="text-xs text-bad-ink">{{ $message }}</span> @enderror
            </label>
            @foreach (['preparer_position' => 'Position', 'preparer_phone' => 'Phone', 'preparer_email' => 'Email'] as $k => $label)
                <label class="flex flex-col gap-1">{{ $label }}
                    <input wire:model.live.blur="form.{{ $k }}" @disabled(! $editable) class="{{ $in }}">
                    @error("form.$k") <span class="text-xs text-bad-ink">{{ $message }}</span> @enderror
                </label>
            @endforeach
            <label class="flex flex-col gap-1">SST %
                <input wire:model.live.blur="form.sst" @disabled(! $editable) class="{{ $in }} w-24">
                @error('form.sst') <span class="text-xs text-bad-ink">{{ $message }}</span> @enderror
            </label>
            <div class="flex flex-wrap items-center gap-4">
                <label class="flex items-center gap-2"><input type="checkbox" wire:model.live="form.show_signature" @disabled(! $editable)> Typed signature</label>
                <label class="flex items-center gap-2"><input type="checkbox" wire:model.live="form.show_stamp" @disabled(! $editable)> Company stamp</label>
                @if (empty($q->letterhead['stamp_path'])) <span class="text-xs text-muted">No stamp on this quotation's letterhead.</span> @endif
            </div>
        </div>
    @elseif ($tab === 'items')
        <div class="space-y-3 rounded-xl border border-line bg-surface p-4">
            <div class="relative overflow-x-auto">
                <table class="w-full min-w-[900px] text-sm">
                    <thead class="bg-subtle text-left text-xs uppercase text-muted">
                        <tr>
                            <th class="px-2 py-2">No</th><th class="px-2">Description</th><th class="px-2">Qty</th><th class="px-2">Unit</th>
                            <th class="px-2 text-right">Unit price (RM)</th><th class="px-2 text-right">Amount (RM)</th><th class="px-2"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse ($q->items as $n => $item)
                        @php $k = 'i'.$item->id; @endphp
                        <tr wire:key="item-{{ $item->id }}" class="border-t border-line align-top">
                            <td class="px-2 py-2">{{ $n + 1 }}</td>
                            <td class="px-2 py-1">
                                <input wire:model.live.blur="items.{{ $k }}.title" @disabled(! $editable) class="{{ $in }} min-w-72 font-medium" aria-label="Title">
                                @error("items.$k.title") <span class="text-xs text-bad-ink">{{ $message }}</span> @enderror
                                <textarea wire:model.live.blur="items.{{ $k }}.details" rows="2" @disabled(! $editable) placeholder="Details (optional) — e.g. Power Supply: 100–240 V" class="{{ $in }} mt-1 text-xs" aria-label="Details"></textarea>
                            </td>
                            <td class="px-2 py-1">
                                <input wire:model.live.blur="items.{{ $k }}.quantity" @disabled(! $editable) class="{{ $in }} w-20" aria-label="Quantity">
                                @error("items.$k.quantity") <span class="text-xs text-bad-ink">{{ $message }}</span> @enderror
                            </td>
                            <td class="px-2 py-1">
                                <input wire:model.live.blur="items.{{ $k }}.unit" @disabled(! $editable) class="{{ $in }} w-20" aria-label="Unit">
                                @error("items.$k.unit") <span class="text-xs text-bad-ink">{{ $message }}</span> @enderror
                            </td>
                            <td class="px-2 py-1 text-right">
                                <input wire:model.live.blur="items.{{ $k }}.unit_price" @disabled(! $editable) class="{{ $in }} w-32 text-right" aria-label="Unit price">
                                @error("items.$k.unit_price") <span class="text-xs text-bad-ink">{{ $message }}</span> @enderror
                            </td>
                            <td class="whitespace-nowrap px-2 py-2 text-right">{{ $plain($totals['lines'][$n]) }}</td>
                            <td class="whitespace-nowrap px-2 py-2 text-xs">
                                @if ($editable)
                                    <button type="button" wire:click="moveItem({{ $item->id }}, -1)" aria-label="Move up">↑</button>
                                    <button type="button" wire:click="moveItem({{ $item->id }}, 1)" aria-label="Move down">↓</button>
                                    <button type="button" wire:click="removeItem({{ $item->id }})" wire:confirm="Remove this item?" class="ml-1 text-bad-ink">Remove</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-3 py-8 text-center text-muted">No items yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="flex flex-wrap items-start gap-4">
                @if ($editable)
                    <button type="button" wire:click="addItem" class="rounded-lg border border-line px-3 py-1.5 text-sm hover:bg-hover">+ Add item</button>
                @endif
                <table class="ml-auto text-sm">
                    <tr><td class="pr-6 text-muted">Subtotal</td><td class="text-right">{{ Money::format($totals['subtotal_sen']) }}</td></tr>
                    <tr><td class="pr-6 text-muted">SST ({{ Percent::format($q->sst_bp) }})</td><td class="text-right">{{ Money::format($totals['sst_sen']) }}</td></tr>
                    <tr class="font-semibold"><td class="pr-6">Total</td><td class="text-right">{{ Money::format($totals['total_sen']) }}</td></tr>
                </table>
            </div>
            <p class="text-right text-xs italic text-muted">{{ $totals['words'] }}</p>
        </div>
    @elseif ($tab === 'terms')
        <div class="space-y-2 rounded-xl border border-line bg-surface p-4 text-sm">
            <p class="text-muted">One term per line. They are numbered automatically on the quotation.</p>
            <textarea wire:model.live.blur="form.terms" rows="10" @disabled(! $editable) class="{{ $in }}" aria-label="Terms"></textarea>
            @error('form.terms') <span class="text-xs text-bad-ink">{{ $message }}</span> @enderror
            @if ($editable)
                <button type="button" wire:click="resetTerms" wire:confirm="Replace these terms with the company default?" class="rounded-lg border border-line px-3 py-1 hover:bg-hover">Reset to company default</button>
            @endif
        </div>
    @elseif ($tab === 'preview')
        <div class="rounded-xl border border-line bg-surface p-2">
            <iframe src="{{ route('quotations.pdf', [$q, 'inline' => 1]) }}" title="Quotation preview" class="h-[80vh] w-full rounded"></iframe>
        </div>
    @else
        <ul class="space-y-2 rounded-xl border border-line bg-surface p-4 text-sm">
            @forelse ($activity as $a)
                <li class="flex flex-wrap gap-x-3">
                    <span class="w-40 shrink-0 text-muted">{{ $a->created_at->timezone(App\Support\MalaysiaTime::TZ)->format('d M Y, g:i a') }}</span>
                    <span>{{ $a->description }} <span class="text-muted">— {{ $a->user?->name ?? 'System' }}</span></span>
                </li>
            @empty
                <li class="text-muted">Nothing yet.</li>
            @endforelse
        </ul>
    @endif
</div>
