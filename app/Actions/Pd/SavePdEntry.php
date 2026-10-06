<?php

namespace App\Actions\Pd;

use App\Actions\Pd\Concerns\GuardsProject;
use App\Enums\PdEntryType;
use App\Models\{ActivityLog, PdEntry, PdLine, User};
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Adds a document to a line, or updates one when $entry is given. */
final class SavePdEntry
{
    use GuardsProject;

    /** @param array{type:string, number:?string, date:string, amount_sen:int, note:?string} $data */
    public function handle(User $actor, PdLine $line, int $expectedLineVersion, ?PdEntry $entry, array $data): PdEntry
    {
        $type = PdEntryType::tryFrom($data['type']);
        $allowed = $line->pd_group->entryTypes();
        if (! in_array($type, $allowed, true)) {
            $labels = array_map(fn (PdEntryType $t) => $t->label(), $allowed);
            $list = implode(', ', array_slice($labels, 0, -1)).' and '.end($labels);
            throw new InvalidArgumentException("{$line->pd_group->label()} lines take {$list} entries only.");
        }
        if ($data['amount_sen'] <= 0) {
            throw new InvalidArgumentException('The amount must be more than RM 0.00.');
        }
        if ($entry && $entry->pd_line_id !== $line->id) {
            throw new InvalidArgumentException('That document belongs to a different line.');
        }

        return DB::transaction(function () use ($actor, $line, $expectedLineVersion, $entry, $data, $type) {
            $l = $this->lockLine($actor, $line, $expectedLineVersion);
            $values = [
                'type' => $type,
                'number' => trim((string) $data['number']) ?: null,
                'date' => $data['date'],
                'amount_sen' => $data['amount_sen'],
                'note' => trim((string) $data['note']) ?: null,
            ];
            if ($entry) {
                $entry->update($values);
                $saved = $entry;
            } else {
                $saved = $l->entries()->create($values + ['created_by' => $actor->id]);
            }
            $l->forceFill(['updated_by' => $actor->id, 'version' => $l->version + 1])->save();

            ActivityLog::record($l->project->tender, $actor, $entry ? 'pd_entry_updated' : 'pd_entry_added', sprintf(
                '%s %s %s on %s: %s',
                trim($type->label().' '.($values['number'] ?? '')), Money::format($values['amount_sen']),
                $entry ? 'updated' : 'recorded', $l->pd_group->label(), $l->name,
            ));

            return $saved->fresh();
        });
    }
}
