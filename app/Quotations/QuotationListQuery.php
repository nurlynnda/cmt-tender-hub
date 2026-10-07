<?php

namespace App\Quotations;

use App\Enums\QuotationStatus;
use App\Models\{Quotation, User};
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

final class QuotationListQuery
{
    /** $status: all, draft, sent (not yet expired), expired, accepted, rejected, revised. */
    public static function build(string $status, string $search, bool $mine, User $viewer, CarbonImmutable $today): Builder
    {
        $q = Quotation::query()->with(['items', 'preparer'])->orderByDesc('quote_date')->orderByDesc('id');
        $expired = 'DATE_ADD(quote_date, INTERVAL validity_days DAY) < ?';
        $day = $today->format('Y-m-d');

        match ($status) {
            'expired' => $q->where('status', QuotationStatus::Sent)->whereRaw($expired, [$day]),
            'sent' => $q->where('status', QuotationStatus::Sent)->whereRaw("NOT ({$expired})", [$day]),
            'draft', 'accepted', 'rejected', 'revised' => $q->where('status', $status),
            default => null,
        };

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
