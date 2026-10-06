<form wire:submit="login" class="space-y-4">
    <h1 class="text-xl font-semibold">Sign in</h1>
    @if (session('status'))
        <p class="rounded-lg bg-good-bg px-3 py-2 text-sm text-good-ink">{{ session('status') }}</p>
    @endif
    <label class="block text-sm">
        <span class="text-muted">Email</span>
        <input type="email" wire:model="email" autocomplete="username" required
               class="mt-1 w-full rounded-lg border border-line bg-surface px-3 py-2">
    </label>
    @error('email') <p class="text-sm text-bad-ink">{{ $message }}</p> @enderror
    <label class="block text-sm">
        <span class="text-muted">Password</span>
        <input type="password" wire:model="password" autocomplete="current-password" required
               class="mt-1 w-full rounded-lg border border-line bg-surface px-3 py-2">
    </label>
    <label class="flex items-center gap-2 text-sm text-muted">
        <input type="checkbox" wire:model="remember"> Keep me signed in
    </label>
    <button type="submit" class="w-full rounded-lg bg-chip px-4 py-2 font-medium text-chip-ink hover:bg-chip-hover">
        Sign in
    </button>
    <a href="{{ route('password.request') }}" class="block text-center text-sm text-muted hover:text-ink">Forgot password?</a>
</form>
