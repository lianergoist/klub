<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Træningssæsonen løber 1. maj – 30. april (regnskabsåret er kalenderåret).
 * Halv sæson: indmeldelse fra 1. august.
 */
class Season
{
    public static function start($date = null): Carbon
    {
        $d = Carbon::parse($date ?? now());

        return Carbon::create($d->month >= 5 ? $d->year : $d->year - 1, 5, 1)->startOfDay();
    }

    public static function end($date = null): Carbon
    {
        return self::start($date)->addYear()->subDay()->endOfDay();
    }

    /** Fx "2026/27" */
    public static function label($date = null): string
    {
        $y = self::start($date)->year;

        return $y.'/'.substr((string) ($y + 1), 2);
    }

    public static function fromStartYear(int $year): Carbon
    {
        return Carbon::create($year, 5, 1)->startOfDay();
    }

    public static function halfStart($date = null): Carbon
    {
        return self::start($date)->setMonth(8)->setDay(1);
    }

    public static function isHalf($date = null): bool
    {
        return Carbon::parse($date ?? now())->gte(self::halfStart($date));
    }
}
