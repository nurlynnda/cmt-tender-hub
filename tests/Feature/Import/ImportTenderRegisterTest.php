<?php

use App\Enums\{TenderCategory, TenderStatus};
use App\Import\RegisterImporter;
use App\Models\{Tender, TenderDocument, User};

beforeEach(fn () => User::factory()->admin()->create(['email' => 'admin@cmt.test']));

function registerFixture(): string
{
    return base_path('tests/Fixtures/register/register-sample.csv');
}

it('previews without saving anything', function () {
    $this->artisan('tenders:import-register', ['file' => registerFixture()])
        ->expectsOutputToContain('7 tenders ready')
        ->expectsOutputToContain('In Progress: 2')
        ->expectsOutputToContain('Dropped: 1')
        ->expectsOutputToContain("line 9 (200-01012026-008): unknown status 'Pending'")
        ->expectsOutputToContain('New switched-off accounts: Aminah, Badrul, Unassigned')
        ->expectsOutputToContain('Preview only — nothing saved')
        ->assertSuccessful();

    expect(Tender::count())->toBe(0)->and(User::count())->toBe(1);
});

it('imports every usable row into the right list with its details', function () {
    $this->artisan('tenders:import-register', ['file' => registerFixture(), '--commit' => true])->assertSuccessful();

    expect(Tender::where('status', TenderStatus::InProgress)->count())->toBe(2)
        ->and(Tender::where('status', TenderStatus::Done)->count())->toBe(1)
        ->and(Tender::where('status', TenderStatus::Awarded)->count())->toBe(1)
        ->and(Tender::where('status', TenderStatus::Lost)->count())->toBe(2)
        ->and(Tender::where('status', TenderStatus::Dropped)->count())->toBe(1)
        ->and(Tender::where('was_cancelled', true)->count())->toBe(1);

    $one = Tender::where('wo_number', '200-01012026-001')->first();
    expect($one->ministry)->toBe('KEMENTERIAN CONTOH')->and($one->client)->toBe('JABATAN SATU')
        ->and($one->category)->toBe(TenderCategory::General)
        ->and($one->has_briefing)->toBeTrue()->and($one->briefing_date->toDateString())->toBe('2026-01-10')
        ->and($one->documents()->count())->toBe(count(TenderDocument::STANDARD))
        ->and($one->activity()->first()->description)->toBe('Imported from the 2026 register');

    $won = Tender::where('wo_number', '200-01012026-004')->first();
    expect($won->awarded_at->toDateString())->toBe('2026-01-23')->and($won->documents()->count())->toBe(0);
    expect(Tender::where('wo_number', '200-01012026-007')->first()->dropped_at->toDateString())->toBe('2026-01-26');
});

it('creates one switched-off account per person, whatever the capitals, and Unassigned for blanks', function () {
    $this->artisan('tenders:import-register', ['file' => registerFixture(), '--commit' => true])->assertSuccessful();

    $aminah = User::where('name', 'Aminah')->sole();
    expect($aminah->is_active)->toBeFalse()->and($aminah->email)->toBe('aminah@import.invalid')
        ->and(Tender::where('pic_id', $aminah->id)->count())->toBe(3)   // "Aminah" and "aminah "
        ->and(Tender::where('wo_number', '200-01012026-005')->first()->pic->name)->toBe('Unassigned');
});

it('turns the submitted cost into one costing line so Gross matches the sheet', function () {
    $this->artisan('tenders:import-register', ['file' => registerFixture(), '--commit' => true])->assertSuccessful();

    $t = Tender::where('wo_number', '200-01012026-003')->first();
    expect($t->costingLines)->toHaveCount(1)
        ->and($t->costingLines->first()->description)->toBe(RegisterImporter::IMPORTED_COST)
        ->and($t->costingSummary()['margin_bp'])->toBe(2000);                       // (400k - 320k) / 400k = 20%
    expect(Tender::where('wo_number', '200-01012026-004')->first()->costingLines)->toHaveCount(0); // price but no cost
});

it('updates on a re-run instead of duplicating, leaving what people changed', function () {
    $this->artisan('tenders:import-register', ['file' => registerFixture(), '--commit' => true])->assertSuccessful();
    $t = Tender::where('wo_number', '200-01012026-003')->first();
    $t->forceFill(['category' => TenderCategory::CivilWorks])->save();
    $t->costingLines()->create(['position' => 9, 'description' => 'Added by hand', 'unit_cost_sen' => 100]);
    $first = Tender::where('wo_number', '200-01012026-001')->first();
    $first->documents()->first()->forceFill(['is_done' => true])->save();

    $this->artisan('tenders:import-register', ['file' => registerFixture(), '--commit' => true])->assertSuccessful();

    expect(Tender::count())->toBe(7)
        ->and($t->fresh()->category)->toBe(TenderCategory::CivilWorks)
        ->and($t->fresh()->costingLines->pluck('description')->all())->toContain('Added by hand')
        ->and($t->fresh()->costingLines->where('description', RegisterImporter::IMPORTED_COST))->toHaveCount(1)
        ->and($first->fresh()->documents()->where('is_done', true)->count())->toBe(1)
        ->and($t->fresh()->activity()->first()->description)->toBe('Updated from the register');
});

it('saves nothing if any row fails part-way', function () {
    $path = tempnam(sys_get_temp_dir(), 'reg');
    $csv = file_get_contents(registerFixture());
    // a WO number longer than the column allows makes the database refuse that row
    file_put_contents($path, str_replace('200-01012026-007', '200-01012026-007-THIS-IS-FAR-TOO-LONG', $csv));

    $this->artisan('tenders:import-register', ['file' => $path, '--commit' => true])
        ->expectsOutputToContain('Nothing was saved')->assertFailed();

    expect(Tender::count())->toBe(0)->and(User::count())->toBe(1);
});

it('stops with a plain message for a file that is not the register', function () {
    $path = tempnam(sys_get_temp_dir(), 'reg');
    file_put_contents($path, "Name,Amount\nX,1\n");

    $this->artisan('tenders:import-register', ['file' => $path, '--commit' => true])
        ->expectsOutputToContain('missing columns')->assertFailed();
});

it('replaces the samples but keeps the admin, the manager and Find Tenders data', function () {
    $manager = User::factory()->manager()->create(['email' => 'manager@cmt.test']);
    $sampleStaff = User::factory()->create(['name' => 'Sample Person']);
    $sample = Tender::factory()->create(['pic_id' => $sampleStaff->id]);
    $quote = \App\Models\Quotation::factory()->create(['prepared_by' => $sampleStaff->id]);
    \App\Models\Project::factory()->create(['tender_id' => null, 'quotation_id' => $quote->id]);   // a project on a quotation
    $entry = \App\Models\PdEntry::factory()->create();                                           // money entry on a sample tender's PD
    $collected = \App\Models\CollectedTender::factory()->create();
    $runner = User::factory()->create(['name' => 'Ran A Collection']);
    \App\Models\CollectionRun::create(['trigger' => 'manual', 'scope' => 'open', 'started_by' => $runner->id, 'status' => 'done', 'started_at' => now()]);

    $this->artisan('tenders:import-register', ['file' => registerFixture(), '--replace-samples' => true])
        ->expectsOutputToContain('Will remove sample tenders: 2')->assertSuccessful();
    expect(Tender::count())->toBe(2); // the preview removed nothing

    $this->artisan('tenders:import-register', ['file' => registerFixture(), '--commit' => true, '--replace-samples' => true])
        ->expectsOutputToContain('Removed quotations: 1')->assertSuccessful();

    expect(Tender::find($sample->id))->toBeNull()
        ->and(\App\Models\Quotation::count())->toBe(0)
        ->and(\App\Models\Project::count())->toBe(0)
        ->and(\App\Models\PdEntry::find($entry->id))->toBeNull()
        ->and(User::find($sampleStaff->id))->toBeNull()
        ->and(User::find($runner->id)->is_active)->toBeFalse()           // still referenced by a collection run
        ->and(User::where('email', 'admin@cmt.test')->exists())->toBeTrue()
        ->and($manager->fresh())->not->toBeNull()
        ->and(\App\Models\CollectedTender::find($collected->id))->not->toBeNull()
        ->and(Tender::count())->toBe(7);
});

it('replaces a sample tender that shares a WO number with the register, instead of keeping its made-up details', function () {
    $sampleStaff = User::factory()->create(['name' => 'Sample Person']);
    $sample = Tender::factory()->create(['wo_number' => '200-01012026-001', 'pic_id' => $sampleStaff->id, 'category' => TenderCategory::CivilWorks]);
    $sample->documents()->create(['name' => 'Sample doc', 'position' => 1, 'is_done' => true]);
    $sample->costingLines()->create(['position' => 1, 'description' => 'Sample costing', 'unit_cost_sen' => 5]);
    \App\Models\ActivityLog::record($sample, $sampleStaff, 'registered', 'Sample history');

    $this->artisan('tenders:import-register', ['file' => registerFixture(), '--commit' => true, '--replace-samples' => true])->assertSuccessful();

    $real = Tender::where('wo_number', '200-01012026-001')->sole();
    expect($real->id)->not->toBe($sample->id)
        ->and($real->category)->toBe(TenderCategory::General)
        ->and($real->documents()->pluck('name')->all())->toBe(TenderDocument::STANDARD)
        ->and($real->costingLines)->toHaveCount(0)
        ->and($real->activity()->pluck('description')->all())->toBe(['Imported from the 2026 register'])
        ->and(User::find($sampleStaff->id))->toBeNull();
});

function registerVariant(array $replace): string
{
    $path = tempnam(sys_get_temp_dir(), 'reg');
    file_put_contents($path, strtr(file_get_contents(registerFixture()), $replace));

    return $path;
}

it('moves the WO-number counter past imported numbers, so registering in the app never clashes', function () {
    $this->artisan('tenders:import-register', ['file' => registerFixture(), '--commit' => true])->assertSuccessful();

    expect(app(\App\Actions\Tenders\GenerateWoNumber::class)->next(\Carbon\CarbonImmutable::parse('2026-01-01')))->toBe('200-01012026-008');
});

it('refuses to replace samples once the register has been imported', function () {
    $this->artisan('tenders:import-register', ['file' => registerFixture(), '--commit' => true])->assertSuccessful();

    $this->artisan('tenders:import-register', ['file' => registerFixture(), '--commit' => true, '--replace-samples' => true])
        ->expectsOutputToContain('already been imported')->assertFailed();

    expect(Tender::count())->toBe(7)->and(User::where('email', 'like', '%@import.invalid')->count())->toBe(3);
});

it('keeps progress made in the app since the last import, and takes newer sheet statuses otherwise', function () {
    $this->artisan('tenders:import-register', ['file' => registerFixture(), '--commit' => true])->assertSuccessful();
    $manager = User::factory()->manager()->create();
    $done = Tender::where('wo_number', '200-01012026-003')->first();                     // sheet: Submitted
    app(\App\Actions\Tenders\MarkTenderAwarded::class)->handle($manager, $done, $done->version);
    $open = Tender::where('wo_number', '200-01012026-002')->first();                     // sheet: Assigned, untouched in the app

    $path = registerVariant([',Assigned,' => ',Submitted,']);                              // the sheet moved 002 on
    $this->artisan('tenders:import-register', ['file' => $path])
        ->expectsOutputToContain('Changed in the app since the last import — status kept: 200-01012026-003')->assertSuccessful();
    $this->artisan('tenders:import-register', ['file' => $path, '--commit' => true])->assertSuccessful();

    expect($done->fresh()->status)->toBe(TenderStatus::Awarded)
        ->and($open->fresh()->status)->toBe(TenderStatus::Done);
});

it('finds a renamed imported account again instead of making a duplicate', function () {
    $this->artisan('tenders:import-register', ['file' => registerFixture(), '--commit' => true])->assertSuccessful();
    $aminah = User::where('name', 'Aminah')->sole();
    $aminah->forceFill(['name' => 'Aminah binti Ali', 'email' => 'aminah@cmt.my', 'is_active' => true])->save();

    $this->artisan('tenders:import-register', ['file' => registerFixture(), '--commit' => true])->assertSuccessful();

    expect(User::where('name', 'Aminah')->exists())->toBeFalse()
        ->and(Tender::where('pic_id', $aminah->id)->count())->toBe(3);
});

it('gives two people whose names make the same email address separate accounts', function () {
    $this->artisan('tenders:import-register', ['file' => registerVariant([',Aminah,' => ',Siti A.,', ',Badrul,' => ',Siti A,']), '--commit' => true])
        ->assertSuccessful();

    expect(User::where('name', 'Siti A.')->sole()->email)->toBe('siti-a@import.invalid')
        ->and(User::where('name', 'Siti A')->sole()->email)->toBe('siti-a-2@import.invalid');
});

it('clears the imported bid price when a later sheet no longer has the cost', function () {
    $this->artisan('tenders:import-register', ['file' => registerFixture(), '--commit' => true])->assertSuccessful();

    $this->artisan('tenders:import-register', ['file' => registerVariant(['"320,000.00"' => '']), '--commit' => true])->assertSuccessful();

    $t = Tender::where('wo_number', '200-01012026-003')->first();
    expect($t->costingLines)->toHaveCount(0)->and($t->bid_price_override_sen)->toBeNull();
});
