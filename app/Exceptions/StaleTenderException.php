<?php

namespace App\Exceptions;

use App\Models\Tender;
use RuntimeException;

class StaleTenderException extends RuntimeException
{
    public static function for(Tender $fresh): self
    {
        $name = $fresh->activity()->with('user')->first()?->user?->name ?? 'someone else';

        return new self("This tender was changed by {$name} — reload to see their changes.");
    }
}
