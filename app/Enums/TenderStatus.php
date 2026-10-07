<?php

namespace App\Enums;

use ValueError;

enum TenderStatus: string
{
    case InProgress = 'in_progress';
    case Done = 'done';
    case Awarded = 'awarded';
    case Lost = 'lost';
    case Dropped = 'dropped';

    public function label(): string
    {
        return match ($this) {
            self::InProgress => 'In Progress',
            self::Done => 'Done',
            self::Awarded => 'Awarded',
            self::Lost => 'Lost',
            self::Dropped => 'Dropped',
        };
    }

    public function slug(): string
    {
        return str_replace('_', '-', $this->value);
    }

    public static function fromSlug(string $slug): self
    {
        return self::tryFrom(str_replace('-', '_', $slug))
            ?? throw new ValueError("Unknown tender list: {$slug}");
    }

    public function listTitle(): string
    {
        return $this->label().' Tenders';
    }

    public function listSubtitle(): string
    {
        return match ($this) {
            self::InProgress => 'Tenders currently being worked on',
            self::Done => 'Submitted tenders waiting for a result',
            self::Awarded => 'Tenders won and confirmed',
            self::Lost => 'Tenders lost, cancelled or with no award news',
            self::Dropped => 'Tenders the company decided not to bid for',
        };
    }
}
