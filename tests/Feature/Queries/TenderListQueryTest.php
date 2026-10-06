<?php

use App\Enums\{TenderCategory, TenderMode, TenderStatus};
use App\Models\{Tender, User};
use App\Queries\TenderListQuery;

function listOf(TenderStatus $status, array $filters = [], ?User $viewer = null): array
{
    return TenderListQuery::build($status, $viewer ?? User::factory()->create(), $filters)
        ->pluck('wo_number')->all();
}

it('shows only the requested status, soonest closing first for In Progress', function () {
    Tender::factory()->create(['wo_number' => 'A', 'closing_date' => '2026-12-01']);
    Tender::factory()->create(['wo_number' => 'B', 'closing_date' => '2026-11-01']);
    Tender::factory()->status(TenderStatus::Done)->create(['wo_number' => 'C']);

    expect(listOf(TenderStatus::InProgress))->toBe(['B', 'A']);
});

it('sorts closed lists latest closing first', function () {
    Tender::factory()->status(TenderStatus::Done)->create(['wo_number' => 'A', 'closing_date' => '2026-01-01']);
    Tender::factory()->status(TenderStatus::Done)->create(['wo_number' => 'B', 'closing_date' => '2026-02-01']);

    expect(listOf(TenderStatus::Done))->toBe(['B', 'A']);
});

it('searches WO number, code, title and client, treating % and _ literally', function () {
    Tender::factory()->create(['wo_number' => 'W1', 'title' => 'SEWAAN KOMPUTER RIBA']);
    Tender::factory()->create(['wo_number' => 'W2', 'client' => 'PUSAT DARAH NEGARA']);
    Tender::factory()->create(['wo_number' => 'W3', 'tender_code' => 'SH250000000019233']);
    Tender::factory()->create(['wo_number' => 'W4', 'title' => '100% UPTIME']);

    expect(listOf(TenderStatus::InProgress, ['search' => 'riba']))->toBe(['W1'])
        ->and(listOf(TenderStatus::InProgress, ['search' => 'darah']))->toBe(['W2'])
        ->and(listOf(TenderStatus::InProgress, ['search' => 'SH2500']))->toBe(['W3'])
        ->and(listOf(TenderStatus::InProgress, ['search' => 'W2']))->toBe(['W2'])
        ->and(listOf(TenderStatus::InProgress, ['search' => '%']))->toBe(['W4']);
});

it('filters to my tenders as PIC or opportunity owner', function () {
    $me = User::factory()->create();
    Tender::factory()->create(['wo_number' => 'PIC', 'pic_id' => $me->id]);
    Tender::factory()->create(['wo_number' => 'OO', 'owner_id' => $me->id]);
    Tender::factory()->create(['wo_number' => 'OTHER']);

    expect(listOf(TenderStatus::InProgress, ['mine' => true], $me))->toEqualCanonicalizing(['PIC', 'OO']);
});

it('filters by mode, PIC, category and closing range', function () {
    $pic = User::factory()->create();
    Tender::factory()->create(['wo_number' => 'X', 'mode' => TenderMode::NonEp, 'pic_id' => $pic->id,
        'category' => TenderCategory::CivilWorks, 'closing_date' => '2026-10-10']);
    Tender::factory()->create(['wo_number' => 'Y', 'closing_date' => '2026-12-10']);

    expect(listOf(TenderStatus::InProgress, ['mode' => 'NON_EP']))->toBe(['X'])
        ->and(listOf(TenderStatus::InProgress, ['pic' => (string) $pic->id]))->toBe(['X'])
        ->and(listOf(TenderStatus::InProgress, ['category' => 'Civil Works']))->toBe(['X'])
        ->and(listOf(TenderStatus::InProgress, ['from' => '2026-11-01', 'to' => '2026-12-31']))->toBe(['Y']);
});

it('ignores filter values that are not valid', function () {
    Tender::factory()->create(['wo_number' => 'Z']);

    expect(listOf(TenderStatus::InProgress, ['mode' => 'BOGUS', 'category' => 'nope', 'from' => 'garbage', 'pic' => 'abc']))
        ->toBe(['Z']);
});
