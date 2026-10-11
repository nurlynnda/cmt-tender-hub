<div>
    @if ($open)
        <x-dialog title="Register Tender" subtitle="Add a tender to In Progress. The WO number is given when you register." close="close" wide :dismissible="false">
            <form wire:submit="save" id="register-tender" class="space-y-4">
                <div class="grid grid-cols-2 gap-3 rounded-xl bg-subtle p-3">
                    <x-fact label="WO Number (auto — final number given on save)">{{ $woPreview }}</x-fact>
                    <x-fact label="WO Date (auto)">{{ $woDate->format('d M Y') }}</x-fact>
                </div>

                @if ($collectedTenderId)
                    <p class="rounded-xl bg-info-bg px-3 py-2 text-[13px] text-info-ink">Filled in from Find Tenders — check the details. You can choose a PIC now or later.</p>
                @endif
                @error('collectedTenderId') <p class="text-sm text-bad-ink">{{ $message }}</p> @enderror

                @include('livewire.partials.tender-fields')

                @if ($confirmDuplicate)
                    <div class="rounded-xl bg-warn-bg p-3 text-[13px] text-warn-ink">
                        This tender code is already registered as {{ implode(', ', $duplicateWoNumbers) }}.
                        Click <strong>Register anyway</strong> if this is a separate bid.
                    </div>
                @endif
            </form>
            <x-slot:actions>
                <button type="submit" form="register-tender" class="btn btn-primary">{{ $confirmDuplicate ? 'Register anyway' : 'Register' }}</button>
            </x-slot:actions>
        </x-dialog>

        {{-- Pop-up reminder: CMT holds none of this tender's field codes, so it cannot be registered --}}
        @if ($codeBlock !== null)
            <x-dialog title="Cannot register this tender" close="dismissCodeBlock" cancelLabel="OK, got it">
                @if ($codeBlock === [])
                    <p class="text-[13.5px]">This tender has no field codes (kod bidang). Enter the field code(s) it asks for — we can only register a tender under a code CMT holds.</p>
                @else
                    <p class="text-[13.5px]">We cannot register this tender because <strong>we do not hold its field code</strong>.</p>
                    <p class="rounded-xl bg-bad-bg px-3 py-2 text-[13px] font-semibold text-bad-ink">Tender asks for: {{ implode(', ', $codeBlock) }}</p>
                @endif
                <p class="text-[12.5px] text-muted">CMT's codes are listed under the Field codes box. Only a tender with at least one of them can be registered.</p>
                <x-slot:actions></x-slot:actions>
            </x-dialog>
        @endif
    @endif
</div>
