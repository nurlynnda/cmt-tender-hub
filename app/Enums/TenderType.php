<?php

namespace App\Enums;

enum TenderType: string
{
    case Tender = 'TENDER';
    case Quotation = 'QUOTATION';

    public function label(): string
    {
        return $this === self::Tender ? 'Tender' : 'Quotation';
    }
}
