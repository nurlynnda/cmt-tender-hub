<?php

namespace App\Models;

use App\Enums\PdEntryType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One document on a PD line: PR, PO, invoice, payment or receipt. */
class PdEntry extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['type' => PdEntryType::class, 'date' => 'immutable_date', 'amount_sen' => 'integer'];
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(PdLine::class, 'pd_line_id');
    }
}
