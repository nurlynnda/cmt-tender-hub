<?php

namespace App\Models;

use App\Collector\SourceName;
use App\Support\MalaysiaTime;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CollectedTender extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'events' => 'array', 'winners' => 'array', 'raw' => 'array', 'field_updated_at' => 'array',
            'advertised_date' => 'immutable_date', 'closing_date' => 'immutable_date',
            'scraped_at' => 'immutable_datetime', 'indicative_price_sen' => 'integer',
        ];
    }

    public function sources(): HasMany
    {
        return $this->hasMany(CollectedTenderSource::class)->orderBy('source');
    }

    public function fieldCodes(): HasMany
    {
        return $this->hasMany(CollectedTenderFieldCode::class)->orderBy('code');
    }

    public function pipelineTenders(): HasMany
    {
        return $this->hasMany(Tender::class)->orderBy('id');
    }

    public function firstPipelineTender(): ?Tender
    {
        return $this->relationLoaded('pipelineTenders') ? $this->pipelineTenders->first() : $this->pipelineTenders()->first();
    }

    public function daysLeft(): ?int
    {
        if ($this->closing_date === null) {
            return null;
        }
        $closing = CarbonImmutable::parse($this->closing_date->toDateString(), MalaysiaTime::TZ);

        return (int) MalaysiaTime::today()->diffInDays($closing, false);
    }

    /** @return list<string> */
    public function sourceNames(): array
    {
        return $this->sources->map(fn ($s) => SourceName::label($s->source))->all();
    }
}
