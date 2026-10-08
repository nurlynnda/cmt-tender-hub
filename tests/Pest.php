<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

pest()->extend(Tests\TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(Tests\TestCase::class)->in('Unit');

// Tests that need committed rows (MySQL FULLTEXT ignores uncommitted ones) truncate instead of rolling back.
pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\DatabaseTruncation::class)
    ->in('Integration');

function tenderData(array $overrides = []): array
{
    return array_merge([
        'mode' => App\Enums\TenderMode::Ep,
        'type' => App\Enums\TenderType::Tender,
        'category' => App\Enums\TenderCategory::SoftwareDevelopment,
        'tender_code' => 'QT260000000041127',
        'title' => 'PERKHIDMATAN PEMBANGUNAN SISTEM',
        'client' => 'JABATAN PERPADUAN NEGARA DAN INTEGRASI NASIONAL',
        'scope' => null,
        'pic_id' => App\Models\User::factory()->create()->id,
        'owner_id' => null,
        'publish_date' => '2026-09-08',
        'closing_date' => '2026-10-15',
        'has_briefing' => false,
        'briefing_date' => null,
        'estimated_value_sen' => 16200610,
    ], $overrides);
}
