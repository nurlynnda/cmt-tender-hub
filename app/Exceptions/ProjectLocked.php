<?php

namespace App\Exceptions;

use RuntimeException;

class ProjectLocked extends RuntimeException
{
    public static function closed(): self
    {
        return new self('This project is closed. A Manager can reopen it.');
    }

    public static function notAwarded(): self
    {
        return new self('This project is on hold because its tender is no longer Awarded.');
    }
}
