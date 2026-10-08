<?php

namespace App\Exceptions;

use RuntimeException;

class QuotationIncomplete extends RuntimeException
{
    /** @param list<string> $missing e.g. ['a customer name', 'a subject'] */
    public function __construct(array $missing)
    {
        $list = count($missing) > 1 ? implode(', ', array_slice($missing, 0, -1)).' and '.end($missing) : $missing[0];
        parent::__construct("Add {$list} before marking this quotation Sent.");
    }
}
