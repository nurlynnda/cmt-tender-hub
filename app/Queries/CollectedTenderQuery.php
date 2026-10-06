<?php

namespace App\Queries;

use App\Models\CollectedTender;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\{Cache, DB};

final class CollectedTenderQuery
{
    public static function build(array $f): Builder
    {
        $q = CollectedTender::query()->with(['sources', 'pipelineTenders:id,wo_number,collected_tender_id']);
        $status = in_array($f['status'] ?? 'open', ['open', 'closed', 'all'], true) ? ($f['status'] ?? 'open') : 'open';
        if ($status !== 'all') {
            $q->where('status', $status);
        }

        $search = trim((string) ($f['search'] ?? ''));
        if ($search !== '') {
            // Turn free text into safe FULLTEXT terms: letters/digits only, each "+word*".
            $words = array_filter(preg_split('/[^\p{L}\p{N}]+/u', $search), fn ($w) => mb_strlen($w) >= 3);
            $q->where(function (Builder $w) use ($words, $search) {
                if ($words !== []) {
                    $w->whereRaw('MATCH(title, reference_no, agency) AGAINST(? IN BOOLEAN MODE)', [implode(' ', array_map(fn ($x) => "+{$x}*", $words))]);
                }
                $w->orWhere('reference_no', $search);
            });
        }

        if ($source = (string) ($f['source'] ?? '')) {
            $q->whereIn('id', DB::table('collected_tender_sources')->select('collected_tender_id')->where('source', $source));
        }
        if (in_array($f['type'] ?? '', ['quotation', 'tender', 'requisition'], true)) {
            $q->where('procurement_type', $f['type']);
        }
        if (($ministry = (string) ($f['ministry'] ?? '')) !== '') {
            $q->where('ministry', $ministry);
        }
        $codes = array_filter(array_map('trim', explode(',', (string) ($f['codes'] ?? ''))));
        if ($codes !== []) {
            $q->whereIn('id', DB::table('collected_tender_field_codes')->select('collected_tender_id')->whereIn('code', $codes));
        }
        foreach (['from' => '>=', 'to' => '<='] as $key => $op) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($f[$key] ?? ''))) {
                $q->where('closing_date', $op, $f[$key]);
            }
        }

        return $status === 'open'
            ? $q->orderByRaw('closing_date IS NULL')->orderBy('closing_date')->orderBy('id')
            : $q->orderByRaw('closing_date IS NULL')->orderByDesc('closing_date')->orderByDesc('id');
    }

    /** @return list<string> */
    public static function ministries(): array
    {
        return Cache::remember('collector.ministries', 3600, fn () => CollectedTender::query()
            ->whereNotNull('ministry')->distinct()->orderBy('ministry')->pluck('ministry')->all());
    }
}
