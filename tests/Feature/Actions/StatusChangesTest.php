<?php

use App\Actions\Tenders\{CancelTender, MarkTenderAwarded, MarkTenderDone, MarkTenderLost, ReopenTender};
use App\Enums\TenderStatus;
use App\Exceptions\{DocumentsIncomplete, InvalidTenderTransition, StaleTenderException};
use App\Models\{Tender, TenderDocument, User};
use Illuminate\Auth\Access\AuthorizationException;

function tenderWithDocs(TenderStatus $status = TenderStatus::InProgress, bool $allDone = true): array
{
    $pic = User::factory()->create();
    $tender = Tender::factory()->create(['pic_id' => $pic->id, 'status' => $status]);
    TenderDocument::factory()->for($tender)->create(['name' => 'Borang ISI (Tender Form)', 'position' => 1, 'is_done' => true]);
    TenderDocument::factory()->for($tender)->create(['name' => 'Bid Bond / Bank Guarantee', 'position' => 2, 'is_done' => $allDone]);

    return [$pic, $tender];
}

it('marks a tender Done with its submitted price', function () {
    [$pic, $tender] = tenderWithDocs();

    $done = app(MarkTenderDone::class)->handle($pic, $tender, 1, 16605900);

    expect($done->status)->toBe(TenderStatus::Done)
        ->and($done->submitted_price_sen)->toBe(16605900)
        ->and($done->done_at)->not->toBeNull()
        ->and($done->version)->toBe(2)
        ->and($done->activity->first()->description)->toBe('Marked Done — submitted price RM 166,059.00');
});

it('blocks Mark Done while documents are unticked and names them', function () {
    [$pic, $tender] = tenderWithDocs(allDone: false);

    try {
        app(MarkTenderDone::class)->handle($pic, $tender, 1, 100);
        $this->fail('Expected DocumentsIncomplete');
    } catch (DocumentsIncomplete $e) {
        expect($e->pending)->toBe(['Bid Bond / Bank Guarantee']);
    }
    expect($tender->fresh()->status)->toBe(TenderStatus::InProgress);
});

it('requires a positive submitted price', function () {
    [$pic, $tender] = tenderWithDocs();

    app(MarkTenderDone::class)->handle($pic, $tender, 1, 0);
})->throws(InvalidArgumentException::class);

it('cancels an in-progress tender into Lost with a reason', function () {
    [$pic, $tender] = tenderWithDocs(allDone: false);

    $lost = app(CancelTender::class)->handle($pic, $tender, 1, '  Not our scope  ');

    expect($lost->status)->toBe(TenderStatus::Lost)
        ->and($lost->was_cancelled)->toBeTrue()
        ->and($lost->lost_reason)->toBe('Not our scope')
        ->and($lost->activity->first()->description)->toBe('Cancelled — reason: Not our scope');
});

it('requires a cancel reason', function () {
    [$pic, $tender] = tenderWithDocs();

    app(CancelTender::class)->handle($pic, $tender, 1, '   ');
})->throws(InvalidArgumentException::class);

it('marks a Done tender Awarded', function () {
    [$pic, $tender] = tenderWithDocs(TenderStatus::Done);

    $won = app(MarkTenderAwarded::class)->handle($pic, $tender, 1);

    expect($won->status)->toBe(TenderStatus::Awarded)->and($won->awarded_at)->not->toBeNull();
});

it('marks a Done tender Lost with optional winning price and reason', function () {
    [$pic, $tender] = tenderWithDocs(TenderStatus::Done);

    $lost = app(MarkTenderLost::class)->handle($pic, $tender, 1, 86617900, 'Lower bidder');

    expect($lost->status)->toBe(TenderStatus::Lost)
        ->and($lost->was_cancelled)->toBeFalse()
        ->and($lost->winning_price_sen)->toBe(86617900)
        ->and($lost->activity->first()->description)->toBe('Marked Lost — winning price RM 866,179.00 — reason: Lower bidder');

    [$pic2, $tender2] = tenderWithDocs(TenderStatus::Done);
    expect(app(MarkTenderLost::class)->handle($pic2, $tender2, 1, null, null)->activity->first()->description)
        ->toBe('Marked Lost');
});

it('refuses transitions the lifecycle does not allow', function (string $action, TenderStatus $from) {
    [$pic, $tender] = tenderWithDocs($from);
    $call = match ($action) {
        'done' => fn () => app(MarkTenderDone::class)->handle($pic, $tender, 1, 100),
        'cancel' => fn () => app(CancelTender::class)->handle($pic, $tender, 1, 'x'),
        'awarded' => fn () => app(MarkTenderAwarded::class)->handle($pic, $tender, 1),
        'lost' => fn () => app(MarkTenderLost::class)->handle($pic, $tender, 1, null, null),
    };

    expect($call)->toThrow(InvalidTenderTransition::class);
})->with([
    ['done', TenderStatus::Done], ['done', TenderStatus::Awarded], ['done', TenderStatus::Lost],
    ['cancel', TenderStatus::Done], ['cancel', TenderStatus::Lost],
    ['awarded', TenderStatus::InProgress], ['awarded', TenderStatus::Lost],
    ['lost', TenderStatus::InProgress], ['lost', TenderStatus::Awarded],
]);

it('lets a manager reopen a closed tender and clears the outcome', function () {
    [, $tender] = tenderWithDocs(TenderStatus::Lost);
    $tender->update(['submitted_price_sen' => 100, 'winning_price_sen' => 200, 'lost_reason' => 'x', 'was_cancelled' => true, 'lost_at' => now()]);

    $open = app(ReopenTender::class)->handle(User::factory()->manager()->create(), $tender, 1);

    expect($open->status)->toBe(TenderStatus::InProgress)
        ->and($open->submitted_price_sen)->toBeNull()
        ->and($open->winning_price_sen)->toBeNull()
        ->and($open->lost_reason)->toBeNull()
        ->and($open->was_cancelled)->toBeFalse()
        ->and($open->lost_at)->toBeNull()
        ->and($open->activity->first()->description)->toBe('Reopened (was Lost)');
});

it('refuses reopen by staff and on in-progress tenders', function () {
    [$pic, $tender] = tenderWithDocs(TenderStatus::Awarded);
    expect(fn () => app(ReopenTender::class)->handle($pic, $tender, 1))->toThrow(AuthorizationException::class);

    [, $open] = tenderWithDocs();
    expect(fn () => app(ReopenTender::class)->handle(User::factory()->admin()->create(), $open, 1))
        ->toThrow(InvalidTenderTransition::class);
});

it('refuses status changes by staff who are not the PIC', function () {
    [, $tender] = tenderWithDocs();

    app(CancelTender::class)->handle(User::factory()->create(), $tender, 1, 'x');
})->throws(AuthorizationException::class);

it('refuses status changes from an out-of-date page', function () {
    [$pic, $tender] = tenderWithDocs(TenderStatus::Done);

    app(MarkTenderAwarded::class)->handle($pic, $tender, 7);
})->throws(StaleTenderException::class);
