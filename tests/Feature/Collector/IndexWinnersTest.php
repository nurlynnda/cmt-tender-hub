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

it('prepares the Market Insights figures after rebuilding', function () {
    CollectedTender::factory()->create(['status' => 'closed', 'closing_date' => now()->format('Y').'-02-01', 'winners' => [['name' => 'ACME', 'price_sen' => 5]]]);

    $this->artisan('collector:index-winners')->expectsOutputToContain('Market Insights figures prepared')->assertSuccessful();

    DB::enableQueryLog();
    app(\App\Market\MarketReport::class)->summary((int) \App\Support\MalaysiaTime::today()->format('Y'));
    expect(collect(DB::getQueryLog())->pluck('query')->filter(fn ($q) => str_contains($q, 'join `collected_tenders`'))->all())->toBe([]);
});
