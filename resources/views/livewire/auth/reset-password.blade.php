<form wire:submit="resetPassword" class="space-y-4">
    <h1 class="text-xl font-semibold">Choose a new password</h1>
    <input type="email" wire:model="email" required placeholder="Email"
           class="w-full rounded-lg border border-line bg-surface px-3 py-2">
    @error('email') <p class="text-sm text-bad-ink">{{ $message }}</p> @enderror
    <input type="password" wire:model="password" required placeholder="New password (at least 8 characters)"
           class="w-full rounded-lg border border-line bg-surface px-3 py-2">
    @error('password') <p class="text-sm text-bad-ink">{{ $message }}</p> @enderror
    <input type="password" wire:model="password_confirmation" required placeholder="Type it again"
           class="w-full rounded-lg border border-line bg-surface px-3 py-2">
    <button type="submit" class="w-full rounded-lg bg-chip px-4 py-2 font-medium text-chip-ink">Change password</button>
</form>
