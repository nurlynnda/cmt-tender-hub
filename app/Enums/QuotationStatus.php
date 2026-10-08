<?php

namespace App\Enums;

enum QuotationStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Revised = 'revised';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
