@if ($modal)
    @php $input = 'mt-1 w-full rounded-lg border border-line bg-surface px-3 py-2 text-sm'; @endphp
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-md space-y-4 rounded-2xl bg-surface p-6 shadow-xl">
            @switch($modal)
                @case('done')
                    <h2 class="text-lg font-semibold">Mark as Done</h2>
                    <p class="text-sm text-muted">This records that the bid was submitted and locks the tender.</p>
                    <label class="block text-sm"><span class="text-muted">Submitted price (RM) *</span>
                        <input wire:model="submittedPrice" inputmode="decimal" class="{{ $input }}"></label>
                    @error('submittedPrice') <p class="text-xs text-bad-ink">{{ $message }}</p> @enderror
                    @php $confirm = ['markDone', 'Mark Done']; @endphp
                    @break
                @case('cancel')
                    <h2 class="text-lg font-semibold">Cancel tender</h2>
                    <p class="text-sm text-muted">The tender moves to Lost and is marked as cancelled.</p>
                    <label class="block text-sm"><span class="text-muted">Reason *</span>
                        <textarea wire:model="cancelReason" rows="3" class="{{ $input }}"></textarea></label>
                    @error('cancelReason') <p class="text-xs text-bad-ink">{{ $message }}</p> @enderror
                    @php $confirm = ['cancelTender', 'Cancel tender']; @endphp
                    @break
                @case('awarded')
                    <h2 class="text-lg font-semibold">Mark as Awarded</h2>
                    <p class="text-sm text-muted">Confirm CMT won this tender.</p>
                    @php $confirm = ['markAwarded', 'Mark Awarded']; @endphp
                    @break
                @case('lost')
                    <h2 class="text-lg font-semibold">Mark as Lost</h2>
                    <label class="block text-sm"><span class="text-muted">Winning price (RM), if known</span>
                        <input wire:model="winningPrice" inputmode="decimal" class="{{ $input }}"></label>
                    @error('winningPrice') <p class="text-xs text-bad-ink">{{ $message }}</p> @enderror
                    <label class="block text-sm"><span class="text-muted">Reason, if known</span>
                        <textarea wire:model="lostReason" rows="2" class="{{ $input }}"></textarea></label>
                    @php $confirm = ['markLost', 'Mark Lost']; @endphp
                    @break
                @case('reopen')
                    <h2 class="text-lg font-semibold">Reopen tender</h2>
                    <p class="text-sm text-muted">Moves it back to In Progress and clears the submitted/winning prices. Use this to correct a mistake.</p>
                    @php $confirm = ['reopen', 'Reopen']; @endphp
                    @break
            @endswitch
            <div class="flex justify-end gap-2">
                <button type="button" wire:click="closeModal" class="rounded-lg px-4 py-2 text-sm hover:bg-hover">Back</button>
                <button type="button" wire:click="{{ $confirm[0] }}" class="rounded-lg bg-chip px-4 py-2 text-sm font-medium text-chip-ink">{{ $confirm[1] }}</button>
            </div>
        </div>
    </div>
@endif
