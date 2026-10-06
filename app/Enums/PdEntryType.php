<?php

namespace App\Enums;

enum PdEntryType: string
{
    case Pr = 'pr';
    case Po = 'po';
    case Invoice = 'invoice';
    case Payment = 'payment';
    case Receipt = 'receipt';

    public function label(): string
    {
        return match ($this) {
            self::Pr => 'PR',
            self::Po => 'PO',
            self::Invoice => 'Invoice',
            self::Payment => 'Payment',
            self::Receipt => 'Receipt',
        };
    }
}
