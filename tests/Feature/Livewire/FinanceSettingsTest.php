<?php

use App\Actions\Pd\SaveFinanceDefaults;
use App\Livewire\FinanceSettings;
use App\Models\{FinanceSetting, Project, ProjectType, User};
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;

it('is for admins only', function () {
    $this->actingAs(User::factory()->manager()->create())->get(route('finance.settings'))->assertForbidden();
    $this->actingAs(User::factory()->admin()->create())->get(route('finance.settings'))
        ->assertOk()->assertSee('Managed Services')->assertSee('Finance Settings');
});

it('adds, edits and switches off project types', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)->test(FinanceSettings::class)
        ->set('typeName', 'Cloud Services')->set('typeMargin', '18.5')->call('saveType')
        ->assertHasNoErrors()->assertSee('Project type saved.');
    $type = ProjectType::where('name', 'Cloud Services')->first();
    expect($type->approved_margin_bp)->toBe(1850);

    Livewire::actingAs($admin)->test(FinanceSettings::class)
        ->call('editType', $type->id)->assertSet('typeName', 'Cloud Services')->assertSet('typeMargin', '18.5')
        ->set('typeMargin', '20')->call('saveType')
        ->call('toggleType', $type->id)
        ->call('editType', $type->id)->call('cancelType')->assertSet('editingType', null);
    expect($type->fresh()->approved_margin_bp)->toBe(2000)->and($type->fresh()->is_active)->toBeFalse();
});

it('validates names and margins', function () {
    Livewire::actingAs(User::factory()->admin()->create())->test(FinanceSettings::class)
        ->set('typeName', 'Managed Services')->set('typeMargin', '100')->call('saveType')
        ->assertHasErrors(['typeName', 'typeMargin']);
});

it('deletes unused types only', function () {
    $admin = User::factory()->admin()->create();
    $used = ProjectType::where('name', 'Networking')->first();
    Project::factory()->create(['project_type_id' => $used->id]);
    $unused = ProjectType::where('name', 'Audio Visual')->first();

    Livewire::actingAs($admin)->test(FinanceSettings::class)
        ->call('deleteType', $used->id)->assertSee('Networking is used by 1 project — switch it off instead.')
        ->call('deleteType', $unused->id);

    expect(ProjectType::find($used->id))->not->toBeNull()->and(ProjectType::find($unused->id))->toBeNull();
});

it('saves the company defaults', function () {
    Livewire::actingAs(User::factory()->admin()->create())->test(FinanceSettings::class)
        ->assertSet('charge', '9')->assertSet('share', '50')
        ->set('charge', '8.5')->set('share', '45')->call('saveDefaults')->assertHasNoErrors()
        ->assertSee('Saved. New projects will use these numbers.');

    expect(FinanceSetting::current()->project_charge_bp)->toBe(850)->and(FinanceSetting::current()->commission_share_bp)->toBe(4500);
});

it('refuses crafted calls from non-admins', function () {
    expect(fn () => app(SaveFinanceDefaults::class)->handle(User::factory()->manager()->create(), 100, 100))
        ->toThrow(AuthorizationException::class);
});

it('links to the page from the sidebar for admins only', function () {
    $this->actingAs(User::factory()->admin()->create())->get(route('settings'))->assertSee(route('finance.settings'));
    $this->actingAs(User::factory()->manager()->create())->get(route('settings'))->assertDontSee(route('finance.settings'));
});

it('saves the company names used to spot our wins, one per line', function () {
    Livewire::actingAs(User::factory()->admin()->create())->test(FinanceSettings::class)
        ->assertSet('ownNames', '10 CREATIVE SOLUTIONS SDN BHD')
        ->set('ownNames', "10 Creative Solutions Sdn. Bhd.\n\n10 CREATIVE SOLUTIONS SDN BHD\nCMT TECH")->call('saveOwnNames')
        ->assertSee('Saved. Market Insights and Find Tenders will mark these names as ours.');

    expect(\App\Market\OwnCompany::keys())->toBe(['10 CREATIVE SOLUTIONS SDN BHD', 'CMT TECH'])
        ->and(\App\Market\OwnCompany::label())->toBe('10 Creative Solutions Sdn. Bhd.');
});

it('labels an all-capitals company name in title case, and falls back when none is set', function () {
    expect(\App\Market\OwnCompany::label())->toBe('10 Creative Solutions Sdn Bhd');

    FinanceSetting::current()->update(['own_company_names' => null]);
    expect(\App\Market\OwnCompany::label())->toBe('Our company')->and(\App\Market\OwnCompany::keys())->toBe([]);
});
