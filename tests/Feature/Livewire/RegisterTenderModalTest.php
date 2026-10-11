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
        ->set('form.fieldCodes', '210101')
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
        ->assertHasErrors(['form.tenderCode', 'form.title', 'form.client', 'form.closingDate'])
        ->assertHasNoErrors('form.picId'); // the PIC can be added later
    expect(Tender::count())->toBe(0);
});

it('uses plain-English field names in error messages', function () {
    Livewire::test(RegisterTenderModal::class)->call('show')->call('save')
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

it('registers a tender with no PIC', function () {
    filledRegister(User::factory()->create())->set('form.picId', '')->call('save')->assertHasNoErrors();

    expect(Tender::sole()->pic_id)->toBeNull();
});

it('pops up a reminder, and registers nothing, when CMT holds none of the tender\'s field codes', function () {
    filledRegister(User::factory()->create())
        ->set('form.fieldCodes', 'B04, 050201')
        ->call('save')
        ->assertSet('codeBlock', ['B04', '050201'])
        ->assertSee('Cannot register this tender')
        ->assertSee('we do not hold its field code')
        ->assertSee('B04, 050201')
        ->assertNoRedirect()
        ->call('dismissCodeBlock')
        ->assertSet('codeBlock', null)
        ->assertDontSee('Cannot register this tender');

    expect(Tender::count())->toBe(0);
});

it('pops up the same reminder when no field code is entered', function () {
    filledRegister(User::factory()->create())->set('form.fieldCodes', '')->call('save')
        ->assertSet('codeBlock', [])
        ->assertSee('Cannot register this tender')->assertSee('has no field codes');

    expect(Tender::count())->toBe(0);
});

it('checks the field codes before asking about a duplicate tender code', function () {
    Tender::factory()->create(['tender_code' => 'QT260000000041127']);

    filledRegister(User::factory()->create())->set('form.fieldCodes', 'B04')->call('save')
        ->assertSet('confirmDuplicate', false)->assertSet('codeBlock', ['B04']);
});

it('registers when only one of several field codes is held', function () {
    filledRegister(User::factory()->create())->set('form.fieldCodes', 'B04, 220301')->call('save')->assertHasNoErrors();

    expect(Tender::sole()->field_codes)->toBe(['B04', '220301']);
});

it('rejects something that is not a field code', function () {
    filledRegister(User::factory()->create())->set('form.fieldCodes', 'not a code!')->call('save')->assertHasErrors('form.fieldCodes');
});

it('lists CMT\'s field codes on the form', function () {
    Livewire::test(RegisterTenderModal::class)->call('show')->assertSee('CMT\'s field codes (30)')->assertSee('210101')->assertSee('221207');
});

it('confirms with a pop-up on the next page that the tender was registered', function () {
    filledRegister(User::factory()->create())->call('save');

    expect(session('toast'))->toBe(['message' => 'Tender '.Tender::sole()->wo_number.' registered', 'type' => 'success']);
});
