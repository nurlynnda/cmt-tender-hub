<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenderDocument extends Model
{
    use HasFactory;

    public const STANDARD = [
        'Borang ISI (Tender Form)',
        'Pricing Schedule',
        'Company Profile / SSM Registration',
        'Technical Proposal',
        'Bid Bond / Bank Guarantee',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_done' => 'boolean', 'done_at' => 'immutable_datetime', 'position' => 'integer'];
    }

    public function tender(): BelongsTo
    {
        return $this->belongsTo(Tender::class);
    }

    public function doneBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'done_by');
    }
}
