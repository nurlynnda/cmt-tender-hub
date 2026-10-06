@php
    $input = 'mt-1 w-full rounded-lg border border-line bg-surface px-3 py-2 text-sm';
    $personLabel = fn ($p) => $p->name.($p->is_active === false ? ' (deactivated)' : '');
    $err = fn ($f) => $errors->first("form.$f");
@endphp
<div class="grid gap-4 sm:grid-cols-2">
    <label class="text-sm"><span class="text-muted">Mode</span>
        <select wire:model="form.mode" class="{{ $input }}">
            @foreach ($modes as $m) <option value="{{ $m->value }}">{{ $m->label() }}</option> @endforeach
        </select>
    </label>
    <label class="text-sm"><span class="text-muted">Type</span>
        <select wire:model="form.type" class="{{ $input }}">
            @foreach ($types as $t) <option value="{{ $t->value }}">{{ $t->label() }}</option> @endforeach
        </select>
    </label>
    <label class="text-sm"><span class="text-muted">Tender code *</span>
        <input wire:model.blur="form.tenderCode" class="{{ $input }}" placeholder="QT26…">
        @if ($e = $err('tenderCode')) <span class="text-xs text-bad-ink">{{ $e }}</span> @endif
    </label>
    <label class="text-sm"><span class="text-muted">Category</span>
        <select wire:model="form.category" class="{{ $input }}">
            @foreach ($categories as $c) <option value="{{ $c->value }}">{{ $c->value }}</option> @endforeach
        </select>
    </label>
    <label class="text-sm sm:col-span-2"><span class="text-muted">Title *</span>
        <textarea wire:model="form.title" rows="2" class="{{ $input }}"></textarea>
        @if ($e = $err('title')) <span class="text-xs text-bad-ink">{{ $e }}</span> @endif
    </label>
    <label class="text-sm sm:col-span-2"><span class="text-muted">Client / agency * (pick a ministry or type any name)</span>
        <input wire:model="form.client" list="ministry-list" class="{{ $input }}">
        <datalist id="ministry-list">
            @foreach ($ministries as $m) <option value="{{ $m }}"></option> @endforeach
        </datalist>
        @if ($e = $err('client')) <span class="text-xs text-bad-ink">{{ $e }}</span> @endif
    </label>
    <label class="text-sm"><span class="text-muted">Person in charge (PIC) *</span>
        <select wire:model="form.picId" class="{{ $input }}">
            <option value="">Choose…</option>
            @foreach ($people as $p) <option value="{{ $p->id }}">{{ $personLabel($p) }}</option> @endforeach
        </select>
        @if ($e = $err('picId')) <span class="text-xs text-bad-ink">{{ $e }}</span> @endif
    </label>
    <label class="text-sm"><span class="text-muted">Opportunity owner</span>
        <select wire:model="form.ownerId" class="{{ $input }}">
            <option value="">None</option>
            @foreach ($people as $p) <option value="{{ $p->id }}">{{ $personLabel($p) }}</option> @endforeach
        </select>
        @if ($e = $err('ownerId')) <span class="text-xs text-bad-ink">{{ $e }}</span> @endif
    </label>
    <label class="text-sm"><span class="text-muted">Publish date</span>
        <input type="date" wire:model="form.publishDate" class="{{ $input }}">
        @if ($e = $err('publishDate')) <span class="text-xs text-bad-ink">{{ $e }}</span> @endif
    </label>
    <label class="text-sm"><span class="text-muted">Closing date *</span>
        <input type="date" wire:model="form.closingDate" class="{{ $input }}">
        @if ($e = $err('closingDate')) <span class="text-xs text-bad-ink">{{ $e }}</span> @endif
    </label>
    <div class="text-sm">
        <label class="flex items-center gap-2"><input type="checkbox" wire:model.live="form.hasBriefing"> There is a briefing</label>
        @if ($form->hasBriefing)
            <input type="date" wire:model="form.briefingDate" class="{{ $input }}" aria-label="Briefing date">
            @if ($e = $err('briefingDate')) <span class="text-xs text-bad-ink">{{ $e }}</span> @endif
        @endif
    </div>
    <label class="text-sm"><span class="text-muted">Estimated value (RM)</span>
        <input wire:model="form.estimatedValue" inputmode="decimal" class="{{ $input }}" placeholder="e.g. 162,006.10">
        @if ($e = $err('estimatedValue')) <span class="text-xs text-bad-ink">{{ $e }}</span> @endif
    </label>
    <label class="text-sm sm:col-span-2"><span class="text-muted">Scope of work</span>
        <textarea wire:model="form.scope" rows="3" class="{{ $input }}"></textarea>
    </label>
</div>
