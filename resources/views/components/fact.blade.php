@props(['label'])
<div {{ $attributes->merge(['class' => 'min-w-0']) }}>
    <div class="text-[10.5px] font-bold uppercase tracking-[0.5px] text-muted">{{ $label }}</div>
    <div class="mt-0.5 truncate text-[13px] font-semibold">{{ $slot }}</div>
</div>
