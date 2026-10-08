<?php

namespace App\Actions\Pd;

use App\Actions\Pd\Concerns\GuardsProject;
use App\Models\{PdEntry, User};
use App\Support\Money;
use Illuminate\Support\Facades\DB;

final class RemovePdEntry
{
    use GuardsProject;

    public function handle(User $actor, PdEntry $entry, int $expectedLineVersion): void
    {
        DB::transaction(function () use ($actor, $entry, $expectedLineVersion) {
            $l = $this->lockLine($actor, $entry->line, $expectedLineVersion);
            $entry->delete();
            $l->forceFill(['updated_by' => $actor->id, 'version' => $l->version + 1])->save();
            $l->project->logActivity($actor, 'pd_entry_removed', sprintf(
                '%s %s removed from %s: %s',
                trim($entry->type->label().' '.$entry->number), Money::format($entry->amount_sen), $l->pd_group->label(), $l->name,
            ));
        });
    }
}
