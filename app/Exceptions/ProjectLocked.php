<?php

namespace App\Exceptions;

use App\Models\Project;
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

    public static function notActive(Project $project): self
    {
        return $project->tender ? self::notAwarded() : new self('This project is on hold because its quotation is no longer Accepted.');
    }
}
