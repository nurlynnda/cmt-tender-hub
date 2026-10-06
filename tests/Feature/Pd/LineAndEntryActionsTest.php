<?php

use App\Actions\Pd\{AddPdLine, RemovePdEntry, RemovePdLine, SavePdEntry, UpdatePdLine};
use App\Enums\{PdGroup, TenderStatus};
use App\Exceptions\{ProjectLocked, StalePdRecord};
use App\Models\{PdEntry, PdLine, Project, Tender, User};
use Illuminate\Auth\Access\AuthorizationException;

function pdFixture(): array
{
    $pic = User::factory()->create(['name' => 'Siti Aisyah']);
    $tender = Tender::factory()->status(TenderStatus::Awarded)->create(['pic_id' => $pic->id]);

    return [$pic, Project::factory()->for($tender)->create()];
}

function entryData(array $o = []): array
{
    return array_merge(['type' => 'invoice', 'number' => 'INV-0012', 'date' => '2026-02-01', 'amount_sen' => 5000000, 'note' => null], $o);
}

it('adds, edits and removes a line, logging each change', function () {
    [$pic, $project] = pdFixture();

    $line = app(AddPdLine::class)->handle($pic, $project, PdGroup::Principal);
    expect($line->name)->toBe('New line')->and($line->budget_sen)->toBe(0)->and($line->version)->toBe(1)
        ->and($project->tender->activity()->first()->description)->toBe('PD line added — Principal');

    $line = app(UpdatePdLine::class)->handle($pic, $line, 1, ['name' => 'Dell laptops', 'reference' => 'Q-123', 'budget_sen' => 9000000, 'scheduled_date' => '2026-03-01']);
    expect($line->version)->toBe(2)->and($line->budget_sen)->toBe(9000000)->and($line->reference)->toBe('Q-123')
        ->and($line->scheduled_date)->toBeNull()   // only Collection lines have a scheduled date
        ->and($project->tender->activity()->first()->description)->toBe('PD line updated — Principal: Dell laptops, budget RM 90,000.00');

    app(RemovePdLine::class)->handle($pic, $line, 2);
    expect(PdLine::count())->toBe(0)->and($project->tender->activity()->first()->description)->toBe('PD line removed — Principal: Dell laptops');
});

it('keeps the scheduled date on collection lines and refuses a blank name', function () {
    [$pic, $project] = pdFixture();
    $line = PdLine::factory()->for($project)->create(['pd_group' => PdGroup::Collection]);

    $saved = app(UpdatePdLine::class)->handle($pic, $line, 1, ['name' => 'Payment 1', 'reference' => null, 'budget_sen' => 0, 'scheduled_date' => '2026-03-01']);
    expect($saved->scheduled_date->format('Y-m-d'))->toBe('2026-03-01');

    app(UpdatePdLine::class)->handle($pic, $saved, 2, ['name' => '  ', 'reference' => null, 'budget_sen' => 0, 'scheduled_date' => null]);
})->throws(InvalidArgumentException::class, 'A line needs a name and a budget of RM 0.00 or more.');

it('appends new lines at the end', function () {
    [$pic, $project] = pdFixture();
    PdLine::factory()->for($project)->create(['position' => 7]);

    expect(app(AddPdLine::class)->handle($pic, $project, PdGroup::Tax)->position)->toBe(8);
});

it('records, edits and removes document entries', function () {
    [$pic, $project] = pdFixture();
    $line = PdLine::factory()->for($project)->create(['name' => 'Dell laptops', 'pd_group' => PdGroup::Principal]);

    $entry = app(SavePdEntry::class)->handle($pic, $line, 1, null, entryData());
    expect($entry->amount_sen)->toBe(5000000)->and($entry->created_by)->toBe($pic->id)->and($line->fresh()->version)->toBe(2)
        ->and($project->tender->activity()->first()->description)->toBe('Invoice INV-0012 RM 50,000.00 recorded on Principal: Dell laptops');

    app(SavePdEntry::class)->handle($pic, $line->fresh(), 2, $entry, entryData(['type' => 'payment', 'amount_sen' => 2000000, 'number' => null]));
    expect($entry->fresh()->type->value)->toBe('payment')
        ->and($project->tender->activity()->first()->description)->toBe('Payment RM 20,000.00 updated on Principal: Dell laptops');

    app(RemovePdEntry::class)->handle($pic, $entry->fresh(), 3);
    expect(PdEntry::count())->toBe(0)->and($line->fresh()->version)->toBe(4)
        ->and($project->tender->activity()->first()->description)->toBe('Payment RM 20,000.00 removed from Principal: Dell laptops');
});

it('only accepts entry types that suit the line', function () {
    [$pic, $project] = pdFixture();
    $cost = PdLine::factory()->for($project)->create(['pd_group' => PdGroup::Principal]);
    $collection = PdLine::factory()->for($project)->create(['pd_group' => PdGroup::Collection]);

    expect(fn () => app(SavePdEntry::class)->handle($pic, $cost, 1, null, entryData(['type' => 'receipt'])))
        ->toThrow(InvalidArgumentException::class, 'Principal lines take PR, PO, Invoice and Payment entries only.')
        ->and(fn () => app(SavePdEntry::class)->handle($pic, $collection, 1, null, entryData(['type' => 'po'])))
        ->toThrow(InvalidArgumentException::class, 'Collection lines take Invoice and Receipt entries only.')
        ->and(fn () => app(SavePdEntry::class)->handle($pic, $cost, 1, null, entryData(['type' => 'bogus'])))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => app(SavePdEntry::class)->handle($pic, $cost, 1, null, entryData(['amount_sen' => 0])))
        ->toThrow(InvalidArgumentException::class, 'The amount must be more than RM 0.00.');
});

it('will not remove a line that still has entries', function () {
    [$pic, $project] = pdFixture();
    $line = PdLine::factory()->for($project)->create();
    PdEntry::factory()->for($line, 'line')->create();

    app(RemovePdLine::class)->handle($pic, $line, 1);
})->throws(DomainException::class, "Remove this line's documents first.");

it('only conflicts when the same line was changed by someone else', function () {
    [$pic, $project] = pdFixture();
    $manager = User::factory()->manager()->create(['name' => 'Ahmad Faizal']);
    $a = PdLine::factory()->for($project)->create(['name' => 'A']);
    $b = PdLine::factory()->for($project)->create(['name' => 'B']);

    app(SavePdEntry::class)->handle($manager, $a, 1, null, entryData());
    app(SavePdEntry::class)->handle($pic, $b, 1, null, entryData());   // different line: fine

    expect(fn () => app(UpdatePdLine::class)->handle($pic, $a, 1, ['name' => 'A2', 'reference' => null, 'budget_sen' => 0, 'scheduled_date' => null]))
        ->toThrow(StalePdRecord::class, 'This line was changed by Ahmad Faizal — reload to see their changes.');
});

it('refuses every change on a closed project or by staff who are not the PIC', function () {
    [$pic, $project] = pdFixture();
    $line = PdLine::factory()->for($project)->create();
    $stranger = User::factory()->create();

    expect(fn () => app(AddPdLine::class)->handle($stranger, $project, PdGroup::Tax))->toThrow(AuthorizationException::class)
        ->and(fn () => app(SavePdEntry::class)->handle($stranger, $line, 1, null, entryData()))->toThrow(AuthorizationException::class);

    $project->update(['closed_at' => now()]);
    expect(fn () => app(AddPdLine::class)->handle($pic, $project, PdGroup::Tax))->toThrow(ProjectLocked::class)
        ->and(fn () => app(SavePdEntry::class)->handle($pic, $line, 1, null, entryData()))->toThrow(ProjectLocked::class)
        ->and(fn () => app(RemovePdLine::class)->handle($pic, $line, 1))->toThrow(ProjectLocked::class);
});

it('does not let an entry be moved through a different line', function () {
    [$pic, $project] = pdFixture();
    $a = PdLine::factory()->for($project)->create();
    $b = PdLine::factory()->for($project)->create();
    $entry = PdEntry::factory()->for($a, 'line')->create();

    app(SavePdEntry::class)->handle($pic, $b, 1, $entry, entryData());
})->throws(InvalidArgumentException::class, 'That document belongs to a different line.');
