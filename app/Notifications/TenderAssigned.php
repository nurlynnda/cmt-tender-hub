<?php

namespace App\Notifications;

use App\Models\Tender;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class TenderAssigned extends Notification
{
    public function __construct(public Tender $tender, public string $asRole) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'message' => "You were assigned as {$this->asRole} on {$this->tender->wo_number} — ".Str::limit($this->tender->title, 60),
            'tender_id' => $this->tender->id,
        ];
    }
}
