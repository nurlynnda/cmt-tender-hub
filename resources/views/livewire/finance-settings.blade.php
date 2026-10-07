@php $input = 'rounded-lg border border-line bg-surface px-3 py-1.5 text-sm'; @endphp
<div class="space-y-6">
    <h1 class="text-xl font-semibold">Finance Settings</h1>
    @if ($notice) <div class="rounded-lg bg-good-bg p-3 text-sm text-good-ink" role="status">{{ $notice }}</div> @endif
    @if ($problem) <div class="rounded-lg bg-bad-bg p-3 text-sm text-bad-ink" role="alert">{{ $problem }}</div> @endif

    <section class="space-y-3 rounded-xl border border-line bg-surface p-4">
        <h2 class="font-medium">Company defaults</h2>
        <p class="text-sm text-muted">Copied into each new project when its tender is awarded. Changing them does not alter running projects.</p>
        <div class="flex flex-wrap items-end gap-3 text-sm">
            <label class="flex items-center gap-2">Project charges % <input wire:model="charge" class="{{ $input }} w-24"></label>
            <label class="flex items-center gap-2">Commission share % <input wire:model="share" class="{{ $input }} w-24"></label>
            <button type="button" wire:click="saveDefaults" class="rounded-lg bg-chip px-4 py-1.5 font-medium text-chip-ink hover:bg-chip-hover">Save</button>
        </div>
        @error('charge') <p class="text-xs text-bad-ink">{{ $message }}</p> @enderror
        @error('share') <p class="text-xs text-bad-ink">{{ $message }}</p> @enderror
    </section>

    <section class="space-y-3 rounded-xl border border-line bg-surface p-4">
        <h2 class="font-medium">Company letterhead</h2>
        <p class="text-sm text-muted">Printed at the top of every quotation. Each new quotation keeps a copy, so changes here never alter quotations already created.</p>
        <div class="grid gap-3 text-sm md:grid-cols-2">
            @foreach (['name' => 'Company name', 'registration_no' => 'Registration no.', 'sst_no' => 'SST no.', 'phone' => 'Phone', 'email' => 'Email', 'website' => 'Website'] as $k => $label)
                <label class="flex flex-col gap-1">{{ $label }}
                    <input wire:model="company.{{ $k }}" class="{{ $input }}">
                    @error("company.$k") <span class="text-xs text-bad-ink">{{ $message }}</span> @enderror
                </label>
            @endforeach
            <label class="flex flex-col gap-1 md:col-span-2">Address
                <textarea wire:model="company.address" rows="2" class="{{ $input }}"></textarea>
            </label>
            <label class="flex flex-col gap-1">Default SST %
                <input wire:model="company.sst" class="{{ $input }} w-24">
                @error('company.sst') <span class="text-xs text-bad-ink">{{ $message }}</span> @enderror
            </label>
            <label class="flex flex-col gap-1 md:col-span-2">Default terms &amp; conditions (one per line)
                <textarea wire:model="company.default_terms" rows="6" class="{{ $input }}"></textarea>
            </label>
        </div>
        <button type="button" wire:click="saveCompany" class="rounded-lg bg-chip px-4 py-1.5 text-sm font-medium text-chip-ink hover:bg-chip-hover">Save letterhead</button>

        <div class="flex flex-wrap items-center gap-3 border-t border-line pt-3 text-sm">
            <span class="font-medium">Company stamp</span>
            @if ($stampPath)
                <img src="{{ route('company.stamp') }}?v={{ md5($stampPath) }}" alt="Company stamp" class="h-16 rounded border border-line bg-white p-1">
                <button type="button" wire:click="removeStamp" wire:confirm="Remove the stamp? Quotations already created keep theirs." class="text-bad-ink underline">Remove</button>
            @else
                <span class="text-muted">No stamp yet.</span>
            @endif
            <input type="file" wire:model="stamp" accept="image/png,image/jpeg" class="text-xs" aria-label="Stamp image">
            <button type="button" wire:click="uploadStamp" class="rounded-lg border border-line px-3 py-1 hover:bg-hover">Upload stamp</button>
            <span class="text-xs text-muted">PNG with a transparent background works best (1 MB or less).</span>
        </div>
        @error('stamp') <p class="text-xs text-bad-ink">{{ $message }}</p> @enderror
    </section>

    <section class="space-y-3 rounded-xl border border-line bg-surface p-4">
        <h2 class="font-medium">Project types</h2>
        <p class="text-sm text-muted">The approved margin is the profit management expects; profit above it earns commission.</p>
        <div class="flex flex-wrap items-end gap-3 text-sm">
            <label class="flex items-center gap-2">Name <input wire:model="typeName" class="{{ $input }} w-64"></label>
            <label class="flex items-center gap-2">Approved margin % <input wire:model="typeMargin" class="{{ $input }} w-24"></label>
            <button type="button" wire:click="saveType" class="rounded-lg bg-chip px-4 py-1.5 font-medium text-chip-ink hover:bg-chip-hover">
                {{ $editingType ? 'Save changes' : 'Add type' }}</button>
            @if ($editingType)
                <button type="button" wire:click="cancelType" class="rounded-lg px-3 py-1.5 hover:bg-hover">Cancel</button>
            @endif
        </div>
        @error('typeName') <p class="text-xs text-bad-ink">{{ $message }}</p> @enderror
        @error('typeMargin') <p class="text-xs text-bad-ink">{{ $message }}</p> @enderror

        <div class="relative overflow-x-auto">
            <table class="w-full min-w-[560px] text-sm">
                <thead class="bg-subtle text-left text-xs uppercase text-muted">
                    <tr>
                        <th class="px-3 py-2">Name</th><th class="px-3 py-2 text-right">Approved margin</th>
                        <th class="px-3 py-2 text-right">Projects</th><th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($types as $t)
                    <tr wire:key="type-{{ $t->id }}" class="border-t border-line">
                        <td class="px-3 py-2">{{ $t->name }}</td>
                        <td class="px-3 py-2 text-right">{{ \App\Support\Percent::format($t->approved_margin_bp) }}</td>
                        <td class="px-3 py-2 text-right">{{ $t->projects_count }}</td>
                        <td class="px-3 py-2">{{ $t->is_active ? 'In use' : 'Switched off' }}</td>
                        <td class="whitespace-nowrap px-3 py-2 text-right text-xs">
                            <button type="button" wire:click="editType({{ $t->id }})" class="underline">Edit</button>
                            <button type="button" wire:click="toggleType({{ $t->id }})" class="ml-2 underline">{{ $t->is_active ? 'Switch off' : 'Switch on' }}</button>
                            @if ($t->projects_count === 0)
                                <button type="button" wire:click="deleteType({{ $t->id }})" wire:confirm="Delete {{ $t->name }}?" class="ml-2 text-bad-ink">Delete</button>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>
</div>
