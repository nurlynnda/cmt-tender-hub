<?php

use App\Actions\Company\SaveCompanyProfile;
use App\Livewire\FinanceSettings;
use App\Models\{CompanyProfile, User};
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

const STAMP_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

it('edits the letterhead, default terms and SST', function () {
    Livewire::actingAs(User::factory()->admin()->create())->test(FinanceSettings::class)
        ->assertSet('company.name', 'CMT Sdn. Bhd.')->assertSet('company.sst', '8')
        ->set('company.sst_no', 'W10-1808-32000001')->set('company.sst', '6')->set('company.default_terms', "One\nTwo")
        ->set('company.website', '')
        ->call('saveCompany')->assertHasNoErrors()->assertSee('Letterhead saved. New quotations will use it.');

    $c = CompanyProfile::current();
    expect($c->sst_no)->toBe('W10-1808-32000001')->and($c->default_sst_bp)->toBe(600)
        ->and($c->default_terms)->toBe("One\nTwo")->and($c->website)->toBeNull();
});

it('validates the letterhead', function () {
    Livewire::actingAs(User::factory()->admin()->create())->test(FinanceSettings::class)
        ->set('company.name', '')->set('company.email', 'nope')->set('company.sst', '100')
        ->call('saveCompany')->assertHasErrors(['company.name', 'company.email', 'company.sst']);
});

it('uploads, shows and removes the stamp, keeping old files', function () {
    Storage::fake('local');
    $admin = User::factory()->admin()->create();
    $c = Livewire::actingAs($admin)->test(FinanceSettings::class)
        ->set('stamp', UploadedFile::fake()->createWithContent('stamp.png', base64_decode(STAMP_PNG)))
        ->call('uploadStamp')->assertHasNoErrors()->assertSee('Stamp saved. New quotations will use it.');
    $path = CompanyProfile::current()->stamp_path;
    expect($path)->toStartWith('company-stamps/')->and(Storage::disk('local')->exists($path))->toBeTrue();

    $this->actingAs($admin)->get(route('company.stamp'))->assertOk()->assertHeader('Content-Type', 'image/png');

    $c->call('removeStamp')->assertSee('Stamp removed.');
    expect(CompanyProfile::current()->stamp_path)->toBeNull()->and(Storage::disk('local')->exists($path))->toBeTrue();
    $this->actingAs($admin)->get(route('company.stamp'))->assertNotFound();
});

it('refuses files that are not a small PNG or JPG', function () {
    Livewire::actingAs(User::factory()->admin()->create())->test(FinanceSettings::class)
        ->set('stamp', UploadedFile::fake()->create('stamp.pdf', 10, 'application/pdf'))
        ->call('uploadStamp')->assertHasErrors('stamp')->assertSee('Upload a PNG or JPG image of 1 MB or less.');
});

it('keeps the letterhead for admins only', function () {
    expect(fn () => app(SaveCompanyProfile::class)->handle(User::factory()->manager()->create(), ['name' => 'X']))
        ->toThrow(AuthorizationException::class);
    $this->actingAs(User::factory()->manager()->create())->get(route('company.stamp'))->assertForbidden();
});
