<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        // No test may ever contact a real website (government sites included).
        \Illuminate\Support\Facades\Http::preventStrayRequests();
    }
}
