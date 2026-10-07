<?php

namespace App\Livewire\Concerns;

use App\Market\MarketReport;
use App\Support\MalaysiaTime;
use Livewire\Attributes\Url;

/** ?year=all|YYYY for the Market pages; a year with no awards (or nonsense) falls back to the current year with a note. */
trait HasMarketYear
{
    #[Url] public string $year = '';

    public ?string $yearNote = null;

    /** @param  list<int>  $years  years that have awards */
    protected function marketYear(array $years): ?int
    {
        if ($this->year === 'all') {
            $this->yearNote = null;

            return null;
        }
        $current = (int) MalaysiaTime::today()->format('Y');
        if ($this->year === '') {
            $this->year = (string) $current;
        }
        $this->yearNote = null;
        if (! ctype_digit($this->year) || ! in_array((int) $this->year, $years, true)) {
            if ($this->year !== (string) $current) {
                $this->yearNote = "That year has no awards — showing {$current}";
            }
            $this->year = (string) $current;
        }

        return (int) $this->year;
    }

    /** Find Tenders closing-date filters matching the figures: the chosen year, or from the first counted year on. */
    protected function yearRange(?int $year): array
    {
        return $year ? ['from' => "{$year}-01-01", 'to' => "{$year}-12-31"] : ['from' => MarketReport::fromYear().'-01-01'];
    }
}
