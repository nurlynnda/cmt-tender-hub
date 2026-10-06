<?php

namespace App\Models;

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
}
