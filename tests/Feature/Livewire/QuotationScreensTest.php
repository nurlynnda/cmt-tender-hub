<?php

use App\Enums\QuotationStatus;
use App\Livewire\{QuotationList, QuotationPage};
use App\Models\{ActivityLog, Quotation, QuotationItem, User};
use App\Support\MalaysiaTime;
use Livewire\Livewire;

function myQuotation(array $attrs = []): array
{
    $u = User::factory()->create(['name' => 'Siti Aisyah']);
    $q = Quotation::factory()->create(array_merge(['prepared_by' => $u->id, 'customer_name' => 'JPNIN', 'subject' => 'Switches'], $attrs));

    return [$u, $q];
}

it('lists quotations with search, status and mine filters', function () {
    [$u] = myQuotation(['number' => 'QTN-2026-0012', 'status' => QuotationStatus::Sent, 'quote_date' => MalaysiaTime::today()->format('Y-m-d')]);
    myQuotation(['number' => 'QTN-2026-0010', 'status' => QuotationStatus::Sent, 'quote_date' => '2020-01-01', 'customer_name' => 'Kuantan']);
    myQuotation(['number' => 'QTN-2026-0011', 'status' => QuotationStatus::Accepted, 'customer_name' => 'Klang']);

    Livewire::actingAs($u)->test(QuotationList::class)
        ->assertSee('QTN-2026-0012')->assertSee('QTN-2026-0010')->assertSee('Expired')
        ->set('status', 'expired')->assertSee('QTN-2026-0010')->assertDontSee('QTN-2026-0012')
        ->set('status', 'sent')->assertSee('QTN-2026-0012')->assertDontSee('QTN-2026-0010')
        ->set('status', 'accepted')->assertSee('QTN-2026-0011')->assertDontSee('QTN-2026-0012')
        ->set('status', 'all')->set('search', 'Klang')->assertSee('QTN-2026-0011')->assertDontSee('QTN-2026-0012')
        ->set('search', '')->set('mine', true)->assertSee('QTN-2026-0012')->assertDontSee('QTN-2026-0011');
});

it('creates a new draft and opens it', function () {
    $u = User::factory()->create();

    Livewire::actingAs($u)->test(QuotationList::class)->call('create')
        ->assertRedirect(route('quotations.show', Quotation::first()));
});

it('saves details when a field is left and shows live totals', function () {
    [$u, $q] = myQuotation();
    QuotationItem::factory()->for($q)->create(['quantity' => 6, 'unit_price_sen' => 485000]);

    Livewire::actingAs($u)->test(QuotationPage::class, ['quotation' => $q])
        ->set('tab', 'items')->assertSee('RM 31,428.00')                      // 29,100 + 8%
        ->set('form.customer_name', 'Jabatan Perpaduan')->assertHasNoErrors()
        ->set('form.sst', '6')->assertSee('RM 30,846.00')
        ->set('form.attention_email', 'not-an-email')->assertHasErrors('form.attention_email');

    expect($q->fresh()->customer_name)->toBe('Jabatan Perpaduan')->and($q->fresh()->sst_bp)->toBe(600);
});

it('keeps saving other fields while one field has an error', function () {
    [$u, $q] = myQuotation();

    Livewire::actingAs($u)->test(QuotationPage::class, ['quotation' => $q])
        ->set('form.attention_email', 'ali@')->assertHasErrors('form.attention_email')
        ->set('tab', 'terms')->set('form.terms', 'New terms')
        ->set('form.subject', 'New subject');

    expect($q->fresh()->terms)->toBe('New terms')->and($q->fresh()->subject)->toBe('New subject');
});

it('still saves a draft whose preparer has been switched off', function () {
    [$u, $q] = myQuotation();
    $u->update(['is_active' => false]);

    Livewire::actingAs(User::factory()->manager()->create())->test(QuotationPage::class, ['quotation' => $q])
        ->set('form.subject', 'Manager fix')->assertHasNoErrors();

    expect($q->fresh()->subject)->toBe('Manager fix');
});

it('swaps in the new preparer\'s contact details when the quotation is handed over', function () {
    [$u, $q] = myQuotation(['preparer_position' => 'Sales Executive', 'preparer_phone' => '012-345', 'preparer_email' => 'siti@cmt.test']);
    $faizal = User::factory()->create(['email' => 'faizal@cmt.test']);

    Livewire::actingAs(User::factory()->manager()->create())->test(QuotationPage::class, ['quotation' => $q])
        ->set('form.prepared_by', (string) $faizal->id)
        ->assertSet('form.preparer_email', 'faizal@cmt.test')->assertSet('form.preparer_position', '');

    expect($q->fresh())->preparer_email->toBe('faizal@cmt.test')->preparer_position->toBeNull()->preparer_phone->toBeNull();
});

it('adds, edits, moves and removes items', function () {
    [$u, $q] = myQuotation();

    $c = Livewire::actingAs($u)->test(QuotationPage::class, ['quotation' => $q])->set('tab', 'items')
        ->call('addItem')->call('addItem');
    [$a, $b] = $q->fresh()->items->all();
    $c->set("items.i{$b->id}.title", 'Switch')->set("items.i{$b->id}.unit_price", '4,850')->set("items.i{$b->id}.quantity", '6')
        ->assertSee('29,100.00')
        ->set("items.i{$b->id}.quantity", '0')->assertHasErrors("items.i{$b->id}.quantity")
        ->set("items.i{$b->id}.quantity", '6')
        ->call('moveItem', $b->id, -1)
        ->call('removeItem', $a->id);

    expect($q->fresh()->items->pluck('title')->all())->toBe(['Switch'])->and($q->fresh()->items->first()->unit_price_sen)->toBe(485000);
});

it('resets the terms to the company default', function () {
    [$u, $q] = myQuotation(['terms' => 'Old']);

    Livewire::actingAs($u)->test(QuotationPage::class, ['quotation' => $q])->set('tab', 'terms')->call('resetTerms');

    expect($q->fresh()->terms)->toBe(App\Models\CompanyProfile::current()->default_terms);
});

it('goes Draft → Sent → Accepted → project, explaining what is missing first', function () {
    [$u, $q] = myQuotation(['customer_name' => null]);

    Livewire::actingAs($u)->test(QuotationPage::class, ['quotation' => $q])
        ->call('markSent')->assertSee('Add a customer name and at least one item before marking this quotation Sent.');

    $q->update(['customer_name' => 'JPNIN']);
    QuotationItem::factory()->for($q)->create();
    $c = Livewire::actingAs($u)->test(QuotationPage::class, ['quotation' => $q->fresh()])
        ->call('markSent')->assertSee('Mark Accepted')
        ->set('tab', 'items')->assertDontSee('+ Add item')
        ->call('markAccepted')->assertSee('Create project')
        ->call('createProject');

    $c->assertRedirect(route('quotations.pd', $q));
    expect($q->fresh()->project)->not->toBeNull();

    Livewire::actingAs($u)->test(QuotationPage::class, ['quotation' => $q->fresh()])->assertSee('Open project');
});

it('marks a sent quotation rejected', function () {
    [$u, $q] = myQuotation(['status' => QuotationStatus::Sent]);

    Livewire::actingAs($u)->test(QuotationPage::class, ['quotation' => $q])->call('markRejected')->assertSee('Rejected');

    expect($q->fresh()->status)->toBe(QuotationStatus::Rejected);
});

it('revises and duplicates into new drafts', function () {
    [$u, $q] = myQuotation(['status' => QuotationStatus::Sent, 'number' => 'QTN-2026-0012']);

    Livewire::actingAs($u)->test(QuotationPage::class, ['quotation' => $q])->call('revise')
        ->assertRedirect(route('quotations.show', Quotation::where('number', 'QTN-2026-0012-R1')->first()));

    Livewire::actingAs(User::factory()->create())->test(QuotationPage::class, ['quotation' => $q->fresh()])->call('duplicate')
        ->assertRedirect(route('quotations.show', Quotation::latest('id')->first()));
});

it('is read-only for other staff, and offers Back to Draft to managers only', function () {
    [$preparer, $q] = myQuotation(['status' => QuotationStatus::Sent]);

    Livewire::actingAs(User::factory()->create())->test(QuotationPage::class, ['quotation' => $q])
        ->assertDontSee('Mark Accepted')->assertDontSee('Back to Draft')->assertSee('Duplicate')
        ->set('form.subject', 'hack')->assertForbidden();

    Livewire::actingAs($preparer)->test(QuotationPage::class, ['quotation' => $q])
        ->set('form.subject', 'hack')->assertSee('This quotation has been sent');

    Livewire::actingAs(User::factory()->manager()->create())->test(QuotationPage::class, ['quotation' => $q])
        ->assertSee('Back to Draft')->call('backToDraft')->assertSee('Mark as Sent');

    expect($q->fresh()->subject)->toBe('Switches');
});

it('explains when an item was removed by someone else', function () {
    [$u, $q] = myQuotation();
    $item = QuotationItem::factory()->for($q)->create();
    $c = Livewire::actingAs($u)->test(QuotationPage::class, ['quotation' => $q]);
    $item->delete();

    $c->call('removeItem', $item->id)->assertSee('This item was removed by someone else');
});

it('previews the real PDF and shows history', function () {
    [$u, $q] = myQuotation();
    ActivityLog::record($q, $u, 'quotation_created', 'Quotation created');

    Livewire::actingAs($u)->test(QuotationPage::class, ['quotation' => $q])
        ->set('tab', 'preview')->assertSeeHtml(e(route('quotations.pdf', [$q, 'inline' => 1])))
        ->set('tab', 'history')->assertSee('Quotation created');
});

it('links Quotations from the sidebar and opens the pages', function () {
    [$u, $q] = myQuotation();

    $this->actingAs($u)->get(route('settings'))->assertSee(route('quotations.index'));
    $this->actingAs($u)->get(route('quotations.index'))->assertOk()->assertSee($q->number);
    $this->actingAs($u)->get(route('quotations.show', $q))->assertOk()->assertSee($q->number);
});

it('counts quotations per status for the current search, matching what each status button shows', function () {
    $this->actingAs(User::factory()->create());
    Quotation::factory()->create(['customer_name' => 'ALPHA', 'status' => \App\Enums\QuotationStatus::Draft]);
    Quotation::factory()->create(['customer_name' => 'ALPHA', 'status' => \App\Enums\QuotationStatus::Sent,
        'quote_date' => now()->subDays(60), 'validity_days' => 30]);                         // expired
    Quotation::factory()->create(['customer_name' => 'BETA', 'status' => \App\Enums\QuotationStatus::Draft]);

    $c = Livewire::test(\App\Livewire\QuotationList::class)->set('search', 'ALPHA');
    $c->assertSeeHtml('data-status-count="all">2<')->assertSeeHtml('data-status-count="draft">1<')
      ->assertSeeHtml('data-status-count="expired">1<')->assertSeeHtml('data-status-count="sent">0<');
    $c->set('status', 'draft')->assertSee('ALPHA')->assertDontSee('BETA');
});

it('sorts quotations by date when the Date heading is clicked', function () {
    $this->actingAs(User::factory()->create());
    Quotation::factory()->create(['subject' => 'OLDER ONE', 'quote_date' => '2026-01-01']);
    Quotation::factory()->create(['subject' => 'NEWER ONE', 'quote_date' => '2026-06-01']);

    Livewire::test(\App\Livewire\QuotationList::class)
        ->assertSeeInOrder(['NEWER ONE', 'OLDER ONE'])
        ->call('toggleDateSort')->assertSet('sort', 'date_asc')->assertSeeInOrder(['OLDER ONE', 'NEWER ONE'])
        ->set('sort', 'bogus')->assertSeeInOrder(['NEWER ONE', 'OLDER ONE']);
});

it('tells the page a field was saved, so it can show Saved', function () {
    [$u, $q] = myQuotation();

    Livewire::actingAs($u)->test(QuotationPage::class, ['quotation' => $q])
        ->set('form.customer_name', 'Jabatan Perpaduan')->assertDispatched('saved')
        ->set('form.attention_email', 'not-an-email')->assertHasErrors('form.attention_email');
});

it('shows the quotation page with its pill, tabs and the shared timeline', function () {
    [$u, $q] = myQuotation();

    Livewire::actingAs($u)->test(QuotationPage::class, ['quotation' => $q])
        ->assertSeeHtml('data-status="draft"')->assertSeeHtml('role="tablist"')
        ->set('tab', 'history')->assertSeeHtml('border-l-2 border-line');
});

it('prices an item from cost and margin, or from a typed price, and shows the profit summary', function () {
    [$u, $q] = myQuotation();
    $item = QuotationItem::factory()->for($q)->create();
    $k = "i{$item->id}";

    $c = Livewire::actingAs($u)->test(QuotationPage::class, ['quotation' => $q])->set('tab', 'items')
        ->set("items.$k.title", 'Switch')->set("items.$k.quantity", '2')->set("items.$k.unit_price", '')
        ->set("items.$k.unit_cost", '1,000')->set("items.$k.margin", '20');

    expect($item->fresh()->unit_price_sen)->toBe(125000);
    $c->assertSee('Total cost')->assertSee('RM 2,000.00')->assertSee('RM 2,500.00')
        ->set("items.$k.unit_price", '1,100')->assertSet("items.$k.margin", '20')->assertSee('9.1% from price')->assertSee('Below the 18% company target')
        ->set("items.$k.margin", '20')->assertSet("items.$k.unit_price", '');
    expect($item->fresh()->unit_price_sen)->toBe(125000);
});

it('adds sub-items, ticks SST off and sets the frequency on an item', function () {
    [$u, $q] = myQuotation();
    $item = QuotationItem::factory()->for($q)->create();
    $k = "i{$item->id}";

    Livewire::actingAs($u)->test(QuotationPage::class, ['quotation' => $q])->set('tab', 'items')
        ->call('addSubItem', $item->id)
        ->set("items.$k.sub_items.0.description", 'Rack')->set("items.$k.sub_items.0.unit_cost", '600')
        ->set("items.$k.frequency", '12')->set("items.$k.sst", false)
        ->assertSee('on items marked *')->assertSeeHtml('aria-label="SST on this item"');

    $fresh = $item->fresh();
    expect($fresh->only(['frequency', 'has_sst']))->toBe(['frequency' => 12, 'has_sst' => false])
        ->and($fresh->sub_items[0])->toMatchArray(['description' => 'Rack', 'unit_cost_sen' => 60000])
        ->and($q->fresh()->totals()['sst_sen'])->toBe(0);

    Livewire::actingAs($u)->test(QuotationPage::class, ['quotation' => $q->fresh()])->set('tab', 'items')
        ->call('removeSubItem', $item->id, 0);
    expect($item->fresh()->sub_items)->toBeNull();
});

it('saves the default margin used for new items', function () {
    [$u, $q] = myQuotation();

    Livewire::actingAs($u)->test(QuotationPage::class, ['quotation' => $q])->set('tab', 'items')->set('form.default_margin', '15')->call('addItem');

    expect($q->fresh()->items->last()->margin_bp)->toBe(1500);
});

it('keeps the margin when a price is typed, so clearing the price goes back to the worked-out price', function () {
    [$u, $q] = myQuotation();
    $item = QuotationItem::factory()->for($q)->create(['unit_cost_sen' => 0, 'unit_price_sen' => 650000]);   // like an item saved before costing
    $k = "i{$item->id}";

    Livewire::actingAs($u)->test(QuotationPage::class, ['quotation' => $q])->set('tab', 'items')
        ->assertSet("items.$k.margin", '20')
        ->set("items.$k.unit_price", '')->set("items.$k.unit_cost", '4,000');
    expect($item->fresh()->unit_price_sen)->toBe(500000);                                                    // 4,000 ÷ 0.8, not 40,000,000
});

it('lets other fields save while a newly added sub-item is still blank', function () {
    [$u, $q] = myQuotation();
    $item = QuotationItem::factory()->for($q)->create();
    $k = "i{$item->id}";

    Livewire::actingAs($u)->test(QuotationPage::class, ['quotation' => $q])->set('tab', 'items')
        ->call('addSubItem', $item->id)->set("items.$k.frequency", '12')->assertHasNoErrors();
    expect($item->fresh()->frequency)->toBe(12)->and($item->fresh()->sub_items)->toBeNull();
});
