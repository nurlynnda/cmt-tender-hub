<?php

namespace App\Exceptions;

use RuntimeException;

class CostingRequired extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Add a costing before marking this tender Done.');
    }
}
