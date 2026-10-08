<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One quotation line. unit_price_sen is the selling price the customer sees, saved as worked out (or as typed). */
class QuotationItem extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    /** Everything a duplicate or revision copies from an item. */
    public const COPIED = ['position', 'title', 'details', 'quantity', 'unit', 'frequency', 'unit_cost_sen', 'margin_bp',
        'unit_price_override_sen', 'unit_price_sen', 'vendor', 'quote_url', 'has_sst', 'sub_items'];

    protected function casts(): array
    {
        return [
            'position' => 'integer', 'quantity' => 'integer', 'unit_price_sen' => 'integer', 'frequency' => 'integer',
            'unit_cost_sen' => 'integer', 'margin_bp' => 'integer', 'unit_price_override_sen' => 'integer',
            'has_sst' => 'boolean', 'sub_items' => 'array',
        ];
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    /** The item as the costing calculator reads it. */
    public function costingLine(): array
    {
        return [
            'quantity' => $this->quantity, 'frequency' => $this->frequency, 'unit_cost_sen' => $this->unit_cost_sen,
            'margin_bp' => $this->margin_bp, 'unit_price_override_sen' => $this->unit_price_override_sen,
            'sub_items' => array_map(fn ($s) => ['quantity' => (int) $s['quantity'], 'unit_cost_sen' => (int) $s['unit_cost_sen']], $this->sub_items ?? []),
        ];
    }
}
