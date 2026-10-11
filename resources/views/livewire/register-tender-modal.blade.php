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

        {{-- Pop-up shown right after clicking Register when none of the tender's field codes is one the company holds --}}
        @if ($codeBlock !== null)
            <x-dialog title="Field code does not match" close="dismissCodeBlock" cancelLabel="Go back">
                @if ($codeBlock === [])
                    <p class="text-[13.5px]">This tender has no field codes (kod bidang). 10 Creative Solutions normally registers a tender only under a field code it holds. Go back and enter the code(s), or register it anyway.</p>
                @else
                    <p class="text-[13.5px]">None of this tender's field codes is one that <strong>10 Creative Solutions holds</strong>. We normally register a tender only under a code we hold.</p>
                    <p class="rounded-xl bg-bad-bg px-3 py-2 text-[13px] font-semibold text-bad-ink">Tender asks for: {{ implode(', ', $codeBlock) }}</p>
                @endif
                <p class="text-[12.5px] text-muted">The company's {{ count(\App\Support\CompanyFieldCodes::all()) }} codes are listed under the Field codes box.</p>
                <x-slot:actions>
                    <button type="button" wire:click="registerAnyway" class="btn btn-dark" data-register-anyway>Register anyway</button>
                </x-slot:actions>
            </x-dialog>
        @endif
    @endif
</div>
