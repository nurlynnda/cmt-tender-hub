<?php

use App\Livewire\{CollectedTenderDetail, RegisterTenderModal, TenderDetail};
use App\Models\{CollectedTender, Tender, User};
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

function collected(array $o = [], string $source = 'myprocurement'): CollectedTender
{
    return CollectedTender::factory()->forSource($source, '980576')->create(array_merge([
        'reference_no' => 'QT260000000041127', 'title' => 'SISTEM RONDAAN', 'procurement_type' => 'requisition',
        'ministry' => 'KEMENTERIAN DALAM NEGERI', 'agency' => 'JABATAN PERPADUAN NEGARA',
        'advertised_date' => '2026-09-08', 'closing_date' => '2026-10-15', 'indicative_price_sen' => 16200610,
        'status' => 'closed', 'winners' => [['name' => 'ACME SDN BHD', 'price_sen' => 15000000]],
        'events' => [['label' => 'Taklimat', 'date' => '2026-09-22', 'address' => 'Putrajaya']],
        'raw' => ['Kod Bidang' => '210103'],
    ], $o));
}

it('shows all details, events, winners, source links and raw fields', function () {
    $c = collected();

    $this->get(route('find-tenders.show', $c))->assertOk()
        ->assertSee('SISTEM RONDAAN')->assertSee('QT260000000041127')
        ->assertSee('Taklimat')->assertSee('Putrajaya')
        ->assertSee('ACME SDN BHD')->assertSee('RM 150,000.00')
        ->assertSee('View on MyProcurement')->assertSee('https://myprocurement.example.test/'.$c->id)
        ->assertSee('All original fields')->assertSee('Kod Bidang')
        ->assertSee('Register this tender');
});

it('pre-fills the Register form from the collected tender', function () {
    $c = collected();

    Livewire::test(RegisterTenderModal::class)
        ->call('show', $c->id)
        ->assertSet('collectedTenderId', $c->id)
        ->assertSet('form.tenderCode', 'QT260000000041127')
        ->assertSet('form.title', 'SISTEM RONDAAN')
        ->assertSet('form.client', 'JABATAN PERPADUAN NEGARA')
        ->assertSet('form.publishDate', '2026-09-08')
        ->assertSet('form.closingDate', '2026-10-15')
        ->assertSet('form.estimatedValue', '162006.10')
        ->assertSet('form.type', 'QUOTATION')
        ->assertSet('form.mode', 'EP')
        ->assertSet('form.category', 'General')
        ->assertSet('form.picId', '');
});

it('uses the ministry when there is no agency, Non-EP for other sources, and Tender for tenders', function () {
    $c = collected(['agency' => null, 'procurement_type' => 'tender', 'closing_date' => null], 'span');

    Livewire::test(RegisterTenderModal::class)->call('show', $c->id)
        ->assertSet('form.client', 'KEMENTERIAN DALAM NEGERI')
        ->assertSet('form.mode', 'NON_EP')
        ->assertSet('form.type', 'TENDER')
        ->assertSet('form.closingDate', '');
});

it('saves the link and then offers "Open in pipeline" instead', function () {
    $c = collected();

    Livewire::test(RegisterTenderModal::class)->call('show', $c->id)
        ->set('form.picId', (string) User::factory()->create()->id)
        ->call('save');

    $tender = Tender::sole();
    expect($tender->collected_tender_id)->toBe($c->id);

    Livewire::test(CollectedTenderDetail::class, ['collectedTender' => $c])
        ->assertSee('Open in pipeline (WO '.$tender->wo_number.')')
        ->assertDontSee('Register this tender');
});

it('refuses a link to a collected tender that does not exist', function () {
    Livewire::test(RegisterTenderModal::class)->call('show')
        ->set('collectedTenderId', 999999)
        ->set('form.tenderCode', 'X')->set('form.title', 'X')->set('form.client', 'X')
        ->set('form.picId', (string) User::factory()->create()->id)->set('form.closingDate', '2026-12-01')
        ->call('save')
        ->assertHasErrors('collectedTenderId');

    expect(Tender::count())->toBe(0);
});

it('the plain Register button still opens an empty form', function () {
    Livewire::test(RegisterTenderModal::class)->call('show')
        ->assertSet('collectedTenderId', null)->assertSet('form.tenderCode', '');
});

it('shows where a pipeline tender was collected from', function () {
    $c = collected();
    $pic = User::factory()->create();
    $tender = Tender::factory()->create(['collected_tender_id' => $c->id, 'pic_id' => $pic->id]);

    Livewire::actingAs($pic)->test(TenderDetail::class, ['tender' => $tender])
        ->assertSee('Collected from MyProcurement')
        ->assertSee(route('find-tenders.show', $c));
});

it('404s for an unknown collected tender', function () {
    $this->get('/find-tenders/999999')->assertNotFound();
});

it('shows the collected tender\'s key facts in the prototype style', function () {
    $this->get(route('find-tenders.show', collected()))->assertOk()
        ->assertSee('data-facts', false)->assertSee('data-status="closed"', false)
        ->assertSeeInOrder(['Ministry', 'KEMENTERIAN DALAM NEGERI', 'Agency', 'JABATAN PERPADUAN NEGARA', 'Closing', '15 Oct 2026']);
});
