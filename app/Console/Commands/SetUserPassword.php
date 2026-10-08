<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/** For the server: give an account a new password without it appearing on screen or in the command history. */
class SetUserPassword extends Command
{
    protected $signature = 'users:set-password {email}';

    protected $description = 'Set a new password for an account (asked for twice, hidden while typing)';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();
        if (! $user) {
            $this->error("No account with the email {$this->argument('email')}.");

            return self::FAILURE;
        }
        $password = (string) $this->secret('New password (at least 12 characters, not shown)');
        $again = (string) $this->secret('Type it again');
        if (mb_strlen($password) < 12) {
            $this->error('The password must be at least 12 characters. Nothing was changed.');

            return self::FAILURE;
        }
        if ($password !== $again) {
            $this->error('The two passwords did not match. Nothing was changed.');

            return self::FAILURE;
        }
        $user->forceFill(['password' => Hash::make($password)])->save();
        $this->info("Password changed for {$user->email}.");

        return self::SUCCESS;
    }
}
