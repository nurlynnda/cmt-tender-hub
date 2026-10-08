@props(['label'])
<label class="flex min-w-0 flex-col gap-1">
    <span class="text-[11px] font-semibold text-muted">{{ $label }}</span>
    {{ $slot }}
</label>
