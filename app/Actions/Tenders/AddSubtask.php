<?php

namespace App\Actions\Tenders;

use App\Actions\Tenders\Concerns\{GuardsTender, SyncsSubtasks};
use App\Enums\TenderStatus;
use App\Models\{ActivityLog, Tender, User};
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Adds a subtask under a top-level task. Subtasks go one level deep; the task is done only when all its subtasks are. */
final class AddSubtask
{
    use GuardsTender, SyncsSubtasks;

    public function handle(User $actor, Tender $tender, int $expectedVersion, int $parentId, string $name): Tender
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 255) {
            throw new InvalidArgumentException('Subtask name must be 1–255 characters.');
        }

        return DB::transaction(function () use ($actor, $tender, $expectedVersion, $parentId, $name) {
            $t = $this->lockForChange($actor, $tender, $expectedVersion);
            $this->requireStatus($t, TenderStatus::InProgress, 'change the checklist of');

            $parent = $t->documents()->whereNull('parent_id')->findOrFail($parentId);
            $parent->children()->create([
                'tender_id' => $t->id,
                'name' => $name,
                'position' => (int) $t->documents()->max('position') + 1,
            ]);
            $this->syncParent($parent->fresh(), $actor); // a finished task with a new, unfinished subtask is open again

            $t->forceFill(['version' => $t->version + 1])->save();
            ActivityLog::record($t, $actor, 'subtask_added', "Added subtask: {$name} (under {$parent->name})");

            return $t->fresh();
        });
    }
}
