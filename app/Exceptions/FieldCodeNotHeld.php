<?php

namespace App\Exceptions;

use RuntimeException;

/** A tender is normally not registered unless it asks for at least one field code (kod bidang) the company holds. */
class FieldCodeNotHeld extends RuntimeException
{
    /** @param list<string> $tenderCodes the codes the tender asked for (empty when none were given) */
    public function __construct(public readonly array $tenderCodes)
    {
        parent::__construct($tenderCodes === []
            ? 'This tender has no field codes. Enter the field code(s) it asks for; we normally register tenders only under a code the company holds.'
            : 'We cannot register this tender: 10 Creative Solutions does not hold any of its field codes ('.implode(', ', $tenderCodes).').');
    }
}
