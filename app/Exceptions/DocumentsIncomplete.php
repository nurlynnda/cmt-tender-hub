<?php

namespace App\Exceptions;

use RuntimeException;

class DocumentsIncomplete extends RuntimeException
{
    /** @param list<string> $pending */
    public function __construct(public readonly array $pending)
    {
        parent::__construct(count($pending).' document(s) still not ticked: '.implode(', ', $pending).'.');
    }
}
