@props(['status'])
@php
    $classes = match ($status) {
        \App\Enums\TenderStatus::InProgress => 'bg-info-bg text-info-ink',
        \App\Enums\TenderStatus::Done => 'bg-subtle text-muted',
        \App\Enums\TenderStatus::Awarded => 'bg-good-bg text-good-ink',
        \App\Enums\TenderStatus::Lost => 'bg-bad-bg text-bad-ink',
    };
@endphp
<span {{ $attributes->merge(['class' => "rounded-full px-2 py-0.5 text-xs font-medium {$classes}"]) }}>{{ $status->label() }}</span>
