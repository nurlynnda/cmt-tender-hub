<?php

namespace App\Market;

use App\Collector\ContractorName;
use App\Support\MalaysiaTime;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\{Cache, DB};

/**
 * Government-market figures from awarded collected tenders (closed, with at least one winner).
 *
 * Grouping 150k+ awards takes seconds, so finished figures are remembered until the winners list
 * changes (new or updated awards from a collection) and for six hours at most.
 */
final class MarketReport
{
    private ?string $version = null;

    /** The first year counted; earlier years (and awards with no closing date) are left out. */
    public static function fromYear(): int
    {
        return (int) config('tenderhub.market_from_year', 2023);
    }

    /** "2023 – now": the label for the no-single-year view. */
    public static function periodLabel(): string
    {
        return self::fromYear().' – now';
    }

    /** Winner rows of awarded tenders closing in one year, or (null) from fromYear() on. */
    private function wins(?int $year): Builder
    {
        return DB::table('collected_tender_winners as w')
            ->join('collected_tenders as t', 't.id', '=', 'w.collected_tender_id')
            ->where('t.status', 'closed')
            ->when($year,
                fn ($q) => $q->whereBetween('t.closing_date', ["{$year}-01-01", "{$year}-12-31"]),
                fn ($q) => $q->where('t.closing_date', '>=', self::fromYear().'-01-01')); // undated awards drop out too
    }

    /** Changes whenever winner rows are added, replaced or removed. */
    private function version(): string
    {
        if ($this->version === null) {
            $v = DB::table('collected_tender_winners')->selectRaw('COUNT(*) AS c, COALESCE(MAX(id), 0) AS m')->first();
            $this->version = "{$v->c}-{$v->m}";
        }

        return $this->version;
    }

    private function remember(string $name, ?int $year, Closure $compute): mixed
    {
        // One fixed key per figure, holding the winners-list version it was worked out for: a newer
        // version overwrites it, so old copies never pile up. (Also boxed: the cache can't hold a bare null.)
        $key = "market:{$name}:".($year ?? 'from'.self::fromYear());
        $version = $this->version();
        $this->version = null; // check again on the next read, so new awards show straight away
        $box = Cache::get($key);
        if (! is_array($box) || ($box['version'] ?? null) !== $version) {
            $box = ['version' => $version, 'value' => $compute()];
            Cache::put($key, $box, now()->addHours(6));
        }

        return $box['value'];
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
        return $this->remember('summary', $year, function () use ($year) {
            $row = $this->wins($year)->selectRaw('COUNT(DISTINCT w.collected_tender_id) AS tenders, COALESCE(SUM(w.price_sen), 0) AS value_sen,
                COUNT(DISTINCT w.name_key) AS contractors, COALESCE(SUM(w.price_sen IS NULL), 0) AS unpriced')->first();

            return ['tenders' => (int) $row->tenders, 'value_sen' => (int) $row->value_sen, 'contractors' => (int) $row->contractors, 'unpriced' => (int) $row->unpriced];
        });
    }

    /** @return list<array{year:int, tenders:int, value_sen:int}> newest first; undated awards left out */
    public function byYear(): array
    {
        return $this->remember('by-year', null, fn () => $this->wins(null)
            ->selectRaw('YEAR(t.closing_date) AS year, COUNT(DISTINCT w.collected_tender_id) AS tenders, COALESCE(SUM(w.price_sen), 0) AS value_sen')
            ->groupByRaw('YEAR(t.closing_date)')->orderByDesc('year')->get()
            ->map(fn ($r) => ['year' => (int) $r->year, 'tenders' => (int) $r->tenders, 'value_sen' => (int) $r->value_sen])->all());
    }

    /** @return list<int> */
    public function years(): array
    {
        return array_column($this->byYear(), 'year');
    }

    /** @return list<array{ministry:string, tenders:int, value_sen:int}> largest value first; blank or missing ministry = "Not stated" */
    public function byMinistry(?int $year, ?int $limit = null): array
    {
        $all = $this->remember('by-ministry', $year, function () use ($year) {
            // Grouped on the plain column (fast), then blank and missing merged here.
            $merged = [];
            $rows = $this->wins($year)->selectRaw('t.ministry, COUNT(DISTINCT w.collected_tender_id) AS tenders, COALESCE(SUM(w.price_sen), 0) AS value_sen')
                ->groupBy('t.ministry')->get();
            foreach ($rows as $r) {
                $name = trim((string) $r->ministry) === '' ? 'Not stated' : $r->ministry;
                $merged[$name] ??= ['ministry' => $name, 'tenders' => 0, 'value_sen' => 0];
                $merged[$name]['tenders'] += (int) $r->tenders;
                $merged[$name]['value_sen'] += (int) $r->value_sen;
            }
            $list = array_values($merged);
            usort($list, fn ($a, $b) => [$b['value_sen'], $a['ministry']] <=> [$a['value_sen'], $b['ministry']]);

            return $list;
        });

        return $limit ? array_slice($all, 0, $limit) : $all;
    }

    /**
     * Every contractor (one per name key), largest value first, then most wins, then name; with a search,
     * only names whose key contains the searched key (ignored under 2 characters).
     *
     * @return list<array{name_key:string, name:string, wins:int, value_sen:int}>
     */
    public function contractors(?int $year, string $search = ''): array
    {
        $all = $this->remember('contractors', $year, fn () => $this->wins($year)
            ->selectRaw('w.name_key, MAX(w.name) AS name, COUNT(DISTINCT w.collected_tender_id) AS wins, COALESCE(SUM(w.price_sen), 0) AS value_sen')
            ->groupBy('w.name_key')->orderByDesc('value_sen')->orderByDesc('wins')->orderBy('w.name_key')->get()
            ->map(fn ($c) => ['name_key' => $c->name_key, 'name' => $c->name, 'wins' => (int) $c->wins, 'value_sen' => (int) $c->value_sen])->all());
        $key = ContractorName::key($search);

        return mb_strlen($key) < 2 ? $all : array_values(array_filter($all, fn ($c) => str_contains($c['name_key'], $key)));
    }

    /** @return list<array{name_key:string, name:string, wins:int, value_sen:int}> */
    public function topContractors(?int $year, int $limit): array
    {
        // Kept apart from the full list (28k+ rows for all years), which is slower to load back.
        return $this->remember("top-{$limit}", $year, fn () => array_slice($this->contractors($year), 0, $limit));
    }

    /** Works out the figures the Market Insights pages open with (current year and all years), so no visitor waits. */
    public function warm(): void
    {
        $this->byYear();
        foreach ([(int) MalaysiaTime::today()->format('Y'), null] as $year) {
            $this->summary($year);
            $this->byMinistry($year);
            $this->contractors($year);
            $this->topContractors($year, 10);
            $this->ownRank($year);
        }
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

        return $this->remember('own-'.md5(implode('|', $own)), $year, function () use ($year, $own) {
            $mine = $this->wins($year)->whereIn('w.name_key', $own)
                ->selectRaw('COUNT(DISTINCT w.collected_tender_id) AS wins, COALESCE(SUM(w.price_sen), 0) AS value_sen')->first();
            if ((int) $mine->wins === 0) {
                return null;
            }
            $others = $this->wins($year)->whereNotIn('w.name_key', $own)->selectRaw('COALESCE(SUM(w.price_sen), 0) AS v')->groupBy('w.name_key');
            $row = DB::query()->fromSub($others, 'x')->selectRaw('COUNT(*) AS total, COALESCE(SUM(v > ?), 0) AS ahead', [(int) $mine->value_sen])->first();

            return ['rank' => (int) $row->ahead + 1, 'of' => (int) $row->total + 1, 'wins' => (int) $mine->wins, 'value_sen' => (int) $mine->value_sen];
        });
    }
}
