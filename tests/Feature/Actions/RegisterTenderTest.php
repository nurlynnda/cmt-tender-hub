<?php

use App\Actions\Tenders\RegisterTender;
use App\Enums\TenderStatus;
use App\Models\{TenderDocument, User};
use Carbon\CarbonImmutable;

it('registers a tender with a Malaysia-dated WO number, standard documents and a log entry', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-06 16:30:00', 'UTC')); // 00:30 on 7 Oct in Malaysia
    $actor = User::factory()->create();

    $tender = app(RegisterTender::class)->handle($actor, tenderData());

    expect($tender->wo_number)->toBe('200-07102026-001')
        ->and($tender->wo_date->toDateString())->toBe('2026-10-07')
        ->and($tender->status)->toBe(TenderStatus::InProgress)
        ->and($tender->version)->toBe(1)
        ->and($tender->documents->pluck('name')->all())->toBe(TenderDocument::STANDARD)
        ->and($tender->activity->first()->event)->toBe('registered')
        ->and($tender->activity->first()->user_id)->toBe($actor->id);
});

it('ignores keys that are not tender fields', function () {
    $tender = app(RegisterTender::class)->handle(
        User::factory()->create(),
        tenderData(['status' => 'awarded', 'version' => 99, 'wo_number' => 'hack']),
    );

    expect($tender->status)->toBe(TenderStatus::InProgress)
        ->and($tender->version)->toBe(1)
        ->and($tender->wo_number)->not->toBe('hack');
});

it('saves the ministry with the agency', function () {
    $tender = app(RegisterTender::class)->handle(User::factory()->create(),
        tenderData(['ministry' => 'KEMENTERIAN KESIHATAN', 'client' => 'PUSAT DARAH NEGARA']));

    expect($tender->ministry)->toBe('KEMENTERIAN KESIHATAN')->and($tender->client)->toBe('PUSAT DARAH NEGARA');
});
