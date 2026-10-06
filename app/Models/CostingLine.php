<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class CostingLine extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'quantity' => 'integer',
            'months' => 'integer',
            'project_year' => 'integer',
            'unit_cost_sen' => 'integer',
            'margin_bp' => 'integer',
            'pd_group' => \App\Enums\PdGroup::class,
        ];
    }

    public function tender(): BelongsTo
    {
        return $this->belongsTo(Tender::class);
    }

    public function subItems(): HasMany
    {
        return $this->hasMany(CostingSubItem::class)->orderBy('position');
    }
}
