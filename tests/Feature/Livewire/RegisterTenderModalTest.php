<?php

use App\Livewire\RegisterTenderModal;
use App\Models\{Tender, User};
use Livewire\Livewire;

function filledRegister(User $pic)
{
    return Livewire::test(RegisterTenderModal::class)
        ->call('show')
        ->set('form.tenderCode', 'QT260000000041127')
        ->set('form.title', 'PERKHIDMATAN PEMBANGUNAN SISTEM')
        ->set('form.client', 'KEMENTERIAN KESIHATAN')
        ->set('form.picId', (string) $pic->id)
        ->set('form.closingDate', '2026-10-15')
        ->set('form.estimatedValue', 'RM 162,006.10');
}

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('registers a tender and opens it', function () {
    $pic = User::factory()->create();

    $component = filledRegister($pic)->call('save');

    $tender = Tender::sole();
    $component->assertRedirect(route('tenders.show', $tender));
    expect($tender->estimated_value_sen)->toBe(16200610)
        ->and($tender->pic_id)->toBe($pic->id)
        ->and($tender->documents)->toHaveCount(5);
});

it('requires the essentials', function () {
    Livewire::test(RegisterTenderModal::class)->call('show')->call('save')
        ->assertHasErrors(['form.tenderCode', 'form.title', 'form.client', 'form.picId', 'form.closingDate']);
    expect(Tender::count())->toBe(0);
});

it('uses plain-English field names in error messages', function () {
    Livewire::test(RegisterTenderModal::class)->call('show')->call('save')
        ->assertSee('The person in charge field is required.')
        ->assertSee('The tender code field is required.')
        ->assertDontSee('pic id');
});

it('rejects a closing date before the publish date, bad money and missing briefing date', function () {
    filledRegister(User::factory()->create())
        ->set('form.publishDate', '2026-10-20')
        ->set('form.estimatedValue', '-5')
        ->set('form.hasBriefing', true)
        ->call('save')
        ->assertHasErrors(['form.closingDate', 'form.estimatedValue', 'form.briefingDate']);
});

it('refuses a deactivated PIC', function () {
    filledRegister(User::factory()->inactive()->create())->call('save')->assertHasErrors('form.picId');
});

it('warns about a duplicate tender code and registers only after confirmation', function () {
    Tender::factory()->create(['tender_code' => 'QT260000000041127', 'wo_number' => '200-01012026-009']);

    $component = filledRegister(User::factory()->create())->call('save')
        ->assertSee('200-01012026-009')
        ->assertSet('confirmDuplicate', true);
    expect(Tender::count())->toBe(1);

    $component->call('save');
    expect(Tender::count())->toBe(2);
});

it('drops the duplicate confirmation when the code is edited', function () {
    Tender::factory()->create(['tender_code' => 'QT260000000041127']);

    filledRegister(User::factory()->create())->call('save')
        ->set('form.tenderCode', 'QT999')
        ->assertSet('confirmDuplicate', false);
});

it('shows the WO number and date that registering will use, labelled as automatic', function () {
    $this->travelTo(\Carbon\CarbonImmutable::parse('2026-10-07 02:00:00', 'UTC'));

    Livewire::test(RegisterTenderModal::class)->call('show')
        ->assertSee('WO Number (auto')->assertSee('200-07102026-001')->assertSee('07 Oct 2026')
        ->call('close')->assertSet('open', false);
    expect(\Illuminate\Support\Facades\DB::table('wo_sequences')->count())->toBe(0);
});

it('does not close Register Tender on a stray click or Escape, so typed details are not lost', function () {
    Livewire::test(RegisterTenderModal::class)->call('show')
        ->assertDontSeeHtml('keydown.escape')->assertSeeHtml('>Cancel</button>');
});
