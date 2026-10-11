<?php

namespace App\Exceptions;

use RuntimeException;

/** A tender cannot be registered unless it asks for at least one field code (kod bidang) CMT holds. */
class FieldCodeNotHeld extends RuntimeException
{
    /** @param list<string> $tenderCodes the codes the tender asked for (empty when none were given) */
    public function __construct(public readonly array $tenderCodes)
    {
        parent::__construct($tenderCodes === []
            ? 'This tender has no field codes. Enter the field code(s) it asks for; we can only register tenders under a code CMT holds.'
            : 'We cannot register this tender: CMT does not hold any of its field codes ('.implode(', ', $tenderCodes).').');
    }
}
