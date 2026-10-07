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
