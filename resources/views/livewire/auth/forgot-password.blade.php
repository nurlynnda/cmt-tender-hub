@php $in = 'w-full rounded-[10px] border border-line-2 bg-surface px-3 py-2.5 text-[13.5px]'; @endphp
<form wire:submit="send" class="space-y-4">
    <div>
        <h1 class="text-xl font-extrabold tracking-tight">Forgot password</h1>
        <p class="mt-1 text-[13px] text-muted-2">Enter your work email and we'll send you a link to choose a new password.</p>
    </div>
    @if ($status)
        <p class="rounded-xl bg-good-bg px-3 py-2 text-[13px] text-good-ink">{{ $status }}</p>
    @endif
    <input type="email" wire:model="email" required placeholder="you@company.com" aria-label="Email" class="{{ $in }}">
    @error('email') <p class="text-[13px] text-bad-ink">{{ $message }}</p> @enderror
    <button type="submit" class="btn btn-dark w-full">Send reset link</button>
    <a href="{{ route('login') }}" class="block text-center text-[13px] font-semibold text-muted hover:text-ink">Back to sign in</a>
</form>
