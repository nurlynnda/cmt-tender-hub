<?php

namespace App\Queries;

use App\Collector\ContractorName;
use App\Market\OwnCompany;
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
        $q = CollectedTender::query()->with(['sources', 'pipelineTenders:id,wo_number,collected_tender_id', 'winnerRows']);
        $status = in_array($f['status'] ?? 'open', ['open', 'closed', 'awarded', 'all'], true) ? ($f['status'] ?? 'open') : 'open';
        match ($status) {
            'all' => null,
            // Awarded: closed with at least one published winner.
            'awarded' => $q->where('status', 'closed')->whereIn('id', DB::table('collected_tender_winners')->select('collected_tender_id')),
            default => $q->where('status', $status),
        };
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
        // Field code: a higher level includes everything under it (05 → 0502… → 050201…). Older links may hold
        // several codes separated by commas; any of them matches. Only letters and digits are kept (no wildcards).
        $codes = array_values(array_filter(array_map(fn ($c) => preg_replace('/[^A-Za-z0-9]/', '', $c), explode(',', (string) ($f['codes'] ?? '')))));
        if ($codes !== []) {
            $q->whereIn('id', DB::table('collected_tender_field_codes')->select('collected_tender_id')
                ->where(fn ($w) => array_map(fn ($c) => $w->orWhere('code', 'like', "{$c}%"), $codes)));
        }
        if (($f['codes'] ?? '') !== '' && $codes === []) {
            $q->whereRaw('1 = 0'); // only symbols were typed: nothing matches
        }
        // Contractor: matched on the name key, so capitals, dots and spacing don't matter; % and _ are plain characters.
        $key = ContractorName::key((string) ($f['contractor'] ?? ''));
        if (mb_strlen($key) >= 2) {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $key).'%';
            // "id IN (matching winners)" lets MySQL scan the winners once (~0.2s) instead of once per tender (~1.4s).
            $q->whereIn('id', DB::table('collected_tender_winners')->select('collected_tender_id')->where('name_key', 'like', $like));
        }
        // Our wins only means something on Awarded (its button only shows there), so it's ignored elsewhere.
        if ($status === 'awarded' && ! empty($f['ours'])) {
            $own = OwnCompany::keys();
            $own === [] ? $q->whereRaw('1 = 0') : $q->whereExists(self::winner()->whereIn('w.name_key', $own));
        }
        foreach (['from' => '>=', 'to' => '<='] as $key => $op) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($f[$key] ?? ''))) {
                $q->where('closing_date', $op, $f[$key]);
            }
        }

        // Clicked "Closing" heading: soonest or latest first, tenders without a closing date always last.
        return match ($f['sort'] ?? '') {
            'closing_asc' => $q->orderByRaw('closing_date IS NULL')->orderBy('closing_date')->orderBy('id'),
            // Descending already puts undated last (MySQL sorts NULL lowest); no expression, so the index is used.
            'closing_desc' => $q->orderByDesc('closing_date')->orderByDesc('id'),
            default => self::usualOrder($q, $status),
        };
    }

    /** A winner row of the tender being filtered (for whereExists). */
    private static function winner(): \Illuminate\Database\Query\Builder
    {
        return DB::table('collected_tender_winners as w')->whereColumn('w.collected_tender_id', 'collected_tenders.id');
    }

    /** Open: closing soonest first. Closed / awarded / all: latest first. */
    private static function usualOrder(Builder $q, string $status): Builder
    {
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
