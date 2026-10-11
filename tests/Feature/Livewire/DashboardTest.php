<?php

use App\Enums\{TenderMode, TenderStatus};
use App\Livewire\Dashboard;
use App\Models\{Tender, User};
use Carbon\Carbon;
use Livewire\Livewire;

beforeEach(fn () => Carbon::setTestNow('2026-10-15 02:00:00'));
afterEach(fn () => Carbon::setTestNow());

it('is the home page', function () {
    $this->actingAs(User::factory()->create())->get('/')->assertRedirect(route('dashboard'));
    $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertOk()->assertSee('Quick Overview');
});

it('shows the overview cards with links', function () {
    $u = User::factory()->create(['name' => 'Siti Aisyah']);
    Tender::factory()->create(['pic_id' => $u->id, 'wo_date' => '2026-10-02', 'closing_date' => '2026-10-17',
        'estimated_value_sen' => 50000000, 'submitted_price_sen' => null]);
    Tender::factory()->status(TenderStatus::Awarded)->create(['pic_id' => $u->id, 'wo_date' => '2026-10-03', 'submitted_price_sen' => 170000000]);
    Tender::factory()->status(TenderStatus::Lost)->create(['pic_id' => $u->id, 'wo_date' => '2026-10-04', 'submitted_price_sen' => 1, 'was_cancelled' => true]);

    Livewire::actingAs($u)->test(Dashboard::class)
        ->assertSee('1 due this week')
        ->assertSee('RM 1,700,000.00 won')
        ->assertSee('(1 cancelled)')->assertDontSee('incl. cancelled') // one note in brackets, not two
        ->assertSee('100%')->assertSee('1 of 1 decided')
        ->assertSee('RM 2,200,000.00')          // portfolio = 1,700,000 + 500,000
        ->assertSeeHtml(route('tenders.index', 'awarded'));
});

it('filters by the WO month and explains bad periods', function () {
    $u = User::factory()->create();
    Tender::factory()->create(['pic_id' => $u->id, 'wo_date' => '2026-10-02']);
    Tender::factory()->create(['pic_id' => $u->id, 'wo_date' => '2026-09-02']);

    Livewire::actingAs($u)->test(Dashboard::class)
        ->assertSeeInOrder(['>2<', 'tenders'], false)
        ->set('period', 'this_month')->assertSee('This month (Oct 2026)')->assertSeeInOrder(['>1<', 'tenders'], false)
        ->set('period', 'custom')->set('from', '2026-12-01')->set('to', '2026-01-01')
        ->assertSee("That period wasn't valid — showing all time.", false);
});

it('lists upcoming deadlines whatever the period', function () {
    $u = User::factory()->create(['name' => 'Ahmad Faizal']);
    Tender::factory()->create(['pic_id' => $u->id, 'wo_date' => '2020-01-01', 'closing_date' => '2026-10-16', 'title' => 'CLOSING TOMORROW']);

    Livewire::actingAs($u)->test(Dashboard::class)->set('period', 'this_month')
        ->assertSee('Upcoming deadlines')->assertSee('CLOSING TOMORROW')->assertSee('AF');
});

it('shows the portfolio mix, PIC summary and the two cards', function () {
    $u = User::factory()->create(['name' => 'Nurul Ain']);
    Tender::factory()->status(TenderStatus::Done)->create(['pic_id' => $u->id, 'mode' => TenderMode::NonEp, 'submitted_price_sen' => 100000]);

    Livewire::actingAs($u)->test(Dashboard::class)
        ->assertSee('Portfolio mix')->assertSee('Non-EP')->assertSeeHtml('<svg')
        ->assertSee('PIC summary')->assertSee('Nurul Ain')->assertSee('Grand total')
        ->assertSee('Quotations')->assertSee('Projects')->assertSee('below approved margin');
});

it('says so when nothing was registered in the period', function () {
    Livewire::actingAs(User::factory()->create())->test(Dashboard::class)->set('period', 'last_month')
        ->assertSee('No tenders registered in this period.')->assertSee('no decided bids yet');
});

it('sends a signed-in visitor of the login page to the dashboard', function () {
    $this->actingAs(User::factory()->create())->get('/login')->assertRedirect('/dashboard');
});

it('lays out the six Quick Overview boxes like the prototype', function () {
    $this->actingAs(\App\Models\User::factory()->create());

    $html = \Livewire\Livewire::test(\App\Livewire\Dashboard::class)->html();
    foreach (['In Progress', 'Awarded', 'Done', 'Lost', 'Win rate', 'Portfolio value'] as $k) {
        expect($html)->toContain('data-kpi="'.$k.'"');
    }
});

it('splits the submission-mode bar by EP and Non-EP count, and draws nothing without tenders', function () {
    $this->actingAs(\App\Models\User::factory()->create());
    \Livewire\Livewire::test(\App\Livewire\Dashboard::class)->assertDontSeeHtml('data-mode-bar');

    \App\Models\Tender::factory()->count(3)->create(['mode' => \App\Enums\TenderMode::Ep]);
    \App\Models\Tender::factory()->create(['mode' => \App\Enums\TenderMode::NonEp]);
    \Livewire\Livewire::test(\App\Livewire\Dashboard::class)->assertSeeHtml('data-mode-bar')->assertSeeHtml('data-ep-pct="75"');
});

it('shows the portfolio value shortened, with no bracketed note beside it', function () {
    $this->actingAs(\App\Models\User::factory()->create());
    \App\Models\Tender::factory()->create(['estimated_value_sen' => 2389441110]);
    \App\Models\Tender::factory()->create(['estimated_value_sen' => null]);

    \Livewire\Livewire::test(\App\Livewire\Dashboard::class)->assertSee('RM 23.9M')
        ->assertDontSee('RM 23,894,411.10 bid value')->assertDontSee('without a value');
});

it('shows Dropped as its own slice in the status ring', function () {
    $this->actingAs(\App\Models\User::factory()->create());
    \App\Models\Tender::factory()->create(['status' => \App\Enums\TenderStatus::Dropped]);

    \Livewire\Livewire::test(\App\Livewire\Dashboard::class)->assertSeeInOrder(['Lost', 'Dropped', '1 (100%)']);
});
