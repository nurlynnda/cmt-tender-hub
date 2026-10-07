<?php

namespace App\Reports;

use Carbon\CarbonImmutable;

/** The period a report covers, by WO date. Built from the page address; anything unreadable means All time. */
final class ReportPeriod
{
    private function __construct(
        public readonly string $kind,
        public readonly ?string $from,
        public readonly ?string $to,
        public readonly bool $invalid = false,
    ) {}

    public static function fromInput(array $input, CarbonImmutable $today): self
    {
        $kind = (string) ($input['period'] ?? 'all');
        $lastMonth = $today->startOfMonth()->subMonth(); // from the 1st, so the 31st never overflows

        return match ($kind) {
            'all', '' => new self('all', null, null),
            'this_month' => self::range('this_month', $today->startOfMonth(), $today->endOfMonth()),
            'last_month' => self::range('last_month', $lastMonth, $lastMonth->endOfMonth()),
            'this_year' => self::range('this_year', $today->startOfYear(), $today->endOfYear()),
            'month' => self::month((string) ($input['month'] ?? '')),
            'custom' => self::custom((string) ($input['from'] ?? ''), (string) ($input['to'] ?? '')),
            default => self::invalid(),
        };
    }

    private static function range(string $kind, CarbonImmutable $from, CarbonImmutable $to): self
    {
        return new self($kind, $from->format('Y-m-d'), $to->format('Y-m-d'));
    }

    private static function month(string $month): self
    {
        if ($month === '') {
            return new self('all', null, null); // not chosen yet: no warning
        }
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            return self::invalid();
        }
        $start = CarbonImmutable::parse($month.'-01');

        return self::range('month', $start, $start->endOfMonth());
    }

    private static function custom(string $from, string $to): self
    {
        if ($from === '' || $to === '') {
            return new self('all', null, null); // still being filled in: no warning
        }
        if (! self::isDate($from) || ! self::isDate($to) || $to < $from) {
            return self::invalid();
        }

        return new self('custom', $from, $to);
    }

    /** A real calendar date in Y-m-d form (rejects 2026-02-30). */
    private static function isDate(string $d): bool
    {
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $d, $m)) {
            return false;
        }

        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }

    private static function invalid(): self
    {
        return new self('all', null, null, true);
    }

    public function isAllTime(): bool
    {
        return $this->from === null;
    }

    /** Filters for the tender lists, so a drill-down shows the same tenders as the number clicked. */
    public function listFilters(): array
    {
        return $this->isAllTime() ? [] : ['wo_from' => $this->from, 'wo_to' => $this->to];
    }

    /** The page-address settings that recreate this period on another report page. */
    public function addressParams(): array
    {
        return match ($this->kind) {
            'this_month', 'last_month', 'this_year' => ['period' => $this->kind],
            'month' => ['period' => 'month', 'month' => substr($this->from, 0, 7)],
            'custom' => ['period' => 'custom', 'from' => $this->from, 'to' => $this->to],
            default => [],
        };
    }

    public function contains(string $date): bool
    {
        return $this->isAllTime() || ($date >= $this->from && $date <= $this->to);
    }

    public function label(): string
    {
        $from = $this->from ? CarbonImmutable::parse($this->from) : null;

        return match ($this->kind) {
            'this_month' => 'This month ('.$from->format('M Y').')',
            'last_month' => 'Last month ('.$from->format('M Y').')',
            'this_year' => 'This year ('.$from->format('Y').')',
            'month' => $from->format('M Y'),
            'custom' => $from->format('d M Y').' – '.CarbonImmutable::parse($this->to)->format('d M Y'),
            default => 'All time',
        };
    }
}
