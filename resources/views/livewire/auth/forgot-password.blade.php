<form wire:submit="send" class="space-y-4">
    <h1 class="text-xl font-semibold">Forgot password</h1>
    <p class="text-sm text-muted">Enter your work email and we'll send you a link to choose a new password.</p>
    @if ($status)
        <p class="rounded-lg bg-good-bg px-3 py-2 text-sm text-good-ink">{{ $status }}</p>
    @endif
    <input type="email" wire:model="email" required placeholder="you@company.com"
           class="w-full rounded-lg border border-line bg-surface px-3 py-2">
    @error('email') <p class="text-sm text-bad-ink">{{ $message }}</p> @enderror
    <button type="submit" class="w-full rounded-lg bg-chip px-4 py-2 font-medium text-chip-ink">Send reset link</button>
    <a href="{{ route('login') }}" class="block text-center text-sm text-muted hover:text-ink">Back to sign in</a>
</form>
