<?php

use App\Enums\TenderStatus;
use App\Livewire\TenderList;
use App\Models\{Tender, User};
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('renders each list at its URL', function (string $slug, string $title) {
    $this->get("/tenders/{$slug}")->assertOk()->assertSee($title);
})->with([
    ['in-progress', 'In Progress Tenders'],
    ['done', 'Done Tenders'],
    ['awarded', 'Awarded Tenders'],
    ['lost', 'Lost Tenders'],
]);

it('404s an unknown list', function () {
    $this->get('/tenders/bogus')->assertNotFound();
});

it('shows list-specific columns', function () {
    Tender::factory()->status(TenderStatus::Lost)->create([
        'estimated_value_sen' => 40275400, 'submitted_price_sen' => 52828500, 'winning_price_sen' => 86617900,
        'was_cancelled' => true,
    ]);

    Livewire::test(TenderList::class, ['list' => 'lost'])
        ->assertSee('Win Variant')
        ->assertSee('RM 866,179.00')
        ->assertSee('215.1%')
        ->assertSee('Cancelled')
        ->assertDontSee('Briefing');

    Livewire::test(TenderList::class, ['list' => 'in-progress'])->assertSee('Briefing')->assertSee('Register Tender');
});

it('paginates ten per page', function () {
    Tender::factory()->count(12)->create();

    Livewire::test(TenderList::class, ['list' => 'in-progress'])->assertSee('Showing 1–10 of 12');
});

it('shows simple page buttons without a second summary line', function () {
    Tender::factory()->count(12)->create();

    Livewire::test(TenderList::class, ['list' => 'in-progress'])
        ->assertSeeInOrder(['Prev', '1', '2', 'Next'])
        ->assertDontSee('results');
});

it('highlights tenders closing soon and overdue', function () {
    Tender::factory()->create(['wo_number' => 'SOON-1', 'closing_date' => now('Asia/Kuala_Lumpur')->addDays(2)->toDateString()]);
    Tender::factory()->create(['wo_number' => 'LATE-1', 'closing_date' => now('Asia/Kuala_Lumpur')->subDay()->toDateString()]);

    Livewire::test(TenderList::class, ['list' => 'in-progress'])
        ->assertSeeHtml('data-closing="soon"')
        ->assertSeeHtml('data-closing="overdue"');
});

it('resets to page 1 when the search changes', function () {
    Tender::factory()->count(12)->create();

    Livewire::test(TenderList::class, ['list' => 'in-progress'])
        ->call('gotoPage', 2)
        ->set('search', 'x')
        ->assertSet('paginators.page', 1);
});

it('filters by when the tender was registered (WO date), for dashboard drill-downs', function () {
    Tender::factory()->create(['wo_date' => '2026-10-01', 'title' => 'REGISTERED IN OCTOBER']);
    Tender::factory()->create(['wo_date' => '2026-09-30', 'title' => 'REGISTERED IN SEPTEMBER']);

    Livewire::test(TenderList::class, ['list' => 'in-progress'])
        ->set('wo_from', '2026-10-01')->set('wo_to', '2026-10-31')
        ->assertSee('REGISTERED IN OCTOBER')->assertDontSee('REGISTERED IN SEPTEMBER')
        ->assertSee('Registered 01 Oct 2026 – 31 Oct 2026')
        ->call('clearFilters')->assertSee('REGISTERED IN SEPTEMBER');
});

it('keeps In Progress rows plain like the other lists and marks the deadline date instead', function () {
    Tender::factory()->create(['wo_number' => 'SOON-2', 'closing_date' => now('Asia/Kuala_Lumpur')->addDays(2)->toDateString()]);
    Tender::factory()->create(['wo_number' => 'LATE-2', 'closing_date' => now('Asia/Kuala_Lumpur')->subDay()->toDateString()]);

    Livewire::test(TenderList::class, ['list' => 'in-progress'])
        ->assertDontSeeHtml('bg-warn-bg/50')->assertDontSeeHtml('bg-bad-bg/60')
        ->assertSeeHtml(['data-deadline="soon"', 'font-semibold text-warn-ink'])
        ->assertSeeHtml(['data-deadline="overdue"', 'font-semibold text-bad-ink']);
});

it('filters by agency, including names with & and apostrophes, and ignores an agency not in the list', function () {
    Tender::factory()->create(['client' => "JABATAN KERJA RAYA & D'SERVIS", 'title' => 'JKR JOB']);
    Tender::factory()->create(['client' => 'KEMENTERIAN KESIHATAN', 'title' => 'KKM JOB']);

    Livewire::test(TenderList::class, ['list' => 'in-progress'])
        ->assertSeeHtml('<option value="JABATAN KERJA RAYA &amp; D&#039;SERVIS">')
        ->set('agency', "JABATAN KERJA RAYA & D'SERVIS")->assertSee('JKR JOB')->assertDontSee('KKM JOB')
        ->set('agency', 'NOT AN AGENCY')->assertSee('JKR JOB')->assertSee('KKM JOB');
});

it('sorts by deadline when the Deadline heading is clicked, flipping on each click', function () {
    Tender::factory()->create(['title' => 'EARLY ONE', 'closing_date' => '2026-11-01']);
    Tender::factory()->create(['title' => 'LATE ONE', 'closing_date' => '2026-12-01']);

    Livewire::test(TenderList::class, ['list' => 'in-progress'])
        ->call('toggleDeadlineSort')->assertSet('sort', 'deadline_asc')->assertSeeInOrder(['EARLY ONE', 'LATE ONE'])
        ->call('toggleDeadlineSort')->assertSet('sort', 'deadline_desc')->assertSeeInOrder(['LATE ONE', 'EARLY ONE'])
        ->set('sort', 'bogus')->assertSeeInOrder(['EARLY ONE', 'LATE ONE']); // In Progress default: closing soonest first
});

it('counts the filters that are on, but not the Dashboard date range', function () {
    $c = Livewire::test(TenderList::class, ['list' => 'in-progress']);
    expect($c->instance()->filterCount())->toBe(0);

    $c->set('mine', true)->set('mode', 'EP')->set('wo_from', '2026-10-01')->set('wo_to', '2026-10-31');
    expect($c->instance()->filterCount())->toBe(2);
    $c->assertSeeHtml('data-filter-count="2"')->assertSee('Registered 01 Oct 2026 – 31 Oct 2026');
});

it('shows each tender as a card on phones with the list’s own figures', function () {
    Tender::factory()->status(TenderStatus::Lost)->create(['wo_number' => 'CARD-1', 'winning_price_sen' => 86617900]);

    Livewire::test(TenderList::class, ['list' => 'lost'])
        ->assertSeeHtml('data-card="CARD-1"')->assertSee('Win Price')->assertSee('RM 866,179.00');
});

it('shows document progress as done out of total', function () {
    $t = Tender::factory()->create();
    $t->documents()->delete(); // start from a known checklist
    $t->documents()->createMany([['name' => 'A', 'position' => 1, 'is_done' => true], ['name' => 'B', 'position' => 2, 'is_done' => false]]);

    Livewire::test(TenderList::class, ['list' => 'in-progress'])->assertSee('1/2');
});

it('lets keyboard and screen-reader users sort by deadline', function () {
    Livewire::test(TenderList::class, ['list' => 'in-progress'])
        ->assertSeeHtml('aria-sort="none"')->assertSeeHtml('<button type="button" wire:click="toggleDeadlineSort"')
        ->call('toggleDeadlineSort')->assertSeeHtml('aria-sort="ascending"')
        ->call('toggleDeadlineSort')->assertSeeHtml('aria-sort="descending"');
});
