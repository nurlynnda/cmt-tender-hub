<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('sets a new password for an account, asking for it twice without showing it', function () {
    $u = User::factory()->create(['email' => 'admin@cmt.test']);

    $this->artisan('users:set-password', ['email' => 'admin@cmt.test'])
        ->expectsQuestion('New password (at least 12 characters, not shown)', 'Kopi-Ais-2026!x')
        ->expectsQuestion('Type it again', 'Kopi-Ais-2026!x')
        ->expectsOutputToContain('Password changed for admin@cmt.test')
        ->assertSuccessful();

    expect(Hash::check('Kopi-Ais-2026!x', $u->fresh()->password))->toBeTrue();
});

it('refuses a short password, a mismatch, or an unknown account', function () {
    $u = User::factory()->create(['email' => 'admin@cmt.test']);
    $old = $u->password;

    $this->artisan('users:set-password', ['email' => 'admin@cmt.test'])
        ->expectsQuestion('New password (at least 12 characters, not shown)', 'short')
        ->expectsQuestion('Type it again', 'short')
        ->expectsOutputToContain('at least 12 characters')->assertFailed();
    $this->artisan('users:set-password', ['email' => 'admin@cmt.test'])
        ->expectsQuestion('New password (at least 12 characters, not shown)', 'Kopi-Ais-2026!x')
        ->expectsQuestion('Type it again', 'Kopi-Ais-2026!y')
        ->expectsOutputToContain('did not match')->assertFailed();
    $this->artisan('users:set-password', ['email' => 'nobody@cmt.test'])->expectsOutputToContain('No account')->assertFailed();

    expect($u->fresh()->password)->toBe($old);
});
