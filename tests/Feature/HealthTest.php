<?php

use Illuminate\Support\Facades\DB;

it('answers the health check', function () {
    $this->get('/up')->assertOk();
});

it('runs tests against the real MySQL test database', function () {
    expect(DB::connection()->getDriverName())->toBe('mysql')
        ->and(DB::connection()->getDatabaseName())->toBe('tender_hub_test');
});
