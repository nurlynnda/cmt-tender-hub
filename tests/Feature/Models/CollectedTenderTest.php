<?php

use App\Collector\SourceName;
use App\Models\{CollectedTender, Tender};
use Carbon\CarbonImmutable;

it('stores a collected tender with JSON fields, sources and field codes', function () {
    $t = CollectedTender::factory()->forSource('span', '188')->create([
        'winners' => [['name' => 'ACME', 'price_sen' => 100]],
        'events' => [['label' => 'Lawatan Tapak', 'date' => '2026-07-10', 'address' => null]],
    ]);
    $t->fieldCodes()->create(['code' => 'E05']);

    $fresh = $t->fresh();
    expect($fresh->winners)->toBe([['name' => 'ACME', 'price_sen' => 100]])
        ->and($fresh->sources->pluck('source')->all())->toBe(['span'])
        ->and($fresh->fieldCodes->pluck('code')->all())->toBe(['E05'])
        ->and($fresh->sourceNames())->toBe(['SPAN']);
});

it('counts days left by Malaysia date', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-06 16:30', 'UTC')); // 7 Oct MYT

    expect(CollectedTender::factory()->make(['closing_date' => '2026-10-10'])->daysLeft())->toBe(3)
        ->and(CollectedTender::factory()->make(['closing_date' => null])->daysLeft())->toBeNull();
});

it('links pipeline tenders back to the collected tender', function () {
    $c = CollectedTender::factory()->create();
    $p = Tender::factory()->create(['collected_tender_id' => $c->id]);

    expect($c->firstPipelineTender()->is($p))->toBeTrue()
        ->and($p->collectedTender->is($c))->toBeTrue();
});

it('labels sources for display', function () {
    expect(SourceName::label('myprocurement'))->toBe('MyProcurement')
        ->and(SourceName::label('kwsp'))->toBe('KWSP')
        ->and(SourceName::label('other'))->toBe('Other');
});
