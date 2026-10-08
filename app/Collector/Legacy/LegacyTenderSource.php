<?php

namespace App\Collector\Legacy;

interface LegacyTenderSource
{
    public function count(): int;

    /** @return iterable<array> raw tms-v2 Mongo documents as arrays */
    public function documents(): iterable;
}
