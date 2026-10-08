<?php

use App\Actions\Pd\{CloseProject, ReopenProject, UpdateProjectDetails, UpdateProjectRates};
use App\Enums\TenderStatus;
use App\Exceptions\{ProjectLocked, StalePdRecord};
use App\Models\{Project, ProjectType, Tender, User};
use Illuminate\Auth\Access\AuthorizationException;

function picProject(): array
{
    $pic = User::factory()->create(['name' => 'Siti Aisyah']);
    $tender = Tender::factory()->status(TenderStatus::Awarded)->create(['pic_id' => $pic->id]);

    return [$pic, Project::factory()->for($tender)->create(['approved_margin_bp' => 0])];
}

it('sets the project type, copying its approved margin, and the dates', function () {
    [$pic, $project] = picProject();
    $type = ProjectType::where('name', 'Networking')->first();

    $p = app(UpdateProjectDetails::class)->handle($pic, $project, 1, ['project_type_id' => $type->id, 'start_date' => '2026-01-01', 'end_date' => '2026-06-30']);

    expect($p->approved_margin_bp)->toBe(2000)
        ->and($p->start_date->format('Y-m-d'))->toBe('2026-01-01')
        ->and($p->version)->toBe(2)
        ->and($p->tender->activity->first()->description)->toBe('Project details updated — Networking, 01 Jan 2026 to 30 Jun 2026');
});

it('keeps a manually adjusted margin when only the dates change', function () {
    [$pic, $project] = picProject();
    $type = ProjectType::where('name', 'Networking')->first();
    $project->update(['project_type_id' => $type->id, 'approved_margin_bp' => 1234]);

    $p = app(UpdateProjectDetails::class)->handle($pic, $project, 1, ['project_type_id' => $type->id, 'start_date' => null, 'end_date' => null]);

    expect($p->approved_margin_bp)->toBe(1234)
        ->and($p->tender->activity->first()->description)->toBe('Project details updated — Networking, — to —');
});

it('rejects an end date before the start date and switched-off types', function () {
    [$pic, $project] = picProject();
    $off = ProjectType::create(['name' => 'Old type', 'approved_margin_bp' => 100, 'is_active' => false]);

    expect(fn () => app(UpdateProjectDetails::class)->handle($pic, $project, 1, ['project_type_id' => null, 'start_date' => '2026-02-01', 'end_date' => '2026-01-01']))
        ->toThrow(InvalidArgumentException::class, 'The end date must be on or after the start date.')
        ->and(fn () => app(UpdateProjectDetails::class)->handle($pic, $project, 1, ['project_type_id' => $off->id, 'start_date' => null, 'end_date' => null]))
        ->toThrow(InvalidArgumentException::class, 'That project type is switched off.');
});

it('lets only managers and admins change the rates', function () {
    [$pic, $project] = picProject();
    $manager = User::factory()->manager()->create();

    expect(fn () => app(UpdateProjectRates::class)->handle($pic, $project, 1, 1500, 900, 5000))->toThrow(AuthorizationException::class)
        ->and(fn () => app(UpdateProjectRates::class)->handle($manager, $project, 1, 10000, 900, 5000))->toThrow(InvalidArgumentException::class);

    $p = app(UpdateProjectRates::class)->handle($manager, $project, 1, 1500, 1000, 4000);
    expect([$p->approved_margin_bp, $p->project_charge_bp, $p->commission_share_bp])->toBe([1500, 1000, 4000])
        ->and($p->tender->activity->first()->description)->toBe('Project rates changed — approved margin 15.0%, project charges 10.0%, commission share 40.0%');
});

it('closes and reopens a project, refusing edits while closed', function () {
    [$pic, $project] = picProject();
    $manager = User::factory()->manager()->create(['name' => 'Ahmad Faizal']);

    expect(fn () => app(CloseProject::class)->handle($pic, $project, 1))->toThrow(AuthorizationException::class);

    $closed = app(CloseProject::class)->handle($manager, $project, 1);
    expect($closed->isOpen())->toBeFalse()->and($closed->closed_by)->toBe($manager->id);

    expect(fn () => app(UpdateProjectDetails::class)->handle($pic, $closed, 2, ['project_type_id' => null, 'start_date' => null, 'end_date' => null]))
        ->toThrow(ProjectLocked::class, 'This project is closed. A Manager can reopen it.')
        ->and(fn () => app(UpdateProjectRates::class)->handle($manager, $closed, 2, 1, 1, 1))->toThrow(ProjectLocked::class)
        ->and(fn () => app(CloseProject::class)->handle($manager, $closed, 2))->toThrow(ProjectLocked::class);

    $open = app(ReopenProject::class)->handle($manager, $closed, 2);
    expect($open->isOpen())->toBeTrue()->and($open->tender->activity->first()->description)->toBe('Project reopened')
        ->and(fn () => app(ReopenProject::class)->handle($manager, $open, 3))->toThrow(InvalidArgumentException::class, 'This project is already open.');
});

it('refuses changes from an out-of-date page, naming who changed it', function () {
    [$pic, $project] = picProject();
    $manager = User::factory()->manager()->create(['name' => 'Ahmad Faizal']);
    app(UpdateProjectRates::class)->handle($manager, $project, 1, 1500, 900, 5000);

    app(UpdateProjectDetails::class)->handle($pic, $project, 1, ['project_type_id' => null, 'start_date' => null, 'end_date' => null]);
})->throws(StalePdRecord::class, 'This project was changed by Ahmad Faizal — reload to see their changes.');

it('refuses changes once the tender is no longer Awarded, and from staff who are not the PIC', function () {
    [$pic, $project] = picProject();
    $data = ['project_type_id' => null, 'start_date' => null, 'end_date' => null];

    expect(fn () => app(UpdateProjectDetails::class)->handle(User::factory()->create(), $project, 1, $data))->toThrow(AuthorizationException::class);

    $project->tender->update(['status' => TenderStatus::InProgress]);
    expect(fn () => app(UpdateProjectDetails::class)->handle($pic, $project, 1, $data))
        ->toThrow(ProjectLocked::class, 'This project is on hold because its tender is no longer Awarded.');
});
