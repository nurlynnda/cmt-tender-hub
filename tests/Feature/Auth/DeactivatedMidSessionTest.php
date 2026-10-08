<?php

use App\Actions\Tenders\{RegisterTender, ToggleDocument};
use App\Http\Middleware\EnsureUserIsActive;
use App\Models\{Tender, TenderDocument, User};
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

it('re-checks the active flag on in-page Livewire requests, not just full page loads', function () {
    expect(Livewire::getPersistentMiddleware())->toContain(EnsureUserIsActive::class);
});

it('refuses every tender permission to a deactivated user, even on their own tender', function () {
    $pic = User::factory()->create();
    $tender = Tender::factory()->create(['pic_id' => $pic->id]);
    $pic->update(['is_active' => false]);

    expect(Gate::forUser($pic)->allows('update', $tender))->toBeFalse()
        ->and(Gate::forUser($pic)->allows('create', Tender::class))->toBeFalse()
        ->and(Gate::forUser(User::factory()->admin()->inactive()->create())->allows('reopen', $tender))->toBeFalse()
        ->and(Gate::forUser(User::factory()->admin()->inactive()->create())->allows('manage-users'))->toBeFalse();
});

it('stops a deactivated user from registering or changing tenders through the actions', function () {
    $pic = User::factory()->create();
    $tender = Tender::factory()->create(['pic_id' => $pic->id]);
    $doc = TenderDocument::factory()->for($tender)->create();
    $pic->update(['is_active' => false]);

    expect(fn () => app(ToggleDocument::class)->handle($pic->fresh(), $tender, 1, $doc->id))->toThrow(AuthorizationException::class)
        ->and(fn () => app(RegisterTender::class)->handle($pic->fresh(), tenderData()))->toThrow(AuthorizationException::class);
});
