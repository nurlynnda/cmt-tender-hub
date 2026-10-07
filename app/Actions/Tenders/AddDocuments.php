<?php

namespace App\Actions\Tenders;

use App\Actions\Tenders\Concerns\GuardsTender;
use App\Enums\TenderStatus;
use App\Models\{ActivityLog, Tender, User};
use Illuminate\Support\Facades\DB;

/** Bulk Add: one document name per line; blanks and names already on the checklist are skipped. */
final class AddDocuments
{
    use GuardsTender;

    /** @return array{0: Tender, 1: int} the fresh tender and how many documents were added */
    public function handle(User $actor, Tender $tender, int $expectedVersion, string $text): array
    {
        return DB::transaction(function () use ($actor, $tender, $expectedVersion, $text) {
            $t = $this->lockForChange($actor, $tender, $expectedVersion);
            $this->requireStatus($t, TenderStatus::InProgress, 'change the checklist of');

            $seen = $t->documents()->pluck('name')->map(fn ($n) => mb_strtolower($n))->flip()->all();
            $names = [];
            foreach (preg_split('/\R/u', $text) as $line) {
                $name = mb_substr(trim(preg_replace('/\s+/u', ' ', $line)), 0, 255);
                if ($name === '' || isset($seen[mb_strtolower($name)])) {
                    continue;
                }
                $seen[mb_strtolower($name)] = true;
                $names[] = $name;
            }
            if ($names === []) {
                return [$t, 0];
            }

            $position = (int) $t->documents()->max('position');
            foreach ($names as $name) {
                $t->documents()->create(['name' => $name, 'position' => ++$position]);
            }
            $t->forceFill(['version' => $t->version + 1])->save();
            ActivityLog::record($t, $actor, 'documents_added', 'Added '.count($names).' documents: '.implode(', ', $names));

            return [$t->fresh(), count($names)];
        });
    }
}
