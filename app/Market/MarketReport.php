<?php

namespace App\Market;

use App\Collector\ContractorName;
use App\Support\MalaysiaTime;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** Government-market figures from awarded collected tenders (closed, with at least one winner). */
final class MarketReport
{
    private const MINISTRY = "COALESCE(NULLIF(t.ministry, ''), 'Not stated')";

    /** Winner rows of awarded tenders, optionally only those closing in one year. */
    private function wins(?int $year): Builder
    {
        return DB::table('collected_tender_winners as w')
            ->join('collected_tenders as t', 't.id', '=', 'w.collected_tender_id')
            ->where('t.status', 'closed')
            ->when($year, fn ($q) => $q->whereBetween('t.closing_date', ["{$year}-01-01", "{$year}-12-31"]));
    }

    /** @return array{open:int, closing_today:int, closing_week:int} open tenders by the same rule as Find Tenders */
    public function now(): array
    {
        $now = MalaysiaTime::now();
        $today = $now->toDateString();
        $open = DB::table('collected_tenders')->where('status', 'open')
            ->where(fn ($w) => $w->whereNull('closing_date')->orWhere('closing_date', '>', $today)
                ->when($now->format('H:i') < '12:01', fn ($w) => $w->orWhere('closing_date', $today)));

        return [
            'open' => (clone $open)->count(),
            'closing_today' => (clone $open)->where('closing_date', $today)->count(),
            'closing_week' => (clone $open)->whereBetween('closing_date', [$today, $now->addDays(7)->toDateString()])->count(),
        ];
    }

    /** @return array{tenders:int, value_sen:int, contractors:int, unpriced:int} */
    public function summary(?int $year): array
    {
        $row = $this->wins($year)->selectRaw('COUNT(DISTINCT w.collected_tender_id) AS tenders, COALESCE(SUM(w.price_sen), 0) AS value_sen,
            COUNT(DISTINCT w.name_key) AS contractors, COALESCE(SUM(w.price_sen IS NULL), 0) AS unpriced')->first();

        return ['tenders' => (int) $row->tenders, 'value_sen' => (int) $row->value_sen, 'contractors' => (int) $row->contractors, 'unpriced' => (int) $row->unpriced];
    }

    /** @return list<array{year:int, tenders:int, value_sen:int}> newest first; undated awards left out */
    public function byYear(): array
    {
        return $this->wins(null)->whereNotNull('t.closing_date')
            ->selectRaw('YEAR(t.closing_date) AS year, COUNT(DISTINCT w.collected_tender_id) AS tenders, COALESCE(SUM(w.price_sen), 0) AS value_sen')
            ->groupByRaw('YEAR(t.closing_date)')->orderByDesc('year')->get()
            ->map(fn ($r) => ['year' => (int) $r->year, 'tenders' => (int) $r->tenders, 'value_sen' => (int) $r->value_sen])->all();
    }

    /** @return list<int> */
    public function years(): array
    {
        return array_column($this->byYear(), 'year');
    }

    /** @return list<array{ministry:string, tenders:int, value_sen:int}> largest value first */
    public function byMinistry(?int $year, ?int $limit = null): array
    {
        return $this->wins($year)
            ->selectRaw(self::MINISTRY.' AS ministry, COUNT(DISTINCT w.collected_tender_id) AS tenders, COALESCE(SUM(w.price_sen), 0) AS value_sen')
            ->groupByRaw(self::MINISTRY)->orderByDesc('value_sen')->orderBy('ministry')
            ->when($limit, fn ($q) => $q->limit($limit))->get()
            ->map(fn ($r) => ['ministry' => $r->ministry, 'tenders' => (int) $r->tenders, 'value_sen' => (int) $r->value_sen])->all();
    }

    /** Contractors (one row per name key: name_key, name, wins, value_sen), largest value first; callers ->get() or ->paginate(). */
    public function contractors(?int $year, string $search = '', ?int $limit = null): Builder
    {
        $key = ContractorName::key($search);

        return $this->wins($year)
            ->when(mb_strlen($key) >= 2, fn ($q) => $q->where('w.name_key', 'like', '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $key).'%'))
            ->selectRaw('w.name_key, MAX(w.name) AS name, COUNT(DISTINCT w.collected_tender_id) AS wins, COALESCE(SUM(w.price_sen), 0) AS value_sen')
            ->groupBy('w.name_key')->orderByDesc('value_sen')->orderByDesc('wins')->orderBy('w.name_key')
            ->when($limit, fn ($q) => $q->limit($limit));
    }

    /**
     * Our company's place among contractors by value (all our names count as one company).
     *
     * @return array{rank:int, of:int, wins:int, value_sen:int}|null null when we won nothing that year
     */
    public function ownRank(?int $year): ?array
    {
        $own = OwnCompany::keys();
        if ($own === []) {
            return null;
        }
        $mine = $this->wins($year)->whereIn('w.name_key', $own)
            ->selectRaw('COUNT(DISTINCT w.collected_tender_id) AS wins, COALESCE(SUM(w.price_sen), 0) AS value_sen')->first();
        if ((int) $mine->wins === 0) {
            return null;
        }
        $others = $this->wins($year)->whereNotIn('w.name_key', $own)->selectRaw('COALESCE(SUM(w.price_sen), 0) AS v')->groupBy('w.name_key');
        $row = DB::query()->fromSub($others, 'x')->selectRaw('COUNT(*) AS total, COALESCE(SUM(v > ?), 0) AS ahead', [(int) $mine->value_sen])->first();

        return ['rank' => (int) $row->ahead + 1, 'of' => (int) $row->total + 1, 'wins' => (int) $mine->wins, 'value_sen' => (int) $mine->value_sen];
    }
}
