<?php

use App\Models\{CollectedTender, CollectedTenderWinner};
use Illuminate\Support\Facades\DB;

it('rebuilds the winners list from every tender, safely re-runnable', function () {
    $a = CollectedTender::factory()->create(['status' => 'closed', 'winners' => [['name' => 'ACME', 'price_sen' => 1], ['name' => 'BETA', 'price_sen' => 2]]]);
    CollectedTender::factory()->create(['status' => 'closed', 'winners' => null]);
    $bad = CollectedTender::factory()->create(['status' => 'closed']);
    DB::table('collected_tenders')->where('id', $bad->id)->update(['winners' => '"not a list"']); // as a legacy bulk insert might leave it
    DB::table('collected_tender_winners')->delete(); // as after the legacy import (bulk insert skips the model)

    $this->artisan('collector:index-winners')->expectsOutputToContain('2 winners from 1 tenders')->assertSuccessful();
    $this->artisan('collector:index-winners')->assertSuccessful();

    expect(CollectedTenderWinner::where('collected_tender_id', $a->id)->count())->toBe(2)->and(CollectedTenderWinner::count())->toBe(2);
});
