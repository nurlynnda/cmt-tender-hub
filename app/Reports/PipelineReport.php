<?php

namespace App\Reports;

use App\Enums\TenderStatus;
use App\Models\{Tender, User};
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/** Tender figures for the Dashboard and Status pages, from one grouped query. */
final class PipelineReport
{
    private const STATUSES = ['in_progress', 'done', 'awarded', 'lost', 'dropped'];

    public function build(ReportPeriod $period, CarbonImmutable $today): array
    {
        $rows = Tender::query()
            ->when(! $period->isAllTime(), fn ($q) => $q->whereBetween('wo_date', [$period->from, $period->to]))
            ->groupBy('pic_id', 'mode', 'status', 'was_cancelled')
            ->select('pic_id', 'mode', 'status', 'was_cancelled')
            ->selectRaw('COUNT(*) AS n')
            // Bid value: estimated value while in progress, otherwise the submitted price; dropped and cancelled tenders count 0.
            ->selectRaw("SUM(CASE WHEN status = 'dropped' THEN 0 WHEN status = 'in_progress' THEN COALESCE(estimated_value_sen, 0)
                WHEN was_cancelled = 1 THEN 0 ELSE COALESCE(submitted_price_sen, 0) END) AS bid")
            ->selectRaw("SUM(CASE WHEN status = 'awarded' THEN COALESCE(submitted_price_sen, 0) ELSE 0 END) AS won_value")
            ->selectRaw("SUM(CASE WHEN was_cancelled = 1 OR status = 'dropped' THEN 0
                WHEN status = 'in_progress' THEN estimated_value_sen IS NULL ELSE submitted_price_sen IS NULL END) AS no_value")
            ->toBase()->get();

        $all = self::blank();
        $modes = ['EP' => self::blank(), 'NON_EP' => self::blank()];
        $byPic = [];
        foreach ($rows as $r) {
            $byPic[$r->pic_id] ??= self::blank();
            foreach ([&$all, &$modes[$r->mode], &$byPic[$r->pic_id]] as &$bucket) {
                $bucket[$r->status] += (int) $r->n;
                $bucket['cancelled'] += $r->was_cancelled ? (int) $r->n : 0;
                $bucket['total'] += (int) $r->n;
                $bucket['bid_value_sen'] += (int) $r->bid;
                $bucket['won_value_sen'] += (int) $r->won_value;
                $bucket['without_value'] += (int) $r->no_value;
            }
            unset($bucket);
        }

        $decided = $all['awarded'] + $all['lost'] - $all['cancelled'];

        return [
            'counts' => array_intersect_key($all, array_flip([...self::STATUSES, 'cancelled', 'total'])),
            'due_this_week' => Tender::where('status', TenderStatus::InProgress)
                ->whereBetween('closing_date', [$today->format('Y-m-d'), $today->addDays(7)->format('Y-m-d')])->count(),
            'won' => $all['awarded'],
            'decided' => $decided,
            'win_rate_bp' => self::rate($all['awarded'], $decided),
            'bid_value_sen' => $all['bid_value_sen'],
            'won_value_sen' => $all['won_value_sen'],
            'without_value' => $all['without_value'],
            'modes' => array_map(fn ($m) => array_intersect_key($m, array_flip([...self::STATUSES, 'total', 'bid_value_sen'])), $modes),
            'pics' => $this->picRows($byPic, $all['bid_value_sen']),
            'totals' => self::row(null, 'Total', '', $all, $all['bid_value_sen']),
        ];
    }

    /** In Progress tenders closing soonest (today onwards), whatever the period. */
    public function deadlines(CarbonImmutable $today, int $limit = 8): Collection
    {
        return Tender::with('pic')->where('status', TenderStatus::InProgress)
            ->where('closing_date', '>=', $today->format('Y-m-d'))
            ->orderBy('closing_date')->orderBy('id')->limit($limit)->get();
    }

    /** Every active person (zero rows included), plus switched-off people who have tenders in the period. */
    private function picRows(array $byPic, int $grandBid): array
    {
        $users = User::query()->where('is_active', true)->orWhereIn('id', array_keys($byPic))->get(['id', 'name', 'is_active']);
        $rows = $users->map(fn (User $u) => self::row($u->id, $u->name, $u->initials(), $byPic[$u->id] ?? self::blank(), $grandBid))->all();
        usort($rows, fn ($a, $b) => [$b['bid_value_sen'], $a['name']] <=> [$a['bid_value_sen'], $b['name']]);

        return $rows;
    }

    private static function row(?int $id, string $name, string $initials, array $b, int $grandBid): array
    {
        $decided = $b['awarded'] + $b['lost'] - $b['cancelled'];

        return [
            'user_id' => $id, 'name' => $name, 'initials' => $initials,
            'total' => $b['total'], 'in_progress' => $b['in_progress'], 'done' => $b['done'], 'awarded' => $b['awarded'], 'lost' => $b['lost'], 'dropped' => $b['dropped'],
            'won' => $b['awarded'], 'decided' => $decided, 'win_rate_bp' => self::rate($b['awarded'], $decided),
            'bid_value_sen' => $b['bid_value_sen'], 'won_value_sen' => $b['won_value_sen'],
            'share_bp' => $grandBid > 0 ? (int) round($b['bid_value_sen'] * 10000 / $grandBid) : 0,
        ];
    }

    private static function blank(): array
    {
        return ['in_progress' => 0, 'done' => 0, 'awarded' => 0, 'lost' => 0, 'dropped' => 0, 'cancelled' => 0, 'total' => 0,
            'bid_value_sen' => 0, 'won_value_sen' => 0, 'without_value' => 0];
    }

    private static function rate(int $won, int $decided): ?int
    {
        return $decided > 0 ? (int) round($won * 10000 / $decided) : null;
    }
}
