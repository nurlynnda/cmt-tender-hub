<?php

namespace App\Exceptions;

use RuntimeException;

class QuotationLocked extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This quotation has been sent. Revise it to make changes.');
    }
}
