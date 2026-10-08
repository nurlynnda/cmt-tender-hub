<?php

namespace App\Notifications;

use App\Models\Tender;
use Illuminate\Notifications\Notification;

class BriefingTomorrow extends Notification
{
    public function __construct(public Tender $tender) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'message' => "Briefing for {$this->tender->wo_number} is tomorrow ({$this->tender->briefing_date->format('d M Y')})",
            'tender_id' => $this->tender->id,
        ];
    }
}
