@php
    use App\Support\{Money, Percent};
    $in = 'rounded border border-line bg-surface px-1.5 py-1 text-sm disabled:border-transparent disabled:bg-transparent';
    $card = 'rounded-xl border border-line bg-surface p-4';
    $b = $summary['pnl']['budget'];
    $a = $summary['pnl']['actual'];
    $collection = $groupEnum->isCollection();
    $tone = [
        'pending' => 'bg-subtle text-muted', 'pr' => 'bg-info-bg text-info-ink', 'po' => 'bg-info-bg text-info-ink',
        'invoiced' => 'bg-warn-bg text-warn-ink', 'partly' => 'bg-warn-bg text-warn-ink', 'paid' => 'bg-good-bg text-good-ink',
        'received' => 'bg-good-bg text-good-ink', 'overpaid' => 'bg-warn-bg text-warn-ink',
    ];
    $neg = fn (int $v) => $v < 0 ? 'text-bad-ink' : '';
    $cols = $collection ? 8 : 11;
@endphp
<section class="space-y-4">
    @if ($problem)
        <div class="flex items-center justify-between gap-3 rounded-lg bg-warn-bg p-3 text-sm text-warn-ink" role="alert">
            <span>{{ $problem }}</span>
            <a href="{{ route('tenders.show', $tender) }}?tab=pd" class="shrink-0 font-medium underline">Reload</a>
        </div>
    @endif
    @error('form') <div class="rounded-lg bg-bad-bg p-3 text-sm text-bad-ink" role="alert">{{ $message }}</div> @enderror
    @if (! $project->isOpen())
        <div class="rounded-lg bg-subtle p-3 text-sm">
            This project is closed{{ $project->closedBy ? ' by '.$project->closedBy->name : '' }}
            on {{ $project->closed_at->timezone(App\Support\MalaysiaTime::TZ)->format('d M Y') }}. A Manager can reopen it.
        </div>
    @endif

    {{-- Header --}}
    <div class="{{ $card }} flex flex-wrap items-end gap-4 text-sm">
        <label class="flex flex-col gap-1">Project type
            <select wire:model.live="header.project_type_id" @disabled(! $canEdit) class="{{ $in }} w-64">
                <option value="">Choose…</option>
                @foreach ($types as $t)
                    <option value="{{ $t->id }}">{{ $t->name }} ({{ Percent::format($t->approved_margin_bp, 0) }})</option>
                @endforeach
            </select>
            @error('header.project_type_id') <span class="text-xs text-bad-ink">{{ $message }}</span> @enderror
        </label>
        @foreach (['approved' => 'Approved margin %', 'charge' => 'Project charges %', 'share' => 'Commission share %'] as $k => $label)
            <label class="flex flex-col gap-1">{{ $label }}
                <input wire:model.live.blur="rates.{{ $k }}" @disabled(! ($canManage && $project->isOpen())) class="{{ $in }} w-24"
                       @if (! $canManage) title="Only Managers and Admins can change this" @endif>
                @error("rates.$k") <span class="text-xs text-bad-ink">{{ $message }}</span> @enderror
            </label>
        @endforeach
        <label class="flex flex-col gap-1">Start date
            <input type="date" wire:model.live.blur="header.start_date" @disabled(! $canEdit) class="{{ $in }}">
            @error('header.start_date') <span class="text-xs text-bad-ink">{{ $message }}</span> @enderror
        </label>
        <label class="flex flex-col gap-1">End date
            <input type="date" wire:model.live.blur="header.end_date" @disabled(! $canEdit) class="{{ $in }}">
            @error('header.end_date') <span class="text-xs text-bad-ink">{{ $message }}</span> @enderror
        </label>
        @if ($canManage)
            <span class="ml-auto">
                @if ($project->isOpen())
                    <button type="button" wire:click="closeProject" wire:confirm="Close this project? It becomes read-only." class="rounded-lg border border-line px-3 py-1.5 hover:bg-hover">Close project</button>
                @else
                    <button type="button" wire:click="reopenProject" class="rounded-lg border border-line px-3 py-1.5 hover:bg-hover">Reopen project</button>
                @endif
            </span>
        @endif
    </div>

    {{-- Profit & Loss --}}
    <div class="{{ $card }}">
        <h3 class="mb-2 font-medium">Profit &amp; Loss</h3>
        <div class="relative overflow-x-auto">
            <table class="w-full min-w-[520px] text-sm">
                <thead class="text-left text-xs uppercase text-muted">
                    <tr><th class="py-1">Line</th><th class="py-1 text-right">Budget</th><th class="py-1 text-right">Actual</th></tr>
                </thead>
                <tbody>
                @foreach ([
                    ['Revenue', 'revenue', 'font-medium', null],
                    ['Less: Cost of sales', 'cost_of_sales', '', 'principal, distributor, partner'],
                    ['Less: Other costs', 'other_costs', '', 'finance, tax, misc, internal resources'],
                    ['Less: Project charges', 'charges', '', Percent::format($project->project_charge_bp).' of revenue'],
                    ['Gross profit (GP)', 'gp', 'font-semibold', null],
                    ['Commission', 'commission', '', '(GP − approved margin) × '.Percent::format($project->commission_share_bp, 0)],
                    ['Net profit', 'net', 'font-semibold', null],
                ] as [$label, $key, $weight, $hint])
                    <tr class="border-t border-line {{ $weight }}">
                        <td class="py-1.5">{{ $label }}
                            @if ($hint) <span class="text-xs font-normal text-muted">({{ $hint }})</span> @endif</td>
                        @foreach ([$b, $a] as $col)
                            <td class="whitespace-nowrap py-1.5 text-right {{ $neg($col[$key]) }}">{{ Money::format($col[$key]) }}
                                @if (in_array($key, ['gp', 'net'], true))
                                    <span class="text-xs font-normal text-muted">({{ Percent::format($col[$key === 'gp' ? 'gp_bp' : 'net_bp']) }})</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-3 flex flex-wrap items-center gap-4 border-t border-line pt-3 text-sm">
            <div>
                <p class="text-xs uppercase text-muted">Approved margin</p>
                <p class="text-lg font-semibold">{{ Money::format($b['approved_sen']) }}</p>
                <p class="text-xs text-muted">{{ Percent::format($project->approved_margin_bp) }} · {{ $project->projectType?->name ?? 'No project type yet' }}</p>
            </div>
            @if ($summary['below_margin'])
                <p class="rounded-lg bg-bad-bg px-3 py-2 text-bad-ink">⚠ Actual gross profit ({{ Percent::format($a['gp_bp']) }}) is below the approved margin ({{ Percent::format($project->approved_margin_bp) }}).</p>
            @endif
        </div>
    </div>

    {{-- Lines --}}
    <div class="{{ $card }} space-y-3">
        <div class="flex flex-wrap items-center gap-2">
            <h3 class="mr-2 font-medium">Cost lines</h3>
            @foreach (App\Enums\PdGroup::cases() as $g)
                <button type="button" wire:click="selectGroup('{{ $g->value }}')" @class([
                    'rounded-full border px-3 py-1 text-xs',
                    'border-chip bg-chip text-chip-ink' => $group === $g->value,
                    'border-line hover:bg-hover' => $group !== $g->value,
                ])>{{ $g->label() }}@if ($counts[$g->value] ?? 0) <span class="opacity-70">{{ $counts[$g->value] }}</span>@endif</button>
            @endforeach
            @if ($canEdit)
                <button type="button" wire:click="addLine" class="ml-auto rounded-lg bg-chip px-3 py-1.5 text-sm font-medium text-chip-ink hover:bg-chip-hover">+ Add line</button>
            @endif
        </div>
        <p class="text-xs text-muted">{{ $groupEnum->description() }}</p>

        <div class="relative overflow-x-auto">
            <table class="w-full min-w-[1100px] text-sm">
                <thead class="bg-subtle text-left text-xs uppercase text-muted">
                    <tr>
                        <th class="px-2 py-2">Name</th>
                        @if ($collection)
                            <th class="px-2">Scheduled date</th><th class="px-2 text-right">Scheduled amount</th><th class="px-2 text-right">Invoiced</th>
                            <th class="px-2 text-right">Received</th><th class="px-2 text-right">Still owed</th>
                        @else
                            <th class="px-2">Reference</th><th class="px-2 text-right">Budget</th><th class="px-2 text-right">PR</th>
                            <th class="px-2 text-right">PO</th><th class="px-2 text-right">Invoiced</th><th class="px-2 text-right">Paid</th>
                            <th class="px-2 text-right">Still owed</th><th class="px-2 text-right">Variance</th>
                        @endif
                        <th class="px-2">Status</th><th class="px-2"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($groupLines as $l)
                    @php $key = 'l'.$l['id']; @endphp
                    <tr wire:key="pd-{{ $l['id'] }}" class="border-t border-line align-top">
                        <td class="px-2 py-1">
                            <input wire:model.live.blur="rows.{{ $key }}.name" @disabled(! $canEdit) class="{{ $in }} w-full min-w-56" aria-label="Name">
                            @error("rows.$key.name") <span class="text-xs text-bad-ink">{{ $message }}</span> @enderror
                        </td>
                        @if ($collection)
                            <td class="px-2 py-1">
                                <input type="date" wire:model.live.blur="rows.{{ $key }}.scheduled_date" @disabled(! $canEdit) class="{{ $in }}" aria-label="Scheduled date">
                                @error("rows.$key.scheduled_date") <span class="text-xs text-bad-ink">{{ $message }}</span> @enderror
                            </td>
                            <td class="px-2 py-1 text-right">
                                <input wire:model.live.blur="rows.{{ $key }}.budget" @disabled(! $canEdit) class="{{ $in }} w-32 text-right" aria-label="Scheduled amount">
                                @error("rows.$key.budget") <span class="text-xs text-bad-ink">{{ $message }}</span> @enderror
                            </td>
                            @foreach (['invoiced', 'received', 'owed'] as $k)
                                <td class="whitespace-nowrap px-2 py-1 text-right">{{ Money::format($l[$k]) }}</td>
                            @endforeach
                        @else
                            <td class="px-2 py-1">
                                <input wire:model.live.blur="rows.{{ $key }}.reference" @disabled(! $canEdit) class="{{ $in }} w-28" aria-label="Reference">
                                @error("rows.$key.reference") <span class="text-xs text-bad-ink">{{ $message }}</span> @enderror
                            </td>
                            <td class="px-2 py-1 text-right">
                                <input wire:model.live.blur="rows.{{ $key }}.budget" @disabled(! $canEdit) class="{{ $in }} w-32 text-right" aria-label="Budget">
                                @error("rows.$key.budget") <span class="text-xs text-bad-ink">{{ $message }}</span> @enderror
                            </td>
                            @foreach (['pr', 'po', 'invoiced', 'paid', 'owed'] as $k)
                                <td class="whitespace-nowrap px-2 py-1 text-right">{{ Money::format($l[$k]) }}</td>
                            @endforeach
                            <td class="whitespace-nowrap px-2 py-1 text-right {{ $neg($l['variance']) }}">{{ Money::format($l['variance']) }}</td>
                        @endif
                        <td class="whitespace-nowrap px-2 py-1">
                            <span class="rounded-full px-2 py-0.5 text-xs {{ $tone[$l['status']] }}">{{ $l['status_label'] }}</span>
                            @if ($l['over_budget']) <span class="ml-1 rounded-full bg-bad-bg px-2 py-0.5 text-xs text-bad-ink">Over budget</span> @endif
                        </td>
                        <td class="whitespace-nowrap px-2 py-1 text-right text-xs">
                            <button type="button" wire:click="openDocuments({{ $l['id'] }})" class="underline">Documents ({{ count($l['entries']) }})</button>
                            @if ($canEdit && count($l['entries']) === 0)
                                <button type="button" wire:click="removeLine({{ $l['id'] }})" wire:confirm="Remove this line?" class="ml-2 text-bad-ink">Remove</button>
                            @endif
                        </td>
                    </tr>
                    @if ($openLine === $l['id'])
                        <tr wire:key="docs-{{ $l['id'] }}" class="bg-subtle">
                            <td colspan="{{ $cols }}" class="space-y-2 px-4 py-3">
                                <table class="w-full text-xs">
                                    <thead class="text-left text-muted">
                                        <tr><th class="py-1 pr-3">Type</th><th class="pr-3">Number</th><th class="pr-3">Date</th><th class="pr-3 text-right">Amount</th><th>Note</th><th></th></tr>
                                    </thead>
                                    <tbody>
                                    @forelse ($entries as $e)
                                        <tr wire:key="entry-{{ $e->id }}" class="border-t border-line">
                                            <td class="py-1">{{ $e->type->label() }}</td>
                                            <td>{{ $e->number ?? '—' }}</td>
                                            <td>{{ $e->date->format('d M Y') }}</td>
                                            <td class="pr-3 text-right">{{ Money::format($e->amount_sen) }}</td>
                                            <td>{{ $e->note }}</td>
                                            <td class="whitespace-nowrap text-right">
                                                @if ($canEdit)
                                                    <button type="button" wire:click="editEntry({{ $e->id }})" class="underline">Edit</button>
                                                    <button type="button" wire:click="removeEntry({{ $e->id }})" wire:confirm="Remove this document?" class="ml-2 text-bad-ink">Remove</button>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="6" class="py-2 text-muted">No documents yet.</td></tr>
                                    @endforelse
                                    </tbody>
                                </table>
                                @if ($canEdit)
                                    <div class="flex flex-wrap items-end gap-2 text-xs">
                                        <select wire:model="entry.type" class="{{ $in }}" aria-label="Document type">
                                            <option value="">Type…</option>
                                            @foreach ($groupEnum->entryTypes() as $t) <option value="{{ $t->value }}">{{ $t->label() }}</option> @endforeach
                                        </select>
                                        <input wire:model="entry.number" placeholder="Number" class="{{ $in }} w-32" aria-label="Document number">
                                        <input type="date" wire:model="entry.date" class="{{ $in }}" aria-label="Document date">
                                        <input wire:model="entry.amount" placeholder="Amount" class="{{ $in }} w-32 text-right" aria-label="Amount">
                                        <input wire:model="entry.note" placeholder="Note (optional)" class="{{ $in }} w-48" aria-label="Note">
                                        <button type="button" wire:click="saveEntry" class="rounded-lg bg-chip px-3 py-1 font-medium text-chip-ink hover:bg-chip-hover">{{ $editingEntry ? 'Save' : 'Add' }}</button>
                                        @if ($editingEntry) <button type="button" wire:click="cancelEntry" class="rounded-lg px-2 py-1 hover:bg-hover">Cancel</button> @endif
                                    </div>
                                    @foreach (['entry.type', 'entry.number', 'entry.date', 'entry.amount', 'entry.note'] as $f)
                                        @error($f) <p class="text-xs text-bad-ink">{{ $message }}</p> @enderror
                                    @endforeach
                                @endif
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr><td colspan="{{ $cols }}" class="px-3 py-6 text-center text-muted">No {{ strtolower($groupEnum->label()) }} lines yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Cash flow --}}
    <div class="{{ $card }}">
        <h3 class="mb-2 font-medium">Cash flow</h3>
        @if ($summary['cash_flow'] === [])
            <p class="text-sm text-muted">Add a start date, scheduled dates or documents to see the cash flow.</p>
        @else
            <div class="relative overflow-x-auto">
                <table class="w-full min-w-[520px] text-sm">
                    <thead class="text-left text-xs uppercase text-muted">
                        <tr><th class="py-1">Month</th><th class="py-1 text-right">Expected in</th><th class="py-1 text-right">Received</th>
                            <th class="py-1 text-right">Paid out</th><th class="py-1 text-right">Balance</th></tr>
                    </thead>
                    <tbody>
                    @foreach ($summary['cash_flow'] as $m)
                        <tr class="border-t border-line">
                            <td class="py-1">{{ \Carbon\CarbonImmutable::parse($m['month'].'-01')->format('M Y') }}</td>
                            @foreach (['expected_in', 'received', 'paid_out'] as $k)
                                <td class="py-1 text-right">{{ $m[$k] ? Money::format($m[$k]) : '—' }}</td>
                            @endforeach
                            <td class="py-1 text-right font-medium {{ $neg($m['balance']) }}">{{ Money::format($m['balance']) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        @if ($summary['duration_pct'] !== null)
            <div class="mt-3 text-xs text-muted">
                <div class="flex justify-between"><span>Project duration</span><span>{{ $summary['duration_pct'] }}%</span></div>
                <div class="mt-1 h-2 overflow-hidden rounded-full bg-subtle"><div class="h-full bg-good-ink" style="width: {{ $summary['duration_pct'] }}%"></div></div>
            </div>
        @endif
    </div>
</section>
