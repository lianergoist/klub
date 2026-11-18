<?php

namespace App\Support;

use App\Models\User;

/**
 * Telefonnummer som login. Numre gemmes ens i users.login_phone (kun cifre, uden +45), så "20 45 67 89",
 * "+45 20456789" og "004520456789" er det samme. Hvert nummer kan kun bruges til login af ét medlem.
 */
class Phone
{
    public static function normalize(?string $phone): ?string
    {
        $d = preg_replace('/\D/', '', (string) $phone);
        if (str_starts_with($d, '0045') && strlen($d) === 12) $d = substr($d, 4);
        elseif (str_starts_with($d, '45') && strlen($d) === 10) $d = substr($d, 2);

        return strlen($d) >= 8 ? $d : null;
    }

    public static function takenBy(?string $phone, ?int $ignoreId = null): ?User
    {
        $n = self::normalize($phone);

        return $n ? User::where('login_phone', $n)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->first() : null;
    }

    /** Valideringsregel: nummeret må ikke bruges af et andet medlem. */
    public static function rule(?int $ignoreId = null): \Closure
    {
        return function (string $attr, $value, \Closure $fail) use ($ignoreId) {
            if ($value && self::takenBy($value, $ignoreId)) {
                $fail('Telefonnummeret '.$value.' bruges allerede af et andet medlem. Hvert medlem skal have sit eget nummer for at kunne logge ind med det.');
            }
        };
    }
}
