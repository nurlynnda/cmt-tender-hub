<?php

namespace App\Collector;

/** One comparable key per company: "10 Creative Solutions Sdn. Bhd." and "10 CREATIVE SOLUTIONS SDN BHD" match. */
final class ContractorName
{
    public static function key(string $name): string
    {
        return trim(preg_replace('/\s+/u', ' ', preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtoupper($name))));
    }
}
