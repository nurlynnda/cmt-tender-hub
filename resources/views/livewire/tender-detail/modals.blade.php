@if ($modal)
    @php
        $input = 'mt-1 w-full rounded-[9px] border border-line-2 bg-surface px-2.5 py-2 text-[13px]';
        // [title, explanation, confirm action, confirm label, button style]
        [$title, $subtitle, $action, $label, $style] = match ($modal) {
            'done' => ['Mark tender as Done?', 'Marking this tender as Done will lock its costing and documents from further edits.', 'markDone', 'Yes, mark Done', 'btn-dark'],
            'cancel' => ['Cancel tender', 'The tender moves to Lost and is marked as cancelled.', 'cancelTender', 'Cancel tender', 'btn-danger'],
            'awarded' => ['Mark as Awarded', 'Confirm CMT won this tender.', 'markAwarded', 'Mark Awarded', 'btn-primary'],
            'lost' => ['Mark as Lost', 'Record the result. Both details are optional.', 'markLost', 'Mark Lost', 'btn-danger'],
            'bulk-docs' => ['Bulk Add Documents', 'Type one document name per line. Names already on the checklist are skipped.', 'bulkAddDocuments', 'Add documents', 'btn-primary'],
            'drop' => ['Drop tender', 'Use this when the company decides not to bid. The tender moves to the Dropped list and is left out of the win rate. A manager can reopen it.', 'dropTender', 'Drop tender', 'btn-danger'],
            'reopen' => ['Reopen tender', 'Moves it back to In Progress and clears the submitted and winning prices. Use this to correct a mistake.', 'reopen', 'Reopen', 'btn-outline'],
            default => ['', null, 'closeModal', 'OK', 'btn-outline'],
        };
    @endphp
    <x-dialog :title="$title" :subtitle="$subtitle" close="closeModal">
        @switch($modal)
            @case('done')
                <p class="text-[13.5px]">Submitted price (the costing's bid price):
                    <strong>{{ \App\Support\Money::format($costing['bid_price_sen'] ?? null) }}</strong></p>
                @break
            @case('cancel')
                <label class="block text-[13px]"><span class="font-semibold text-muted">Reason *</span>
                    <textarea wire:model="cancelReason" rows="3" class="{{ $input }}"></textarea></label>
                @error('cancelReason') <p class="text-xs text-bad-ink">{{ $message }}</p> @enderror
                @break
            @case('lost')
                <label class="block text-[13px]"><span class="font-semibold text-muted">Winning price (RM), if known</span>
                    <input wire:model="winningPrice" inputmode="decimal" class="{{ $input }}"></label>
                @error('winningPrice') <p class="text-xs text-bad-ink">{{ $message }}</p> @enderror
                <label class="block text-[13px]"><span class="font-semibold text-muted">Reason, if known</span>
                    <textarea wire:model="lostReason" rows="2" class="{{ $input }}"></textarea></label>
                @break
            @case('bulk-docs')
                <textarea wire:model="bulkDocuments" rows="7" placeholder="Site Visit Report&#10;Insurance Certificate&#10;Warranty Letter" class="{{ $input }}"></textarea>
                @error('bulkDocuments') <p class="text-xs text-bad-ink">{{ $message }}</p> @enderror
                @break
            @case('drop')
                <label class="block text-[13px]"><span class="font-semibold text-muted">Reason, if any</span>
                    <textarea wire:model="dropReason" rows="2" class="{{ $input }}"></textarea></label>
                @error('dropReason') <p class="text-xs text-bad-ink">{{ $message }}</p> @enderror
                @break
        @endswitch
        <x-slot:actions>
            <button type="button" wire:click="{{ $action }}" class="btn {{ $style }}">{{ $label }}</button>
        </x-slot:actions>
    </x-dialog>
@endif
