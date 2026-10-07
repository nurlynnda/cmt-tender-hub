@php $in = 'w-full rounded-[10px] border border-line-2 bg-surface px-3 py-2.5 text-[13.5px]'; @endphp
<form wire:submit="resetPassword" class="space-y-4">
    <div>
        <h1 class="text-xl font-extrabold tracking-tight">Choose a new password</h1>
        <p class="mt-1 text-[13px] text-muted-2">At least 8 characters.</p>
    </div>
    <input type="email" wire:model="email" required placeholder="Email" aria-label="Email" class="{{ $in }}">
    @error('email') <p class="text-[13px] text-bad-ink">{{ $message }}</p> @enderror
    <input type="password" wire:model="password" required placeholder="New password (at least 8 characters)" aria-label="New password" class="{{ $in }}">
    @error('password') <p class="text-[13px] text-bad-ink">{{ $message }}</p> @enderror
    <input type="password" wire:model="password_confirmation" required placeholder="Type it again" aria-label="Type the new password again" class="{{ $in }}">
    <button type="submit" class="btn btn-dark w-full">Change password</button>
</form>
