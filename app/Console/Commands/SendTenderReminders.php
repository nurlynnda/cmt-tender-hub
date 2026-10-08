<?php

namespace App\Console\Commands;

use App\Enums\TenderStatus;
use App\Models\Tender;
use App\Notifications\{BriefingTomorrow, TenderClosingSoon};
use App\Support\MalaysiaTime;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class SendTenderReminders extends Command
{
    protected $signature = 'tenders:send-reminders';

    protected $description = 'Send "closes in 3 days" and "briefing tomorrow" notifications (once per tender)';

    public function handle(): int
    {
        $today = MalaysiaTime::today();

        $closing = Tender::with(['pic', 'owner'])
            ->where('status', TenderStatus::InProgress)
            ->whereNull('closing_soon_notified_at')
            ->whereBetween('closing_date', [$today->toDateString(), $today->addDays(3)->toDateString()])
            ->get();
        foreach ($closing as $tender) {
            $this->recipients($tender)->each->notify(new TenderClosingSoon($tender));
            $tender->forceFill(['closing_soon_notified_at' => now()])->saveQuietly();
        }

        $briefings = Tender::with(['pic', 'owner'])
            ->where('status', TenderStatus::InProgress)
            ->where('has_briefing', true)
            ->whereNull('briefing_notified_at')
            ->where('briefing_date', $today->addDay()->toDateString())
            ->get();
        foreach ($briefings as $tender) {
            $this->recipients($tender)->each->notify(new BriefingTomorrow($tender));
            $tender->forceFill(['briefing_notified_at' => now()])->saveQuietly();
        }

        $this->info("Closing reminders: {$closing->count()}, briefing reminders: {$briefings->count()}");

        return self::SUCCESS;
    }

    private function recipients(Tender $tender): Collection
    {
        return collect([$tender->pic, $tender->owner])
            ->filter()
            ->unique('id')
            ->filter(fn ($user) => $user->is_active)
            ->values();
    }
}
