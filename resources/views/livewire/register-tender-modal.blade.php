<div>
    @if ($open)
        <x-dialog title="Register Tender" subtitle="Add a tender to In Progress. The WO number is given when you register." close="close" wide :dismissible="false">
            <form wire:submit="save" id="register-tender" class="space-y-4">
                <div class="grid grid-cols-2 gap-3 rounded-xl bg-subtle p-3">
                    <x-fact label="WO Number (auto — final number given on save)">{{ $woPreview }}</x-fact>
                    <x-fact label="WO Date (auto)">{{ $woDate->format('d M Y') }}</x-fact>
                </div>

                @if ($collectedTenderId)
                    <p class="rounded-xl bg-info-bg px-3 py-2 text-[13px] text-info-ink">Filled in from Find Tenders — check the details and choose a PIC.</p>
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
    @endif
</div>
