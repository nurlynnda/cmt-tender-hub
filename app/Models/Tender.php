<?php

namespace App\Models;

use App\Costing\CostingCalculator;
use App\Enums\{TenderCategory, TenderMode, TenderStatus, TenderType};
use App\Support\{MalaysiaTime, Money};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, HasOne};

class Tender extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => TenderStatus::class,
            'mode' => TenderMode::class,
            'type' => TenderType::class,
            'category' => TenderCategory::class,
            'wo_date' => 'immutable_date',
            'publish_date' => 'immutable_date',
            'closing_date' => 'immutable_date',
            'briefing_date' => 'immutable_date',
            'has_briefing' => 'boolean',
            'was_cancelled' => 'boolean',
            'estimated_value_sen' => 'integer',
            'submitted_price_sen' => 'integer',
            'winning_price_sen' => 'integer',
            'default_margin_bp' => 'integer',
            'bid_price_override_sen' => 'integer',
            'version' => 'integer',
            'done_at' => 'immutable_datetime',
            'awarded_at' => 'immutable_datetime',
            'lost_at' => 'immutable_datetime',
            'closing_soon_notified_at' => 'immutable_datetime',
            'briefing_notified_at' => 'immutable_datetime',
        ];
    }

    public function pic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function collectedTender(): BelongsTo
    {
        return $this->belongsTo(CollectedTender::class);
    }

    /** The PD, created when the tender is first awarded. */
    public function project(): HasOne
    {
        return $this->hasOne(Project::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(TenderDocument::class)->orderBy('position');
    }

    public function costingLines(): HasMany
    {
        return $this->hasMany(CostingLine::class)->orderBy('position')->with('subItems');
    }

    /** Totals for the saved costing, or null when it has no lines. */
    public function costingSummary(): ?array
    {
        $lines = $this->costingLines;
        if ($lines->isEmpty()) {
            return null;
        }

        return CostingCalculator::summary(
            $lines->map(fn (CostingLine $l) => [
                'quantity' => $l->quantity, 'frequency' => $l->frequency, 'months' => $l->months,
                'project_year' => $l->project_year, 'unit_cost_sen' => $l->unit_cost_sen, 'margin_bp' => $l->margin_bp,
                'sub_items' => $l->subItems->map(fn ($s) => ['quantity' => $s->quantity, 'unit_cost_sen' => $s->unit_cost_sen])->all(),
            ])->all(),
            $this->bid_price_override_sen,
            $this->estimated_value_sen,
        );
    }

    public function hasCosting(): bool
    {
        return ($this->costingSummary()['bid_price_sen'] ?? 0) > 0;
    }

    public function activity(): HasMany
    {
        return $this->hasMany(ActivityLog::class)->latest('id');
    }

    public function scopeWithDocumentCounts(Builder $query): Builder
    {
        return $query->withCount([
            'documents',
            'documents as documents_done_count' => fn (Builder $q) => $q->where('is_done', true),
        ]);
    }

    public function isLocked(): bool
    {
        return $this->status !== TenderStatus::InProgress;
    }

    public function closingState(): ?string
    {
        if ($this->status !== TenderStatus::InProgress || $this->closing_date === null) {
            return null;
        }

        $closing = $this->closing_date->toDateString();
        $today = MalaysiaTime::today();

        if ($closing < $today->toDateString()) {
            return 'overdue';
        }

        return $closing <= $today->addDays(7)->toDateString() ? 'soon' : null;
    }

    public function documentPercent(): int
    {
        $total = $this->documents_count ?? $this->documents()->count();
        $done = $this->documents_done_count ?? $this->documents()->where('is_done', true)->count();

        return $total === 0 ? 0 : (int) round($done * 100 / $total);
    }

    public function companyVariant(): ?string
    {
        return Money::variant($this->submitted_price_sen, $this->estimated_value_sen);
    }

    public function winVariant(): ?string
    {
        return Money::variant($this->winning_price_sen, $this->estimated_value_sen);
    }
}
