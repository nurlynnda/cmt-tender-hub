<?php

namespace App\Exceptions;

use App\Models\Quotation;
use RuntimeException;

class StaleQuotation extends RuntimeException
{
    public static function for(Quotation $fresh): self
    {
        return new self('This quotation was changed by '.($fresh->updatedBy?->name ?? 'someone else').' — reload to see their changes.');
    }
}
