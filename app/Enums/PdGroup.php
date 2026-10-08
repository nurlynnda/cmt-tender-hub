<?php

namespace App\Enums;

enum PdGroup: string
{
    case Collection = 'collection';
    case Principal = 'principal';
    case Distributor = 'distributor';
    case Partner = 'partner';
    case FinanceCost = 'finance_cost';
    case Tax = 'tax';
    case Misc = 'misc';
    case Internal = 'internal';

    public function label(): string
    {
        return match ($this) {
            self::Collection => 'Collection',
            self::Principal => 'Principal',
            self::Distributor => 'Distributor',
            self::Partner => 'Partner',
            self::FinanceCost => 'Finance Cost',
            self::Tax => 'Tax (SST)',
            self::Misc => 'Misc, Training & Travel',
            self::Internal => 'Internal Resources (Manpower)',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Collection => 'Customer payment schedule according to the contract terms.',
            self::Principal => 'Costs paid to principals.',
            self::Distributor => 'Costs paid to distributors.',
            self::Partner => 'Costs paid to partners and subcontractors.',
            self::FinanceCost => 'Trade finance, leasing, insurance bonds and other financial costs.',
            self::Tax => 'SST and other taxes on costs.',
            self::Misc => 'Entertainment, training, travelling and other fees.',
            self::Internal => 'Internal manpower charged to the project.',
        };
    }

    public function isCollection(): bool
    {
        return $this === self::Collection;
    }

    public function isCostOfSales(): bool
    {
        return in_array($this, [self::Principal, self::Distributor, self::Partner], true);
    }

    /** @return list<PdEntryType> */
    public function entryTypes(): array
    {
        return $this->isCollection()
            ? [PdEntryType::Invoice, PdEntryType::Receipt]
            : [PdEntryType::Pr, PdEntryType::Po, PdEntryType::Invoice, PdEntryType::Payment];
    }

    /** @return list<self> */
    public static function costGroups(): array
    {
        return array_values(array_filter(self::cases(), fn (self $g) => ! $g->isCollection()));
    }
}
