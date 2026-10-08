<?php

use App\Enums\{Role, TenderCategory, TenderMode, TenderStatus, TenderType};

it('labels roles and knows who manages all tenders', function () {
    expect(Role::Staff->label())->toBe('Staff')
        ->and(Role::Staff->canManageAllTenders())->toBeFalse()
        ->and(Role::Manager->canManageAllTenders())->toBeTrue()
        ->and(Role::Admin->canManageAllTenders())->toBeTrue();
});

it('maps statuses to labels and URL slugs both ways', function () {
    expect(TenderStatus::InProgress->label())->toBe('In Progress')
        ->and(TenderStatus::InProgress->slug())->toBe('in-progress')
        ->and(TenderStatus::fromSlug('awarded'))->toBe(TenderStatus::Awarded)
        ->and(TenderStatus::Lost->listTitle())->toBe('Lost Tenders');
});

it('refuses unknown status slugs', function () {
    TenderStatus::fromSlug('nope');
})->throws(ValueError::class);

it('labels mode, type and category', function () {
    expect(TenderMode::NonEp->label())->toBe('Non-EP')
        ->and(TenderType::Quotation->label())->toBe('Quotation')
        ->and(TenderCategory::from('Civil Works'))->toBe(TenderCategory::CivilWorks)
        ->and(TenderCategory::Default)->toBe(TenderCategory::General);
});
