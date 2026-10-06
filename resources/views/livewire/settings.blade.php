@php $input = 'mt-1 w-full rounded-lg border border-line bg-surface px-3 py-2 text-sm'; @endphp
<div class="max-w-xl space-y-6">
    <header>
        <h1 class="text-2xl font-semibold">Settings</h1>
        <p class="text-sm text-muted">Your account details</p>
    </header>
    @if ($status) <p class="rounded-lg bg-good-bg px-3 py-2 text-sm text-good-ink">{{ $status }}</p> @endif

    <form wire:submit="saveProfile" class="space-y-3 rounded-xl border border-line bg-surface p-4">
        <h2 class="font-medium">Profile</h2>
        <label class="block text-sm"><span class="text-muted">Name</span><input wire:model="name" class="{{ $input }}"></label>
        @error('name') <p class="text-xs text-bad-ink">{{ $message }}</p> @enderror
        <p class="text-sm text-muted">Email: {{ auth()->user()->email }} · Role: {{ auth()->user()->role->label() }}</p>
        <button class="rounded-lg bg-chip px-4 py-2 text-sm font-medium text-chip-ink">Save</button>
    </form>

    <form wire:submit="changePassword" class="space-y-3 rounded-xl border border-line bg-surface p-4">
        <h2 class="font-medium">Change password</h2>
        <label class="block text-sm"><span class="text-muted">Current password</span><input type="password" wire:model="currentPassword" class="{{ $input }}"></label>
        @error('currentPassword') <p class="text-xs text-bad-ink">{{ $message }}</p> @enderror
        <label class="block text-sm"><span class="text-muted">New password (at least 8 characters)</span><input type="password" wire:model="newPassword" class="{{ $input }}"></label>
        @error('newPassword') <p class="text-xs text-bad-ink">{{ $message }}</p> @enderror
        <label class="block text-sm"><span class="text-muted">Type it again</span><input type="password" wire:model="newPassword_confirmation" class="{{ $input }}"></label>
        <button class="rounded-lg bg-chip px-4 py-2 text-sm font-medium text-chip-ink">Change password</button>
    </form>
</div>
