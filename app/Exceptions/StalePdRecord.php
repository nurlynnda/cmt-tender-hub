<?php

namespace App\Exceptions;

use App\Models\{PdLine, Project};
use RuntimeException;

class StalePdRecord extends RuntimeException
{
    public static function line(PdLine $fresh): self
    {
        return new self('This line was changed by '.($fresh->updatedBy?->name ?? 'someone else').' — reload to see their changes.');
    }

    public static function project(Project $fresh): self
    {
        return new self('This project was changed by '.($fresh->updatedBy?->name ?? 'someone else').' — reload to see their changes.');
    }
}
