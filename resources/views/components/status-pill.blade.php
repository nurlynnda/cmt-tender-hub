@props(['status'])
@php
    // A tender status (enum) or a quotation status string (draft, sent, expired, accepted, rejected, revised)
    $value = $status instanceof \App\Enums\TenderStatus ? $status->value : (string) $status;
    $label = $status instanceof \App\Enums\TenderStatus ? $status->label() : ucfirst($value);
    [$bg, $ink] = match ($value) {
        'in_progress', 'sent' => ['bg-info-bg', 'text-info-ink'],
        'awarded', 'accepted' => ['bg-good-bg', 'text-good-ink'],
        'lost', 'rejected', 'expired' => ['bg-bad-bg', 'text-bad-ink'],
        'done', 'draft' => ['bg-hover', 'text-muted'],
        default => ['bg-hover', 'text-muted-2'], // dropped, revised
    };
@endphp
<span data-status="{{ $value }}" {{ $attributes->merge(['class' => "inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-[11.5px] font-semibold {$bg} {$ink}"]) }}>
    <span class="h-1.5 w-1.5 rounded-full bg-current"></span>{{ $label }}
</span>
