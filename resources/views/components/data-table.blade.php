@props(['resizable' => null, 'minWidth' => '900px'])
{{-- A table in a card. With resizable="key", resources/js/resizable-columns.js lets people drag column edges (saved per browser). --}}
<div {{ $attributes->merge(['class' => 'relative overflow-x-auto rounded-2xl border border-line bg-surface']) }}>
    <table @if ($resizable) data-resizable="{{ $resizable }}" @endif class="w-full text-[13px]" style="min-width: {{ $minWidth }}">
        {{ $slot }}
    </table>
</div>
