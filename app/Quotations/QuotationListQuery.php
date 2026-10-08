<?php

namespace App\Quotations;

use App\Enums\QuotationStatus;
use App\Models\{Quotation, User};
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

final class QuotationListQuery
{
    private const EXPIRED = 'DATE_ADD(quote_date, INTERVAL validity_days DAY) < ?';

    /**
     * $status: all, draft, sent (not yet expired), expired, accepted, rejected, revised.
     * $sort: date_desc (newest first, the default) or date_asc.
     */
    public static function build(string $status, string $search, bool $mine, User $viewer, CarbonImmutable $today, string $sort = 'date_desc'): Builder
    {
        $q = self::scope(Quotation::query()->with(['items', 'preparer']), $search, $mine, $viewer);
        $day = $today->format('Y-m-d');

        match ($status) {
            'expired' => $q->where('status', QuotationStatus::Sent)->whereRaw(self::EXPIRED, [$day]),
            'sent' => $q->where('status', QuotationStatus::Sent)->whereRaw('NOT ('.self::EXPIRED.')', [$day]),
            'draft', 'accepted', 'rejected', 'revised' => $q->where('status', $status),
            default => null,
        };

        $dir = $sort === 'date_asc' ? 'asc' : 'desc';

        return $q->orderBy('quote_date', $dir)->orderBy('id', $dir);
    }

    /** How many quotations each status button would show, for the same search / "mine" filter as the list. */
    public static function counts(string $search, bool $mine, User $viewer, CarbonImmutable $today): array
    {
        $day = $today->format('Y-m-d');
        $row = self::scope(Quotation::query(), $search, $mine, $viewer)->toBase()
            ->selectRaw('COUNT(*) AS all_n')
            ->selectRaw("SUM(status = 'draft') AS draft, SUM(status = 'accepted') AS accepted, SUM(status = 'rejected') AS rejected, SUM(status = 'revised') AS revised")
            ->selectRaw("SUM(status = 'sent' AND ".self::EXPIRED.') AS expired', [$day])
            ->selectRaw("SUM(status = 'sent' AND NOT (".self::EXPIRED.')) AS sent', [$day])
            ->first();

        $counts = ['all' => (int) $row->all_n];
        foreach (['draft', 'sent', 'expired', 'accepted', 'rejected', 'revised'] as $k) {
            $counts[$k] = (int) $row->{$k};
        }

        return $counts;
    }

    /** The search box and "My quotations", shared by the list and its counts. */
    private static function scope(Builder $q, string $search, bool $mine, User $viewer): Builder
    {
        $search = trim($search);
        if ($search !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
            $q->where(fn (Builder $w) => $w->where('number', 'like', $like)->orWhere('customer_name', 'like', $like)->orWhere('subject', 'like', $like));
        }
        if ($mine) {
            $q->where('prepared_by', $viewer->id);
        }

        return $q;
    }
}
