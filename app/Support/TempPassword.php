<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

/** Midlertidig adgangskode, som vises én gang; medlemmet skal vælge sin egen ved første login. */
class TempPassword
{
    public static function make(User $u): string
    {
        // Uden tegn, der let forveksles (0/O, 1/l/I), i grupper på 4, så den er nem at læse op
        $chars = 'abcdefghjkmnpqrstuvwxyz23456789';
        $code = implode('-', array_map(fn () => implode('', array_map(fn () => $chars[random_int(0, strlen($chars) - 1)], range(1, 4))), range(1, 3)));
        $u->forceFill(['password' => Hash::make($code), 'must_change_password' => true])->save();
        $u->tokens()->delete(); // log ud overalt

        return $code;
    }

    /** Det, medlemmet logger ind med (e-mail eller telefonnummer). */
    public static function loginOf(User $u): ?string
    {
        return $u->email ?: ($u->login_phone ? $u->phone : null);
    }
}
