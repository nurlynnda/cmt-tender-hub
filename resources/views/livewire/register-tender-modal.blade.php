<div>
    @if ($open)
        <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/40 p-4">
            <form wire:submit="save" class="my-8 w-full max-w-2xl space-y-4 rounded-2xl bg-surface p-6 shadow-xl">
                <header class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold">Register Tender</h2>
                    <button type="button" wire:click="$set('open', false)" class="text-muted hover:text-ink" aria-label="Close">✕</button>
                </header>

                @if ($collectedTenderId)
                    <p class="rounded-lg bg-info-bg px-3 py-2 text-sm text-info-ink">Filled in from Find Tenders — check the details and choose a PIC.</p>
                @endif
                @error('collectedTenderId') <p class="text-sm text-bad-ink">{{ $message }}</p> @enderror

                @include('livewire.partials.tender-fields')

                @if ($confirmDuplicate)
                    <div class="rounded-lg bg-warn-bg p-3 text-sm text-warn-ink">
                        This tender code is already registered as {{ implode(', ', $duplicateWoNumbers) }}.
                        Click <strong>Register anyway</strong> if this is a separate bid.
                    </div>
                @endif

                <footer class="flex justify-end gap-2">
                    <button type="button" wire:click="$set('open', false)" class="rounded-lg px-4 py-2 text-sm hover:bg-hover">Cancel</button>
                    <button type="submit" class="rounded-lg bg-chip px-4 py-2 text-sm font-medium text-chip-ink hover:bg-chip-hover">
                        {{ $confirmDuplicate ? 'Register anyway' : 'Register' }}
                    </button>
                </footer>
            </form>
        </div>
    @endif
</div>
