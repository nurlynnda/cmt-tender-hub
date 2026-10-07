<?php

use App\Livewire\FindTenders;
use App\Models\{CollectedTender, CollectionRun, Tender, User};
use App\Queries\CollectedTenderQuery;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

function refs(array $filters): array
{
    return CollectedTenderQuery::build($filters)->pluck('reference_no')->all();
}

it('shows open tenders closing soonest first by default, undated last', function () {
    CollectedTender::factory()->create(['reference_no' => 'B', 'closing_date' => '2026-12-01']);
    CollectedTender::factory()->create(['reference_no' => 'A', 'closing_date' => '2026-11-01']);
    CollectedTender::factory()->create(['reference_no' => 'N', 'closing_date' => null]);
    CollectedTender::factory()->create(['reference_no' => 'C', 'status' => 'closed']);

    expect(refs([]))->toBe(['A', 'B', 'N'])
        ->and(refs(['status' => 'all']))->toContain('C');
});

it('filters by source, type, ministry, field codes and closing range', function () {
    $span = CollectedTender::factory()->forSource('span')->create(['reference_no' => 'S', 'procurement_type' => 'tender', 'ministry' => 'KKM', 'closing_date' => '2026-11-10']);
    $span->fieldCodes()->create(['code' => '210103']);
    CollectedTender::factory()->forSource('myprocurement')->create(['reference_no' => 'M', 'closing_date' => '2026-12-20']);

    expect(refs(['source' => 'span']))->toBe(['S'])
        ->and(refs(['type' => 'tender']))->toBe(['S'])
        ->and(refs(['ministry' => 'KKM']))->toBe(['S'])
        ->and(refs(['codes' => 'E05, 210103']))->toBe(['S'])
        ->and(refs(['from' => '2026-12-01', 'to' => '2026-12-31']))->toBe(['M'])
        ->and(refs(['type' => 'bogus', 'from' => 'garbage', 'status' => 'weird']))->toBe(['S', 'M']);
});

it('renders the list with sources, days left, price and the registered badge', function () {
    $c = CollectedTender::factory()->forSource('span')->create(['reference_no' => 'SPAN/1', 'indicative_price_sen' => 2880000]);
    Tender::factory()->create(['collected_tender_id' => $c->id, 'wo_number' => '200-07102026-001']);

    $this->get('/find-tenders')->assertOk()
        ->assertSee('Find Tenders')
        ->assertSee('SPAN/1')->assertSee('SPAN')
        ->assertSee('RM 28,800.00')
        ->assertSee('10 days left')
        ->assertSee('Registered as WO 200-07102026-001');
});

it('shows the latest run on the status line, including failures', function () {
    CollectionRun::create(['trigger' => 'scheduled', 'scope' => 'daily', 'status' => 'partial', 'started_at' => now(), 'finished_at' => now(),
        'results' => ['myprocurement' => ['count' => 1523, 'error' => null], 'span' => ['count' => 0, 'error' => 'SPAN is down']]]);

    Livewire::test(FindTenders::class)
        ->assertSee('MyProcurement 1,523')
        ->assertSee('SPAN failed: SPAN is down');
});

it('shows Collect now only to managers and admins, and starts an open-tenders run', function () {
    Queue::fake();
    Livewire::test(FindTenders::class)->assertDontSee('Collect now')->call('collectNow')->assertForbidden();

    Livewire::actingAs(User::factory()->manager()->create())->test(FindTenders::class)
        ->assertSee('Collect now')
        ->call('collectNow')
        ->assertSee('Collecting now');

    expect(CollectionRun::sole()->scope)->toBe('open');
});

it('tells the user when a collection is already running', function () {
    CollectionRun::create(['trigger' => 'scheduled', 'scope' => 'daily', 'status' => 'running', 'started_at' => now()]);

    Livewire::actingAs(User::factory()->admin()->create())->test(FindTenders::class)
        ->call('collectNow')
        ->assertSee('Already collecting');
});

it('appears in the sidebar for everyone', function () {
    $this->get('/tenders/in-progress')->assertSee('Find Tenders');
});

it('counts only the filters changed from their defaults, and shows phone cards', function () {
    $this->actingAs(\App\Models\User::factory()->create());
    $c = \Livewire\Livewire::test(\App\Livewire\FindTenders::class);
    expect($c->instance()->filterCount())->toBe(0);
    $c->assertSeeHtml('x-data="{ open: false }"');

    $c->set('status', 'all')->set('source', 'span')->set('codes', '210103');
    expect($c->instance()->filterCount())->toBe(3);
    $c->assertSeeHtml('data-filter-count="3"')->assertSeeHtml('data-resizable="find-tenders"');
});

it('sorts by closing date both ways when asked, undated tenders always last', function () {
    CollectedTender::factory()->create(['reference_no' => 'B', 'closing_date' => '2026-12-01']);
    CollectedTender::factory()->create(['reference_no' => 'A', 'closing_date' => '2026-11-01']);
    CollectedTender::factory()->create(['reference_no' => 'N', 'closing_date' => null]);
    CollectedTender::factory()->create(['reference_no' => 'C', 'closing_date' => '2026-10-01', 'status' => 'closed']);

    expect(refs(['status' => 'all', 'sort' => 'closing_asc']))->toBe(['C', 'A', 'B', 'N'])
        ->and(refs(['status' => 'all', 'sort' => 'closing_desc']))->toBe(['B', 'A', 'C', 'N'])
        ->and(refs(['sort' => 'closing_desc']))->toBe(['B', 'A', 'N'])
        ->and(refs(['sort' => 'nonsense']))->toBe(['A', 'B', 'N']);   // unknown → the usual order
});

it('flips the closing-date sort when the Closing heading is clicked, and keeps it in the address', function () {
    CollectedTender::factory()->create(['reference_no' => 'EARLY-REF', 'closing_date' => '2026-11-01']);
    CollectedTender::factory()->create(['reference_no' => 'LATE-REF', 'closing_date' => '2026-12-01']);

    Livewire::test(FindTenders::class)
        ->assertSeeHtml('wire:click="toggleClosingSort"')
        ->call('toggleClosingSort')->assertSet('sort', 'closing_asc')->assertSeeInOrder(['EARLY-REF', 'LATE-REF'])->assertSeeHtml('aria-sort="ascending"')
        ->call('toggleClosingSort')->assertSet('sort', 'closing_desc')->assertSeeInOrder(['LATE-REF', 'EARLY-REF'])->assertSeeHtml('aria-sort="descending"');
});

function awarded(string $ref, array $winners, array $o = []): CollectedTender
{
    return CollectedTender::factory()->create(array_merge(['reference_no' => $ref, 'status' => 'closed', 'closing_date' => '2026-03-01', 'winners' => $winners], $o));
}

it('lists awarded tenders with their winners, and searches by contractor ignoring punctuation and wildcards', function () {
    awarded('OURS-1', [['name' => '10 CREATIVE SOLUTIONS SDN. BHD.', 'price_sen' => 500000]]);
    awarded('OURS-2', [['name' => '10 CREATIVE SOLUTIONS SDN BHD', 'price_sen' => 100]]);
    awarded('LOOKALIKE', [['name' => 'DARKWHITE CREATIVE SOLUTIONS SDN. BHD.', 'price_sen' => 1]]);
    CollectedTender::factory()->create(['reference_no' => 'NO-WINNER', 'status' => 'closed']);

    expect(refs(['status' => 'awarded']))->toEqualCanonicalizing(['OURS-1', 'OURS-2', 'LOOKALIKE'])
        ->and(refs(['status' => 'awarded', 'contractor' => '10 creative solutions sdn.bhd']))->toEqualCanonicalizing(['OURS-1', 'OURS-2'])
        ->and(refs(['status' => 'awarded', 'contractor' => '%']))->toEqualCanonicalizing(['OURS-1', 'OURS-2', 'LOOKALIKE']) // too short → ignored
        ->and(refs(['status' => 'awarded', 'contractor' => 'X_Y']))->toBe([])
        ->and(refs(['status' => 'awarded', 'ours' => true]))->toEqualCanonicalizing(['OURS-1', 'OURS-2']);

    Livewire::test(FindTenders::class)->set('status', 'awarded')
        ->assertSee('Winner(s)')->assertSee('10 CREATIVE SOLUTIONS SDN. BHD.')->assertSee('RM 5,000.00')
        ->assertSeeHtml('data-ours')->assertSee('Our wins')
        ->toggle('ours')->assertDontSee('LOOKALIKE')
        ->set('contractor', 'darkwhite')->assertSet('ours', true)
        ->call('clearFilters')->assertSet('contractor', '')->assertSet('ours', false);
});

it('only offers Our wins while Awarded is chosen, and counts a contractor search as a filter', function () {
    Livewire::test(FindTenders::class)->assertDontSee('Our wins')
        ->set('contractor', 'acme')->assertSeeHtml('data-filter-count="1"');
});
