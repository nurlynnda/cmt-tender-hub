<?php

namespace App\Queries;

use App\Enums\{TenderCategory, TenderMode, TenderStatus};
use App\Models\{Tender, User};
use Illuminate\Database\Eloquent\Builder;

final class TenderListQuery
{
    public static function build(TenderStatus $status, User $viewer, array $filters): Builder
    {
        $query = Tender::query()
            ->where('status', $status)
            ->with(['pic', 'owner'])
            ->withDocumentCounts()
            ->when($status === TenderStatus::Done, fn (Builder $q) => $q->with('costingLines')) // for the Gross column
            ->when($status === TenderStatus::Awarded, fn (Builder $q) => $q->with('project.lines')); // for Actual GP

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
            $query->where(fn (Builder $q) => $q
                ->where('wo_number', 'like', $like)
                ->orWhere('tender_code', 'like', $like)
                ->orWhere('title', 'like', $like)
                ->orWhere('client', 'like', $like));
        }

        if (! empty($filters['mine'])) {
            $query->where(fn (Builder $q) => $q->where('pic_id', $viewer->id)->orWhere('owner_id', $viewer->id));
        }
        if ($mode = TenderMode::tryFrom((string) ($filters['mode'] ?? ''))) {
            $query->where('mode', $mode);
        }
        if (ctype_digit((string) ($filters['pic'] ?? ''))) {
            $query->where('pic_id', (int) $filters['pic']);
        }
        if ($category = TenderCategory::tryFrom((string) ($filters['category'] ?? ''))) {
            $query->where('category', $category);
        }
        foreach (['from' => '>=', 'to' => '<='] as $key => $operator) {
            $date = (string) ($filters[$key] ?? '');
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $query->where('closing_date', $operator, $date);
            }
        }

        $direction = $status === TenderStatus::InProgress ? 'asc' : 'desc';

        return $query->orderBy('closing_date', $direction)->orderBy('id', $direction);
    }
}
