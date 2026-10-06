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
