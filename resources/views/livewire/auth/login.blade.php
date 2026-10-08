@php $in = 'mt-1 w-full rounded-[10px] border border-line-2 bg-surface px-3 py-2.5 text-[13.5px]'; @endphp
<form wire:submit="login" class="space-y-4">
    <div>
        <h1 class="text-xl font-extrabold tracking-tight">Sign in</h1>
        <p class="mt-1 text-[13px] text-muted-2">Use your work email and password.</p>
    </div>
    @if (session('status'))
        <p class="rounded-xl bg-good-bg px-3 py-2 text-[13px] text-good-ink">{{ session('status') }}</p>
    @endif
    <label class="block text-[12px] font-semibold text-muted">Email
        <input type="email" wire:model="email" autocomplete="username" required class="{{ $in }} font-normal text-ink">
    </label>
    @error('email') <p class="text-[13px] text-bad-ink">{{ $message }}</p> @enderror
    <label class="block text-[12px] font-semibold text-muted">Password
        <input type="password" wire:model="password" autocomplete="current-password" required class="{{ $in }} font-normal text-ink">
    </label>
    <label class="flex items-center gap-2 text-[13px] text-muted">
        <input type="checkbox" wire:model="remember" class="h-4 w-4 accent-[var(--accent-solid)]"> Keep me signed in
    </label>
    <button type="submit" class="btn btn-dark w-full">Sign in</button>
    <a href="{{ route('password.request') }}" class="block text-center text-[13px] font-semibold text-muted hover:text-ink">Forgot password?</a>
</form>
