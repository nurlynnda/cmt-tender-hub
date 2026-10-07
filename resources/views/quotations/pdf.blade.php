@php
    use App\Support\{Money, Percent};
    $l = $q->letterhead;
    $num = fn (int $sen) => ltrim(Money::format($sen), 'RM ');
    // "Label: value" detail lines print the label in bold, like the prototype's spec sheet.
    $detail = fn (string $line) => preg_match('/^([^:]{1,40}):\s*(.+)$/u', $line, $m)
        ? '<strong>'.e($m[1]).':</strong> '.e($m[2])
        : e($line);
    $contacts = collect([
        ! empty($l['phone']) ? 'Tel: '.$l['phone'] : null,
        $l['email'] ?? null,
        $l['website'] ?? null,
    ])->filter()->implode(' · ');
@endphp
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{{ $q->number }}</title>
<style>
    @page { margin: 28px 34px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 9.5px; color: #1f2933; }
    table { width: 100%; border-collapse: collapse; }
    .muted { color: #6b7280; }
    .items th { background: #f1f2f4; font-size: 8.5px; text-transform: uppercase; padding: 6px; text-align: left; }
    .items td { padding: 6px; vertical-align: top; border-bottom: 1px solid #e5e7eb; }
    .items thead { display: table-header-group; }
    .right { text-align: right; }
    .signature { font-family: times; font-style: italic; font-size: 20px; color: #1d4ed8; }
</style>
</head>
<body>
<table>
    <tr>
        <td style="width: 65%; vertical-align: top">
            <div style="font-size: 13px; font-weight: bold">{{ $l['name'] ?? '' }}
                @if (! empty($l['registration_no'])) <span class="muted" style="font-size: 8px; font-weight: normal">({{ $l['registration_no'] }})</span> @endif
            </div>
            <div class="muted">{!! nl2br(e($l['address'] ?? '')) !!}</div>
            @if ($contacts !== '') <div class="muted">{{ $contacts }}</div> @endif
            @if (! empty($l['sst_no'])) <div class="muted">SST No.: {{ $l['sst_no'] }}</div> @endif
        </td>
        <td class="right" style="vertical-align: top">
            <div style="font-size: 18px; font-weight: bold; letter-spacing: 1px">QUOTATION</div>
            <table style="width: auto; margin-left: auto">
                <tr><td class="muted right">No.</td><td class="right"><strong>{{ $q->number }}</strong></td></tr>
                <tr><td class="muted right">Date</td><td class="right">{{ $q->quote_date->format('d M Y') }}</td></tr>
                <tr><td class="muted right">Valid until</td><td class="right">{{ $q->validUntil()->format('d M Y') }}</td></tr>
            </table>
        </td>
    </tr>
</table>
<div style="border-bottom: 1.5px solid #dc2626; margin: 10px 0 14px"></div>

<div class="muted" style="font-size: 8px; font-weight: bold">QUOTATION TO</div>
<div style="font-weight: bold">{{ $q->customer_name }}</div>
<div class="muted">{!! nl2br(e((string) $q->customer_address)) !!}</div>
@if ($q->attention)
    <div style="margin-top: 4px"><span class="muted">Attn:</span> <strong>{{ $q->attention }}</strong>
        {{ collect([$q->attention_phone, $q->attention_email])->filter()->implode(' · ') }}</div>
@endif
<div style="background: #f3f4f6; padding: 6px 8px; margin: 10px 0"><strong>Subject:</strong> {{ $q->subject }}</div>

<table class="items">
    <thead>
        <tr>
            <th style="width: 24px">No</th><th>Description</th><th class="right" style="width: 40px">Qty</th><th style="width: 40px">Unit</th>
            <th class="right" style="width: 80px">Unit price (RM)</th><th class="right" style="width: 85px">Amount (RM)</th>
        </tr>
    </thead>
    <tbody>
    @foreach ($q->items as $i => $item)
        <tr>
            <td>{{ $i + 1 }}</td>
            <td><strong>{{ $item->title }}</strong>
                @foreach (array_filter(array_map('trim', preg_split('/\R/', (string) $item->details))) as $line)
                    <div class="muted" style="font-size: 8.5px">{!! $detail($line) !!}</div>
                @endforeach
            </td>
            <td class="right">{{ $item->quantity }}</td>
            <td>{{ $item->unit }}</td>
            <td class="right">{{ $num($item->unit_price_sen) }}</td>
            <td class="right">{{ $num($totals['lines'][$i]) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<table style="width: 45%; margin-left: auto; margin-top: 8px">
    <tr><td>Subtotal</td><td class="right">{{ $num($totals['subtotal_sen']) }}</td></tr>
    <tr><td>SST ({{ Percent::format($q->sst_bp) }})</td><td class="right">{{ $num($totals['sst_sen']) }}</td></tr>
    <tr>
        <td style="border-top: 1.5px solid #1f2933; font-weight: bold">Total (RM)</td>
        <td class="right" style="border-top: 1.5px solid #1f2933; font-weight: bold">{{ $num($totals['total_sen']) }}</td>
    </tr>
</table>
<div class="muted" style="font-style: italic; margin: 8px 0 14px">{{ $totals['words'] }}</div>

@if ($terms !== [])
    <div style="font-weight: bold; font-size: 8.5px">TERMS &amp; CONDITIONS</div>
    <table>
        @foreach ($terms as $n => $term)
            <tr><td style="width: 18px; vertical-align: top">{{ $n + 1 }}.</td><td>{{ $term }}</td></tr>
        @endforeach
    </table>
@endif

<table style="margin-top: 24px">
    <tr>
        <td style="width: 50%; vertical-align: top">
            <div class="muted">Prepared by,</div>
            <div style="height: 46px; position: relative">
                @if ($q->show_signature) <div class="signature">{{ $q->preparer->name }}</div> @endif
                @if ($stamp) <img src="{{ $stamp }}" style="height: 60px; position: absolute; left: 140px; top: -8px" alt=""> @endif
            </div>
            <div style="border-top: 1px solid #9ca3af; width: 80%; padding-top: 3px"><strong>{{ $q->preparer->name }}</strong></div>
            @if ($q->preparer_position) <div class="muted">{{ $q->preparer_position }}</div> @endif
            <div class="muted">{{ $l['name'] ?? '' }}</div>
            <div class="muted">{{ collect([$q->preparer_phone, $q->preparer_email])->filter()->implode(' · ') }}</div>
        </td>
        <td style="vertical-align: top">
            <div class="muted">Accepted by,</div>
            <div style="height: 46px"></div>
            <div style="border-top: 1px solid #9ca3af; width: 85%; padding-top: 3px" class="muted">Name, signature &amp; company stamp<br>Date:</div>
        </td>
    </tr>
</table>
<div class="muted" style="text-align: center; border-top: 1px solid #e5e7eb; margin-top: 18px; padding-top: 6px">This is a computer-generated quotation.</div>
</body>
</html>
