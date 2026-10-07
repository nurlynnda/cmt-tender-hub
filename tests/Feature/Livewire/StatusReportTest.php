<?php

use App\Enums\TenderStatus;
use App\Livewire\{Dashboard, StatusReport};
use App\Models\{Tender, User};
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

it('shows per-PIC performance with totals', function () {
    $ahmad = User::factory()->create(['name' => 'Ahmad Faizal']);
    $nurul = User::factory()->create(['name' => 'Nurul Ain']);
    Tender::factory()->status(TenderStatus::Awarded)->create(['pic_id' => $ahmad->id, 'submitted_price_sen' => 500000]);
    Tender::factory()->status(TenderStatus::Lost)->create(['pic_id' => $ahmad->id, 'submitted_price_sen' => 100000]);

    Livewire::actingAs($nurul)->test(StatusReport::class)
        ->assertSee('Per-PIC tender performance')
        ->assertSeeInOrder(['Ahmad Faizal', '2', '1', '1', '50%', 'RM 6,000.00', 'RM 5,000.00'])
        ->assertSee('Nurul Ain')->assertSee('Total')
        ->assertSeeHtml(e(route('tenders.index', ['awarded', 'pic' => $ahmad->id])));
});

it('sorts by any column and back', function () {
    User::factory()->create(['name' => 'Zara']);
    $a = User::factory()->create(['name' => 'Aminah']);
    Tender::factory()->status(TenderStatus::Done)->create(['pic_id' => $a->id, 'submitted_price_sen' => 100]);

    Livewire::actingAs($a)->test(StatusReport::class)
        ->assertSeeInOrder(['Aminah', 'Zara'])
        ->call('sortBy', 'name')->assertSet('dir', 'asc')->assertSeeInOrder(['Aminah', 'Zara'])
        ->call('sortBy', 'name')->assertSet('dir', 'desc')->assertSeeInOrder(['Zara', 'Aminah'])
        ->call('sortBy', 'total')->assertSet('sort', 'total')->assertSet('dir', 'desc')
        ->call('sortBy', 'nonsense')->assertSet('sort', 'bid_value_sen')->assertSet('dir', 'desc');
});

it('uses the period filter', function () {
    $u = User::factory()->create(['name' => 'Siti Aisyah']);
    Tender::factory()->create(['pic_id' => $u->id, 'wo_date' => '2001-01-01']);

    Livewire::actingAs($u)->test(StatusReport::class)->set('period', 'month')->set('month', '2001-01')
        ->assertSee('Jan 2001')->assertSeeInOrder(['Siti Aisyah', '1']);
});

it('is in the sidebar and reachable', function () {
    $this->actingAs(User::factory()->create())->get(route('status'))->assertOk()->assertSee(route('status'));
});

it('does not run more queries as tenders pile up', function () {
    $u = User::factory()->create();
    $count = function () use ($u) {
        DB::flushQueryLog();
        DB::enableQueryLog();
        Livewire::actingAs($u)->test(Dashboard::class);
        Livewire::actingAs($u)->test(StatusReport::class);
        $n = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $n;
    };
    Tender::factory()->count(3)->create(['pic_id' => $u->id]);
    $few = $count();
    Tender::factory()->count(30)->create(['pic_id' => User::factory()->create()->id]);

    expect($count())->toBe($few);
});
