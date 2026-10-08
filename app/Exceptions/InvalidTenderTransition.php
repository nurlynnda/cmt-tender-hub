<?php

namespace App\Exceptions;

use App\Enums\TenderStatus;
use RuntimeException;

class InvalidTenderTransition extends RuntimeException
{
    public static function make(TenderStatus $from, string $action): self
    {
        return new self("You can't {$action} a tender that is {$from->label()}.");
    }
}
