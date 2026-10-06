<?php

use App\Enums\{Role, TenderStatus};
use App\Models\{Tender, User};
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;

it('loads the prototype people and tenders', function () {
    $this->seed(DatabaseSeeder::class);

    expect(User::count())->toBe(6)
        ->and(User::where('email', 'admin@cmt.test')->first()->role)->toBe(Role::Admin)
        ->and(User::where('email', 'manager@cmt.test')->first()->role)->toBe(Role::Manager)
        ->and(Tender::count())->toBe(41)
        ->and(Tender::where('status', TenderStatus::InProgress)->count())->toBe(13)
        ->and(Tender::where('status', TenderStatus::Done)->count())->toBe(20)
        ->and(Tender::where('status', TenderStatus::Awarded)->count())->toBe(3)
        ->and(Tender::where('status', TenderStatus::Lost)->count())->toBe(5)
        ->and(Tender::where('wo_number', '200-10092026-001')->first()->documentPercent())->toBe(40)
        ->and(Tender::where('status', '!=', TenderStatus::InProgress)->get()->every(fn ($t) => $t->documentPercent() === 100))->toBeTrue()
        ->and(Tender::first()->activity()->count())->toBeGreaterThan(0);
});

it('primes the WO sequence so the next registration on a seeded day does not collide', function () {
    $this->seed(DatabaseSeeder::class);

    expect(DB::table('wo_sequences')->where('date', '2025-12-22')->value('last_seq'))->toBe(6);
});

it('refuses to run in production', function () {
    app()->detectEnvironment(fn () => 'production');

    // Called directly: `db:seed` would stop at Laravel's own "are you sure?" prompt first.
    app(DatabaseSeeder::class)->run();
})->throws(RuntimeException::class, 'never be loaded in production');
