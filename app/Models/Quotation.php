<?php

namespace App\Models;

use App\Enums\QuotationStatus;
use App\Support\MalaysiaTime;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, HasOne};

class Quotation extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => QuotationStatus::class,
            'quote_date' => 'immutable_date',
            'validity_days' => 'integer',
            'show_signature' => 'boolean',
            'show_stamp' => 'boolean',
            'sst_bp' => 'integer',
            'letterhead' => 'array',
            'sent_at' => 'immutable_datetime',
            'accepted_at' => 'immutable_datetime',
            'rejected_at' => 'immutable_datetime',
            'version' => 'integer',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class)->orderBy('position')->orderBy('id');
    }

    public function preparer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function revisionOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'revision_of_id');
    }

    public function project(): HasOne
    {
        return $this->hasOne(Project::class);
    }

    public function activity(): HasMany
    {
        return $this->hasMany(ActivityLog::class)->latest('id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function validUntil(): CarbonImmutable
    {
        return $this->quote_date->addDays($this->validity_days);
    }

    /** Sent and past its valid-until date (Malaysia calendar day). */
    public function isExpired(?CarbonImmutable $today = null): bool
    {
        $today ??= MalaysiaTime::today();

        return $this->status === QuotationStatus::Sent && $today->format('Y-m-d') > $this->validUntil()->format('Y-m-d');
    }

    public function displayStatus(): string
    {
        return $this->isExpired() ? 'expired' : $this->status->value;
    }

    public function displayLabel(): string
    {
        return $this->isExpired() ? 'Expired' : $this->status->label();
    }

    public function isDraft(): bool
    {
        return $this->status === QuotationStatus::Draft;
    }
}
