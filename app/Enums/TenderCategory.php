<?php

namespace App\Enums;

enum TenderCategory: string
{
    case ItInfrastructure = 'IT Infrastructure';
    case SoftwareDevelopment = 'Software Development';
    case CivilWorks = 'Civil Works';
    case General = 'General';

    public const Default = self::General;
}
