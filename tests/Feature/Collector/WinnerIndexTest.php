<?php

use App\Models\{CollectedTender, CollectedTenderWinner};

it('keeps one winner row per published winner, refreshed when the winners change', function () {
    $t = CollectedTender::factory()->create(['status' => 'closed', 'winners' => [
        ['name' => 'ACME SDN. BHD.', 'price_sen' => 1000], ['name' => '  ', 'price_sen' => 5], ['name' => 'Beta Sdn Bhd', 'price_sen' => null],
    ]]);

    expect($t->winnerRows()->get(['name', 'name_key', 'price_sen', 'position'])->toArray())->toBe([
        ['name' => 'ACME SDN. BHD.', 'name_key' => 'ACME SDN BHD', 'price_sen' => 1000, 'position' => 1],
        ['name' => 'Beta Sdn Bhd', 'name_key' => 'BETA SDN BHD', 'price_sen' => null, 'position' => 2],
    ]);

    $t->update(['winners' => [['name' => 'ACME SDN BHD', 'price_sen' => 2000]]]);
    expect($t->winnerRows()->pluck('price_sen')->all())->toBe([2000]);

    $t->update(['winners' => null]);
    expect(CollectedTenderWinner::count())->toBe(0);
});

it('does not touch winner rows when an unrelated field changes', function () {
    $t = CollectedTender::factory()->create(['status' => 'closed', 'winners' => [['name' => 'ACME', 'price_sen' => 1]]]);
    $id = $t->winnerRows()->value('id');

    $t->update(['title' => 'NEW TITLE']);
    expect($t->winnerRows()->value('id'))->toBe($id);
});
