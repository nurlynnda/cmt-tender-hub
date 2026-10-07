<?php

namespace App\Import;

use App\Models\{Project, Quotation, Tender, User};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * The made-up records the app shipped with. Removing them before the first real import keeps
 * the Dashboard honest. Find Tenders' collected tenders are never touched.
 *
 * Every pipeline tender is a sample at this point: many sample tenders reuse real WO numbers from the
 * register, so keeping "matching" ones would carry made-up documents, costing and history into real data.
 */
final class SampleData
{
    public const KEEP_EMAILS = ['admin@cmt.test', 'manager@cmt.test'];

    public function preview(): array
    {
        return [
            'sample tenders' => Tender::count(),
            'quotations' => Quotation::count(),
            'sample staff' => $this->staff()->count(),
        ];
    }

    /** Call inside a transaction, before importing. */
    public function remove(): array
    {
        // PD money entries are protected from vanishing with their line, so clear them explicitly first,
        // then the projects (a quotation's project would otherwise block deleting the quotation).
        DB::table('pd_entries')->delete();
        Project::query()->delete();                                      // PD lines cascade

        $tenders = Tender::count();
        Tender::query()->delete();                                       // documents, costing, activity cascade
        $quotations = Quotation::count();
        Quotation::query()->delete();                                    // items, activity cascade
        $notifications = DB::table('notifications')->delete();

        return ['sample tenders' => $tenders, 'quotations' => $quotations, 'notifications' => $notifications];
    }

    /** Call inside the import transaction, after the import, so nothing points at sample staff any more. */
    public function removeStaff(): array
    {
        $deleted = $switchedOff = 0;
        foreach ($this->staff()->get() as $user) {
            try {
                DB::transaction(fn () => $user->delete());               // savepoint: a refused delete undoes only itself
                $deleted++;
            } catch (QueryException) {
                // Still referenced elsewhere (e.g. started a Find Tenders collection): keep the record, switch it off.
                $user->forceFill(['is_active' => false])->save();
                $switchedOff++;
            }
        }

        return ['sample staff (deleted)' => $deleted, 'sample staff (switched off)' => $switchedOff];
    }

    /** Everyone except the kept admin/manager and accounts the import itself created. */
    private function staff(): Builder
    {
        return User::query()->whereNotIn('email', self::KEEP_EMAILS)->where('email', 'not like', '%@import.invalid');
    }
}
