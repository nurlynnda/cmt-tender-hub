<?php

namespace App\Notifications;

use App\Models\Tender;
use App\Support\MalaysiaTime;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\Notification;

class TenderClosingSoon extends Notification
{
    public function __construct(public Tender $tender) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $closing = CarbonImmutable::parse($this->tender->closing_date->toDateString(), MalaysiaTime::TZ);
        $days = (int) MalaysiaTime::today()->diffInDays($closing);
        $when = match ($days) {
            0 => 'today',
            1 => 'tomorrow',
            default => "in {$days} days",
        };

        return [
            'message' => "{$this->tender->wo_number} closes {$when} ({$this->tender->closing_date->format('d M Y')})",
            'tender_id' => $this->tender->id,
        ];
    }
}
