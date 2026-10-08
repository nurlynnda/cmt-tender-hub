@props(['user'])
@if ($user)
    <span {{ $attributes->merge(['class' => 'grid h-7 w-7 shrink-0 place-items-center rounded-full bg-accent text-[11px] font-semibold text-accent-ink']) }}
          title="{{ $user->name }}">{{ $user->initials() }}</span>
@endif
