<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One published winner of a collected tender (a searchable copy of collected_tenders.winners). */
class CollectedTenderWinner extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['price_sen' => 'integer', 'position' => 'integer'];
    }

    public function tender(): BelongsTo
    {
        return $this->belongsTo(CollectedTender::class, 'collected_tender_id');
    }
}
