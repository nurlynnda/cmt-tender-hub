<?php

namespace App\Models;

use App\Pd\PdCalculator;
use App\Support\MalaysiaTime;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/** The PD of an awarded tender. */
class Project extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'approved_margin_bp' => 'integer',
            'project_charge_bp' => 'integer',
            'commission_share_bp' => 'integer',
            'start_date' => 'immutable_date',
            'end_date' => 'immutable_date',
            'closed_at' => 'immutable_datetime',
            'version' => 'integer',
        ];
    }

    public function tender(): BelongsTo
    {
        return $this->belongsTo(Tender::class);
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function projectType(): BelongsTo
    {
        return $this->belongsTo(ProjectType::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PdLine::class)->orderBy('position')->orderBy('id')->with('entries');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function isOpen(): bool
    {
        return $this->closed_at === null;
    }

    /** @see PdCalculator::summary() */
    public function summary(?string $today = null): array
    {
        return PdCalculator::summary(
            $this->lines->map(fn (PdLine $l) => [
                'id' => $l->id,
                'group' => $l->pd_group->value,
                'budget_sen' => $l->budget_sen,
                'scheduled_date' => $l->scheduled_date?->format('Y-m-d'),
                'entries' => $l->entries->map(fn (PdEntry $e) => [
                    'type' => $e->type->value, 'amount_sen' => $e->amount_sen, 'date' => $e->date->format('Y-m-d'),
                ])->all(),
            ])->all(),
            [
                'approved_margin_bp' => $this->approved_margin_bp,
                'project_charge_bp' => $this->project_charge_bp,
                'commission_share_bp' => $this->commission_share_bp,
            ],
            $this->start_date?->format('Y-m-d'),
            $this->end_date?->format('Y-m-d'),
            $today ?? MalaysiaTime::today()->format('Y-m-d'),
        );
    }
}
