<?php

use App\Actions\Pd\{AddPdLine, UpdateProjectRates};
use App\Enums\{PdGroup, QuotationStatus};
use App\Exceptions\ProjectLocked;
use App\Livewire\ProjectPd;
use App\Models\{Project, Quotation, User};
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;

function quotationProject(QuotationStatus $status = QuotationStatus::Accepted): array
{
    $preparer = User::factory()->create();
    $q = Quotation::factory()->create(['prepared_by' => $preparer->id, 'status' => $status]);

    return [$preparer, $q, Project::factory()->create(['tender_id' => null, 'quotation_id' => $q->id])];
}

it('lets the preparer work on an accepted quotation\'s project, logging on the quotation', function () {
    [$preparer, $q, $project] = quotationProject();

    app(AddPdLine::class)->handle($preparer, $project, PdGroup::Principal);

    expect($project->owner()->is($q))->toBeTrue()
        ->and($project->isActive())->toBeTrue()
        ->and($project->pdUrl())->toBe(route('quotations.pd', $q))
        ->and($q->activity()->first()->description)->toBe('PD line added — Principal');
});

it('refuses other staff, and everyone once the quotation is no longer accepted', function () {
    [, , $project] = quotationProject();
    expect(fn () => app(AddPdLine::class)->handle(User::factory()->create(), $project, PdGroup::Tax))->toThrow(AuthorizationException::class);

    [$preparer2, , $sentProject] = quotationProject(QuotationStatus::Sent);
    expect(fn () => app(AddPdLine::class)->handle($preparer2, $sentProject, PdGroup::Tax))
        ->toThrow(ProjectLocked::class, 'This project is on hold because its quotation is no longer Accepted.')
        ->and(fn () => app(UpdateProjectRates::class)->handle(User::factory()->manager()->create(), $sentProject, 1, 1, 1, 1))
        ->toThrow(ProjectLocked::class);
});

it('shows the PD screen for a quotation project, on its own page', function () {
    [$preparer, $q, $project] = quotationProject();

    Livewire::actingAs($preparer)->test(ProjectPd::class, ['project' => $project])
        ->assertSee('Profit & Loss')->assertSee('+ Add line');

    $this->actingAs($preparer)->get(route('quotations.pd', $q))->assertOk()->assertSee('Back to '.$q->number);
    $this->actingAs($preparer)->get(route('quotations.pd', Quotation::factory()->create()))->assertNotFound();
});

it('still links tender projects to the tender page', function () {
    $project = Project::factory()->create();

    expect($project->pdUrl())->toBe(route('tenders.show', $project->tender).'?tab=pd')
        ->and($project->owner()->is($project->tender))->toBeTrue();
});
