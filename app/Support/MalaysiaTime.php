<?php

namespace App\Support;

use Carbon\CarbonImmutable;

final class MalaysiaTime
{
    public const TZ = 'Asia/Kuala_Lumpur';

    public static function now(): CarbonImmutable
    {
        return CarbonImmutable::now(self::TZ);
    }

    public static function today(): CarbonImmutable
    {
        return self::now()->startOfDay();
    }
}
