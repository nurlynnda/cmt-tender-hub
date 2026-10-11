<?php

namespace App\Exceptions;

use App\Support\CompanyFieldCodes;
use RuntimeException;

/** A tender is normally not registered unless the company holds every field code (kod bidang) it asks for. */
class FieldCodeNotHeld extends RuntimeException
{
    /** @var list<string> the codes the tender asks for that the company does not hold (all of them when it asks for none) */
    public readonly array $missing;

    /** @param list<string> $tenderCodes the codes the tender asked for (empty when none were given) */
    public function __construct(public readonly array $tenderCodes)
    {
        $this->missing = CompanyFieldCodes::missing($tenderCodes);

        parent::__construct($tenderCodes === []
            ? 'This tender has no field codes. Enter the field code(s) it asks for; we normally register tenders only when the company holds all of them.'
            : 'We cannot register this tender: 10 Creative Solutions does not hold its field code(s) '.implode(', ', $this->missing).'.');
    }
}
