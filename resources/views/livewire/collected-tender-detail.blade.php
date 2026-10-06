@php
    use App\Collector\SourceName;
    use App\Support\Money;
    $types = ['quotation' => 'Quotation', 'tender' => 'Tender', 'requisition' => 'Requisition'];
    $wo = $t->firstPipelineTender();
@endphp
<div class="space-y-4">
    <a href="{{ route('find-tenders.index') }}" class="text-sm text-muted hover:text-ink">← Back to Find Tenders</a>

    <header class="rounded-xl border border-line bg-surface p-4">
        <div class="flex flex-wrap items-center gap-2">
            <span class="font-semibold">{{ $t->reference_no ?: 'No reference number' }}</span>
            <span @class(['rounded-full px-2 py-0.5 text-xs', 'bg-info-bg text-info-ink' => $t->status === 'open', 'bg-subtle text-muted' => $t->status !== 'open'])>{{ ucfirst($t->status) }}</span>
            @foreach ($t->sources as $s) <span class="rounded bg-subtle px-1.5 text-xs text-muted">{{ SourceName::label($s->source) }}</span> @endforeach
            <div class="ml-auto">
                @if ($wo)
                    <a href="{{ route('tenders.show', $wo) }}" class="rounded-lg bg-good-bg px-3 py-1.5 text-sm font-medium text-good-ink">Open in pipeline (WO {{ $wo->wo_number }})</a>
                @else
                    <button type="button" wire:click="$dispatch('open-register-tender', { collectedTenderId: {{ $t->id }} })"
                            class="rounded-lg bg-chip px-3 py-1.5 text-sm font-medium text-chip-ink hover:bg-chip-hover">Register this tender</button>
                @endif
            </div>
        </div>
        <h1 class="mt-3 text-lg font-semibold">{{ $t->title }}</h1>
    </header>

    <section class="rounded-xl border border-line bg-surface p-4">
        <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2 lg:grid-cols-3">
            @foreach ([
                'Ministry' => $t->ministry ?? '—', 'Agency' => $t->agency ?? '—', 'Category' => $t->category ?? '—',
                'Type' => $types[$t->procurement_type] ?? '—',
                'Advertised' => $t->advertised_date?->format('d M Y') ?? '—',
                'Closing' => $t->closing_date?->format('d M Y') ?? '—',
                'Indicative price' => Money::format($t->indicative_price_sen),
                'Field codes' => $t->fieldCodes->pluck('code')->implode(', ') ?: '—',
                'Last collected' => $t->scraped_at?->setTimezone('Asia/Kuala_Lumpur')->format('d M Y, g:i a') ?? '—',
            ] as $label => $value)
                <div><dt class="text-xs uppercase tracking-wide text-muted">{{ $label }}</dt><dd>{{ $value }}</dd></div>
            @endforeach
        </dl>
        <div class="mt-4 flex flex-wrap gap-3 text-sm">
            @foreach ($t->sources as $s)
                <a href="{{ $s->source_url }}" target="_blank" rel="noopener noreferrer" class="text-info-ink underline">View on {{ SourceName::label($s->source) }}</a>
            @endforeach
        </div>
    </section>

    @if ($t->events)
        <section class="rounded-xl border border-line bg-surface p-4 text-sm">
            <h2 class="mb-2 font-medium">Briefings and site visits</h2>
            <table class="w-full"><tbody>
                @foreach ($t->events as $e)
                    <tr class="border-t border-line"><td class="py-1.5 pr-4">{{ $e['label'] }}</td><td class="py-1.5 pr-4 whitespace-nowrap">{{ $e['date'] ? \Carbon\CarbonImmutable::parse($e['date'])->format('d M Y') : '—' }}</td><td class="py-1.5">{{ $e['address'] ?? '—' }}</td></tr>
                @endforeach
            </tbody></table>
        </section>
    @endif

    @if ($t->winners)
        <section class="rounded-xl border border-line bg-surface p-4 text-sm">
            <h2 class="mb-2 font-medium">Winners</h2>
            <table class="w-full"><tbody>
                @foreach ($t->winners as $w)
                    <tr class="border-t border-line"><td class="py-1.5">{{ $w['name'] }}</td><td class="py-1.5 text-right">{{ Money::format($w['price_sen']) }}</td></tr>
                @endforeach
            </tbody></table>
        </section>
    @endif

    <details class="rounded-xl border border-line bg-surface p-4 text-sm">
        <summary class="cursor-pointer font-medium">All original fields</summary>
        <dl class="mt-3 grid gap-2 sm:grid-cols-2">
            @foreach (($t->raw ?? []) as $label => $value)
                <div><dt class="text-xs text-muted">{{ $label }}</dt><dd class="break-words">{{ $value }}</dd></div>
            @endforeach
        </dl>
    </details>

    <livewire:register-tender-modal />
</div>
