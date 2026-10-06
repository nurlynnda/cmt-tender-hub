<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** After a password change, end the user's other browser sessions and invalidate "keep me signed in" cookies. */
final class SignOutElsewhere
{
    public static function for(User $user, ?string $keepSessionId = null): void
    {
        DB::table('sessions')
            ->where('user_id', $user->id)
            ->when($keepSessionId, fn ($q) => $q->where('id', '!=', $keepSessionId))
            ->delete();

        $user->forceFill(['remember_token' => Str::random(60)])->save();
    }
}
