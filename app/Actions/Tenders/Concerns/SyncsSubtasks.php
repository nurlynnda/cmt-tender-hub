<?php

namespace App\Actions\Tenders\Concerns;

use App\Models\{TenderDocument, User};

trait SyncsSubtasks
{
    /** After a subtask changes: a task with subtasks is done exactly when all of its subtasks are. */
    private function syncParent(TenderDocument $parent, User $actor): void
    {
        $children = $parent->children()->get(['id', 'is_done']);
        if ($children->isEmpty()) {
            return;
        }

        $allDone = $children->every(fn (TenderDocument $c) => $c->is_done);
        if ($allDone === $parent->is_done) {
            return;
        }

        $parent->forceFill([
            'is_done' => $allDone,
            'done_by' => $allDone ? $actor->id : null,
            'done_at' => $allDone ? now() : null,
        ])->save();
    }
}
