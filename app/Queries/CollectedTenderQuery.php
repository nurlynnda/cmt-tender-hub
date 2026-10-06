<?php

namespace App\Queries;

use App\Models\CollectedTender;
use App\Support\MalaysiaTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\{Cache, DB};

final class CollectedTenderQuery
{
    /** InnoDB's default full-text stopwords of 3+ letters (never indexed, so "+the*" would match nothing useful). */
    private const STOPWORDS = ['about', 'are', 'com', 'for', 'from', 'how', 'that', 'the', 'this', 'was', 'what', 'when', 'where', 'who', 'will', 'with', 'und', 'www'];

    public static function build(array $f): Builder
    {
        $q = CollectedTender::query()->with(['sources', 'pipelineTenders:id,wo_number,collected_tender_id']);
        $status = in_array($f['status'] ?? 'open', ['open', 'closed', 'all'], true) ? ($f['status'] ?? 'open') : 'open';
        if ($status !== 'all') {
            $q->where('status', $status);
        }
        if ($status === 'open') {
            // Never show a tender as open once its closing time (12:01pm MYT on the closing day) has
            // passed — even mid-run, before StaleOpenCloser has corrected its status.
            $now = MalaysiaTime::now();
            $today = $now->toDateString();
            $q->where(fn (Builder $w) => $w->whereNull('closing_date')
                ->orWhere('closing_date', '>', $today)
                ->when($now->format('H:i') < '12:01', fn ($w) => $w->orWhere('closing_date', $today)));
        }

        $search = trim((string) ($f['search'] ?? ''));
        if ($search !== '') {
            // Safe FULLTEXT terms: letters/digits only, each "+word*". Words the index never stores
            // (MySQL's stopwords like "for"/"the") are dropped; words under 3 letters use a text match.
            $parts = array_values(array_filter(preg_split('/[^\p{L}\p{N}]+/u', $search), fn ($w) => $w !== ''));
            $words = array_filter($parts, fn ($w) => mb_strlen($w) >= 3 && ! in_array(mb_strtolower($w), self::STOPWORDS, true));
            $short = array_filter($parts, fn ($w) => mb_strlen($w) < 3);
            $q->where(function (Builder $w) use ($words, $short, $search) {
                if ($words !== []) {
                    $w->whereRaw('MATCH(title, reference_no, agency) AGAINST(? IN BOOLEAN MODE)', [implode(' ', array_map(fn ($x) => "+{$x}*", $words))]);
                } elseif ($short !== []) {
                    $w->where(function (Builder $s) use ($short) {
                        foreach ($short as $term) {
                            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';
                            $s->where(fn ($t) => $t->where('title', 'like', $like)->orWhere('reference_no', 'like', $like));
                        }
                    });
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
            // Descending order already puts undated last; no expression, so the (status, closing_date, id) index is used.
            : $q->orderByDesc('closing_date')->orderByDesc('id');
    }

    /** @return list<string> */
    public static function ministries(): array
    {
        return Cache::remember('collector.ministries', 3600, fn () => CollectedTender::query()
            ->whereNotNull('ministry')->distinct()->orderBy('ministry')->pluck('ministry')->all());
    }
}
