<?php

use App\Enums\QuotationStatus;
use App\Models\{Quotation, QuotationItem, User};
use App\Pdf\QuotationPdf;
use Illuminate\Support\Facades\Storage;

const TINY_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

function pdfQuotation(array $attrs = []): Quotation
{
    $preparer = User::factory()->create(['name' => 'Siti Aisyah']);
    $q = Quotation::factory()->create(array_merge(['number' => 'QTN-2026-0012', 'prepared_by' => $preparer->id,
        'customer_name' => 'Jabatan Perpaduan Negara dan Integrasi Nasional', 'attention' => 'Puan Rozita binti Hassan',
        'subject' => 'Supply of network switches', 'preparer_position' => 'Sales Executive',
        'terms' => "Prices quoted are in Ringgit Malaysia (RM).\n\nPayment terms: 30 days from the date of invoice."], $attrs));
    QuotationItem::factory()->for($q)->create(['title' => '24-port Gigabit PoE+ managed switch', 'quantity' => 6, 'unit_price_sen' => 485000,
        'details' => "Power Supply: 100–240 V AC\nRack mountable"]);
    QuotationItem::factory()->for($q)->create(['position' => 2, 'title' => 'Installation', 'unit' => 'Lot', 'unit_price_sen' => 650000]);

    return $q->fresh();
}

it('lays out the quotation like the prototype', function () {
    $html = app(QuotationPdf::class)->html(pdfQuotation());

    expect($html)->toContain('QUOTATION')->toContain('QTN-2026-0012')->toContain('Jabatan Perpaduan Negara dan Integrasi Nasional')
        ->toContain('Puan Rozita binti Hassan')->toContain('<strong>Power Supply:</strong> 100–240 V AC')->toContain('Rack mountable')
        ->toContain('35,600.00')->toContain('SST (8.0%)')->toContain('2,848.00')->toContain('38,448.00')
        ->toContain('Ringgit Malaysia Thirty Eight Thousand Four Hundred Forty Eight Only')
        ->toContain('1.</td>')->toContain('2.</td>')->not->toContain('3.</td>')   // blank term line skipped
        ->toContain('Siti Aisyah')->toContain('Sales Executive')->toContain('This is a computer-generated quotation.')
        ->toContain('class="signature"')
        ->not->toContain('data:image');
});

it('shows the stamp only when switched on and present', function () {
    Storage::fake('local');
    Storage::disk('local')->put('company-stamps/s.png', base64_decode(TINY_PNG));
    $letterhead = ['name' => 'CMT Sdn. Bhd.', 'stamp_path' => 'company-stamps/s.png'];

    expect(app(QuotationPdf::class)->html(pdfQuotation(['letterhead' => $letterhead])))->toContain('data:image/png;base64,')
        ->and(app(QuotationPdf::class)->html(pdfQuotation(['number' => 'QTN-2026-0013', 'letterhead' => $letterhead, 'show_stamp' => false])))->not->toContain('data:image')
        ->and(app(QuotationPdf::class)->html(pdfQuotation(['number' => 'QTN-2026-0014', 'letterhead' => ['name' => 'X', 'stamp_path' => 'gone.png']])))->not->toContain('data:image');
});

it('hides the typed signature when switched off', function () {
    expect(app(QuotationPdf::class)->html(pdfQuotation(['show_signature' => false])))->not->toContain('class="signature"');
});

it('downloads a real PDF named after the quotation, or shows it in the page', function () {
    $q = pdfQuotation(['status' => QuotationStatus::Sent]);
    $this->actingAs(User::factory()->create());

    $r = $this->get(route('quotations.pdf', $q));
    $r->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect(substr($r->getContent(), 0, 4))->toBe('%PDF')
        ->and($r->headers->get('Content-Disposition'))->toBe('attachment; filename="QTN-2026-0012.pdf"');

    expect($this->get(route('quotations.pdf', [$q, 'inline' => 1]))->headers->get('Content-Disposition'))->toStartWith('inline;');
});

it('makes a PDF with the stamp image in it', function () {
    Storage::fake('local');
    Storage::disk('local')->put('company-stamps/s.png', base64_decode(TINY_PNG));

    $bytes = app(QuotationPdf::class)->bytes(pdfQuotation(['letterhead' => ['name' => 'CMT', 'stamp_path' => 'company-stamps/s.png']]));

    expect(substr($bytes, 0, 4))->toBe('%PDF')->and($bytes)->toContain('/Image');
});

it('explains when the PDF could not be made', function () {
    $this->actingAs(User::factory()->create());
    $this->mock(QuotationPdf::class, fn ($m) => $m->shouldReceive('bytes')->andThrow(new RuntimeException('boom')));

    $this->get(route('quotations.pdf', pdfQuotation()))->assertStatus(500)->assertSee('The PDF could not be made');
});

it('needs a signed-in user', function () {
    $this->get(route('quotations.pdf', pdfQuotation()))->assertRedirect(route('login'));
});

it('writes the typed signature in the Allura handwriting font, stored with the app', function () {
    $pdf = app(QuotationPdf::class)->bytes(pdfQuotation());

    expect(str_contains($pdf, 'Allura'))->toBeTrue('the signature font is not in the PDF')
        ->and(str_contains(app(QuotationPdf::class)->bytes(pdfQuotation(['number' => 'QTN-2026-0015', 'show_signature' => false])), 'Allura'))->toBeFalse();
});

it('shows frequency only when needed, marks SST items and never shows costs', function () {
    $q = pdfQuotation();
    $q->items[0]->update(['frequency' => 12, 'unit_cost_sen' => 777700, 'vendor' => 'SecretVendor', 'margin_bp' => 1234]);
    $q->items[1]->update(['has_sst' => false]);

    $html = app(QuotationPdf::class)->html($q->fresh());
    expect($html)->toContain('Freq.')->toContain('<td class="right">12</td>')->toContain('on items marked *')
        ->toContain('349,200.00*</td>')->toContain('6,500.00</td>')->not->toContain('6,500.00*')
        ->not->toContain('SecretVendor')->not->toContain('7,777.00')->not->toContain('12.3%');

    $q->items[0]->update(['frequency' => 1]);
    expect(app(QuotationPdf::class)->html($q->fresh()))->not->toContain('Freq.');
});

it('has no customer "Accepted by" box, only the Prepared by signature', function () {
    $html = app(QuotationPdf::class)->html(pdfQuotation());

    expect($html)->toContain('Prepared by,')->not->toContain('Accepted by')->not->toContain('Name, signature &amp; company stamp');
});
