@php $input = 'mt-1 w-full rounded-[9px] border border-line-2 bg-surface px-2.5 py-2 text-[13px] font-normal text-ink'; @endphp
<div class="max-w-xl space-y-5">
    <x-page-heading title="Settings" subtitle="Your account details" />
    @if ($status) <p class="rounded-xl bg-good-bg px-3 py-2 text-[13px] text-good-ink">{{ $status }}</p> @endif

    <x-card title="Profile" icon="user">
        <form wire:submit="saveProfile" class="space-y-3">
            <label class="block text-[12px] font-semibold text-muted">Name<input wire:model="name" class="{{ $input }}"></label>
            @error('name') <p class="text-xs text-bad-ink">{{ $message }}</p> @enderror
            <p class="text-[13px] text-muted">Email: {{ auth()->user()->email }} · Role: {{ auth()->user()->role->label() }}</p>
            <button class="btn btn-dark">Save</button>
        </form>
    </x-card>

    <x-card title="Change password" icon="settings">
        <form wire:submit="changePassword" class="space-y-3">
            <label class="block text-[12px] font-semibold text-muted">Current password<input type="password" wire:model="currentPassword" class="{{ $input }}"></label>
            @error('currentPassword') <p class="text-xs text-bad-ink">{{ $message }}</p> @enderror
            <label class="block text-[12px] font-semibold text-muted">New password (at least 8 characters)<input type="password" wire:model="newPassword" class="{{ $input }}"></label>
            @error('newPassword') <p class="text-xs text-bad-ink">{{ $message }}</p> @enderror
            <label class="block text-[12px] font-semibold text-muted">Type it again<input type="password" wire:model="newPassword_confirmation" class="{{ $input }}"></label>
            <button class="btn btn-dark">Change password</button>
        </form>
    </x-card>
</div>
