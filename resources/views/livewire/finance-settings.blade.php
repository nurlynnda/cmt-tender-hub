@php
    $input = 'rounded-[9px] border border-line-2 bg-surface px-2.5 py-1.5 text-[13px] font-normal text-ink';
    $lbl = 'text-[12px] font-semibold text-muted';
    $th = 'px-3.5 py-3 text-left text-[10.5px] font-bold uppercase tracking-[0.5px] text-muted';
@endphp
<div class="space-y-5">
    <x-page-heading title="Finance Settings" subtitle="Company defaults, letterhead and project types" />
    @if ($notice) <div class="rounded-xl bg-good-bg p-3 text-[13px] text-good-ink" role="status">{{ $notice }}</div> @endif
    @if ($problem) <div class="rounded-xl bg-bad-bg p-3 text-[13px] text-bad-ink" role="alert">{{ $problem }}</div> @endif

    <x-card title="Company defaults" subtitle="Copied into each new project when its tender is awarded. Changing them does not alter running projects." icon="settings">
        <div class="flex flex-wrap items-end gap-3">
            <label class="flex items-center gap-2 {{ $lbl }}">Project charges % <input wire:model="charge" class="{{ $input }} w-24"></label>
            <label class="flex items-center gap-2 {{ $lbl }}">Commission share % <input wire:model="share" class="{{ $input }} w-24"></label>
            <button type="button" wire:click="saveDefaults" class="btn btn-dark">Save</button>
        </div>
        @error('charge') <p class="mt-1 text-xs text-bad-ink">{{ $message }}</p> @enderror
        @error('share') <p class="mt-1 text-xs text-bad-ink">{{ $message }}</p> @enderror
    </x-card>

    <x-card title="Company letterhead" subtitle="Printed at the top of every quotation. Each new quotation keeps a copy, so changes here never alter quotations already created." icon="quotation">
        <div class="grid gap-3 md:grid-cols-2">
            @foreach (['name' => 'Company name', 'registration_no' => 'Registration no.', 'sst_no' => 'SST no.', 'phone' => 'Phone', 'email' => 'Email', 'website' => 'Website'] as $k => $label)
                <label class="flex flex-col gap-1 {{ $lbl }}">{{ $label }}
                    <input wire:model="company.{{ $k }}" class="{{ $input }}">
                    @error("company.$k") <span class="text-xs text-bad-ink">{{ $message }}</span> @enderror
                </label>
            @endforeach
            <label class="flex flex-col gap-1 md:col-span-2 {{ $lbl }}">Address
                <textarea wire:model="company.address" rows="2" class="{{ $input }}"></textarea>
            </label>
            <label class="flex flex-col gap-1 {{ $lbl }}">Default SST %
                <input wire:model="company.sst" class="{{ $input }} w-24">
                @error('company.sst') <span class="text-xs text-bad-ink">{{ $message }}</span> @enderror
            </label>
            <label class="flex flex-col gap-1 md:col-span-2 {{ $lbl }}">Default terms &amp; conditions (one per line)
                <textarea wire:model="company.default_terms" rows="6" class="{{ $input }}"></textarea>
            </label>
        </div>
        <button type="button" wire:click="saveCompany" class="btn btn-dark mt-3">Save letterhead</button>

        <div class="mt-4 flex flex-wrap items-center gap-3 border-t border-line pt-4 text-[13px]">
            <span class="font-bold">Company stamp</span>
            @if ($stampPath)
                <img src="{{ route('company.stamp') }}?v={{ md5($stampPath) }}" alt="Company stamp" class="h-16 rounded-lg border border-line bg-white p-1">
                <button type="button" wire:click="removeStamp" wire:confirm="Remove the stamp? Quotations already created keep theirs." class="font-semibold text-bad-ink underline">Remove</button>
            @else
                <span class="text-muted">No stamp yet.</span>
            @endif
            <input type="file" wire:model="stamp" accept="image/png,image/jpeg" class="text-xs" aria-label="Stamp image">
            <button type="button" wire:click="uploadStamp" class="btn btn-outline">Upload stamp</button>
            <span class="text-xs text-muted">PNG with a transparent background works best (1 MB or less).</span>
        </div>
        @error('stamp') <p class="mt-1 text-xs text-bad-ink">{{ $message }}</p> @enderror
    </x-card>

    <x-card title="Project types" subtitle="The approved margin is the profit management expects; profit above it earns commission." icon="tenders">
        <div class="flex flex-wrap items-end gap-3">
            <label class="flex items-center gap-2 {{ $lbl }}">Name <input wire:model="typeName" class="{{ $input }} w-64"></label>
            <label class="flex items-center gap-2 {{ $lbl }}">Approved margin % <input wire:model="typeMargin" class="{{ $input }} w-24"></label>
            <button type="button" wire:click="saveType" class="btn btn-dark">{{ $editingType ? 'Save changes' : 'Add type' }}</button>
            @if ($editingType)
                <button type="button" wire:click="cancelType" class="btn btn-outline">Cancel</button>
            @endif
        </div>
        @error('typeName') <p class="mt-1 text-xs text-bad-ink">{{ $message }}</p> @enderror
        @error('typeMargin') <p class="mt-1 text-xs text-bad-ink">{{ $message }}</p> @enderror

        <div class="relative mt-4 overflow-x-auto rounded-xl border border-line">
            <table class="w-full min-w-[560px] text-[13px]">
                <thead class="bg-subtle">
                    <tr>
                        <th class="{{ $th }}">Name</th><th class="{{ $th }} text-right">Approved margin</th>
                        <th class="{{ $th }} text-right">Projects</th><th class="{{ $th }}">Status</th>
                        <th class="{{ $th }}"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($types as $t)
                    <tr wire:key="type-{{ $t->id }}" class="border-t border-line">
                        <td class="px-3.5 py-2.5 font-semibold">{{ $t->name }}</td>
                        <td class="px-3.5 py-2.5 text-right">{{ \App\Support\Percent::format($t->approved_margin_bp) }}</td>
                        <td class="px-3.5 py-2.5 text-right">{{ $t->projects_count }}</td>
                        <td class="px-3.5 py-2.5">{{ $t->is_active ? 'In use' : 'Switched off' }}</td>
                        <td class="whitespace-nowrap px-3.5 py-2.5 text-right text-xs font-semibold">
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
    </x-card>
</div>
