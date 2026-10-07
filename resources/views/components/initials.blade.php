@props(['name'])
<span {{ $attributes->merge(['class' => 'grid h-7 w-7 shrink-0 place-items-center rounded-full bg-accent text-[11px] font-bold text-accent-ink']) }}
      title="{{ $name }}">{{ \App\Support\Initials::of($name) }}</span>
