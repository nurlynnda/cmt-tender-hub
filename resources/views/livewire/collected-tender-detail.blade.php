@php
    use App\Collector\SourceName;
    use App\Support\Money;
    $types = ['quotation' => 'Quotation', 'tender' => 'Tender', 'requisition' => 'Requisition'];
    $wo = $t->firstPipelineTender();
    $days = $t->status === 'open' ? $t->daysLeft() : null;
@endphp
<div class="space-y-4">
    <a href="{{ route('find-tenders.index') }}" class="text-[13px] font-semibold text-muted hover:text-ink">← Back to Find Tenders</a>

    <header class="space-y-4 rounded-[20px] border border-line bg-surface p-5">
        <div class="flex flex-wrap items-center gap-2">
            <span class="font-extrabold tracking-tight">{{ $t->reference_no ?: 'No reference number' }}</span>
            <x-status-pill :status="$t->status" />
            @foreach ($t->sources as $s) <span class="rounded-full bg-hover px-2.5 py-0.5 text-[11.5px] font-semibold text-ink-2">{{ SourceName::label($s->source) }}</span> @endforeach
            <div class="ml-auto">
                @if ($wo)
                    <a href="{{ route('tenders.show', $wo) }}" class="btn border border-good-ink/30 bg-good-bg text-good-ink">Open in pipeline (WO {{ $wo->wo_number }})</a>
                @else
                    <button type="button" wire:click="$dispatch('open-register-tender', { collectedTenderId: {{ $t->id }} })" class="btn btn-primary">Register this tender</button>
                @endif
            </div>
        </div>
        <h1 class="text-xl font-extrabold leading-snug tracking-tight">{{ $t->title }}</h1>

        <div data-facts class="grid grid-cols-2 gap-x-6 gap-y-3 border-t border-line pt-4 sm:grid-cols-4">
            <x-fact label="Ministry" class="col-span-2 [&>div:last-child]:whitespace-normal">{{ $t->ministry ?? '—' }}</x-fact>
            <x-fact label="Agency" class="col-span-2 [&>div:last-child]:whitespace-normal">{{ $t->agency ?? '—' }}</x-fact>
            <x-fact label="Type">{{ $types[$t->procurement_type] ?? '—' }}</x-fact>
            <x-fact label="Advertised">{{ $t->advertised_date?->format('d M Y') ?? '—' }}</x-fact>
            <x-fact label="Closing">
                {{ $t->closing_date?->format('d M Y') ?? '—' }}
                @if ($days !== null)
                    <span @class(['block text-[11.5px]', 'text-bad-ink' => $days <= 3, 'font-normal text-muted' => $days > 3])>{{ $days === 0 ? 'Closes today' : ($days === 1 ? '1 day left' : "{$days} days left") }}</span>
                @endif
            </x-fact>
            <x-fact label="Indicative price">{{ Money::format($t->indicative_price_sen) }}</x-fact>
        </div>
    </header>

    <x-card title="Details" icon="tenders">
        <div class="grid grid-cols-2 gap-x-6 gap-y-3 sm:grid-cols-3">
            <x-fact label="Category">{{ $t->category ?? '—' }}</x-fact>
            <x-fact label="Field codes" class="[&>div:last-child]:whitespace-normal">{{ $t->fieldCodes->pluck('code')->implode(', ') ?: '—' }}</x-fact>
            <x-fact label="Last collected">{{ $t->scraped_at?->setTimezone('Asia/Kuala_Lumpur')->format('d M Y, g:i a') ?? '—' }}</x-fact>
        </div>
        <div class="mt-4 flex flex-wrap gap-3 text-[13px]">
            @foreach ($t->sources as $s)
                <a href="{{ $s->source_url }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-info-ink underline">View on {{ SourceName::label($s->source) }}</a>
            @endforeach
        </div>
    </x-card>

    @if ($t->events)
        <x-card title="Briefings and site visits" icon="calendar">
            <table class="w-full text-[13px]"><tbody>
                @foreach ($t->events as $e)
                    <tr class="border-t border-line first:border-t-0"><td class="py-2 pr-4">{{ $e['label'] }}</td><td class="whitespace-nowrap py-2 pr-4">{{ $e['date'] ? \Carbon\CarbonImmutable::parse($e['date'])->format('d M Y') : '—' }}</td><td class="py-2">{{ $e['address'] ?? '—' }}</td></tr>
                @endforeach
            </tbody></table>
        </x-card>
    @endif

    @if ($t->winners)
        <x-card title="Winners" icon="award">
            <table class="w-full text-[13px]"><tbody>
                @foreach ($t->winners as $w)
                    <tr class="border-t border-line first:border-t-0"><td class="py-2">{{ $w['name'] }}</td><td class="py-2 text-right font-semibold">{{ Money::format($w['price_sen']) }}</td></tr>
                @endforeach
            </tbody></table>
        </x-card>
    @endif

    <details class="rounded-[20px] border border-line bg-surface p-5 text-[13px]">
        <summary class="cursor-pointer font-bold">All original fields</summary>
        <dl class="mt-3 grid gap-2 sm:grid-cols-2">
            @foreach (($t->raw ?? []) as $label => $value)
                <div><dt class="text-xs text-muted">{{ $label }}</dt><dd class="break-words">{{ $value }}</dd></div>
            @endforeach
        </dl>
    </details>

    <livewire:register-tender-modal />
</div>
