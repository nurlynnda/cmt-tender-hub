<?php

namespace App\Enums;

enum TenderMode: string
{
    case Ep = 'EP';
    case NonEp = 'NON_EP';

    public function label(): string
    {
        return $this === self::Ep ? 'EP' : 'Non-EP';
    }
}
