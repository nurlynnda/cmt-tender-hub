@props(['active' => false])
<button type="button" role="tab" aria-selected="{{ $active ? 'true' : 'false' }}" {{ $attributes->class([
    '-mb-px whitespace-nowrap border-b-2 px-3.5 py-2.5 font-semibold',
    'border-ink text-ink' => $active, 'border-transparent text-muted hover:text-ink' => ! $active,
]) }}>{{ $slot }}</button>
