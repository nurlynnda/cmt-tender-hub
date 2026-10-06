<?php

namespace App\Actions\Tenders;

use App\Actions\Tenders\Concerns\GuardsTender;
use App\Enums\TenderStatus;
use App\Models\{ActivityLog, Tender, User};
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class AddDocument
{
    use GuardsTender;

    public function handle(User $actor, Tender $tender, int $expectedVersion, string $name): Tender
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 255) {
            throw new InvalidArgumentException('Document name must be 1–255 characters.');
        }

        return DB::transaction(function () use ($actor, $tender, $expectedVersion, $name) {
            $t = $this->lockForChange($actor, $tender, $expectedVersion);
            $this->requireStatus($t, TenderStatus::InProgress, 'change the checklist of');

            $t->documents()->create([
                'name' => $name,
                'position' => (int) $t->documents()->max('position') + 1,
            ]);
            $t->forceFill(['version' => $t->version + 1])->save();
            ActivityLog::record($t, $actor, 'document_added', "Added document: {$name}");

            return $t->fresh();
        });
    }
}
