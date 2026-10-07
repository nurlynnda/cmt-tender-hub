<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['created_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        // Append-only: refuse any update.
        static::updating(fn () => false);
    }

    /** History belongs to a tender or a quotation. */
    public static function record(Tender|Quotation $subject, ?User $user, string $event, string $description): self
    {
        return static::create([
            'tender_id' => $subject instanceof Tender ? $subject->id : null,
            'quotation_id' => $subject instanceof Quotation ? $subject->id : null,
            'user_id' => $user?->id,
            'event' => $event,
            'description' => $description,
        ]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
