<?php

use App\Enums\{TenderCategory, TenderStatus};
use App\Import\RegisterImporter;
use App\Models\{Tender, TenderDocument, User};

beforeEach(fn () => User::factory()->admin()->create(['email' => 'admin@cmt.test']));

function registerFixture(): string
{
    return base_path('tests/fixtures/register/register-sample.csv');
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
